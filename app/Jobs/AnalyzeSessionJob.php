<?php

namespace App\Jobs;

use App\Contracts\SessionAnalyzer;
use App\Exceptions\LangflowRequestException;
use App\Models\ConversationApiCall;
use App\Models\ConversationSession;
use App\Services\FallbackSessionAnalyzer;
use App\Support\ErrorAnalysis;
use App\Support\GeminiRetry;
use App\Support\SessionTranscript;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * "Kesalahan terbanyakmu": analyzes a finished Mandarin session's transcript
 * (see SessionAnalyzer) and stores the result on the session. Dispatched by
 * ConversationService::endSession().
 *
 * Idempotent: a session whose analyzed_at is already set is skipped, so a
 * duplicate dispatch never spends a second API call — `analysis:retry-failed`
 * clears analyzed_at first when a failed analysis should run again.
 *
 * Never throws: a failure is reported and stored as analyzed_at set +
 * error_analysis NULL ("tried and failed"), which is what the chat summary
 * and the retry command look for. A success with no mistakes is stored as
 * {"items": [], ...}, never NULL.
 *
 * Retries: on a real queue worker, a transient Langflow failure (429/502/
 * 503/504, "high demand", "overloaded") releases the job for another try
 * after $backoff, up to $tries attempts; the last attempt falls back to
 * Gemini as usual. On the sync queue (also used by dispatchAfterResponse)
 * a release would never run again, so there the job goes straight to
 * Langflow-then-Gemini and always finishes the session.
 */
class AnalyzeSessionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Langflow alone may take up to services.langflow.timeout (120s by
    // default) before the Gemini fallback starts. Gemini's own budget
    // (gemini.retry_budgets.analysis, 120s) is cut to what is left of
    // $timeout minus DEADLINE_MARGIN_SECONDS, so the whole analysis ends
    // before the worker kills the job. The queue's retry_after must stay
    // above $timeout (DB_QUEUE_RETRY_AFTER, 200 by default) or a slow run is
    // handed to a second worker and runs twice.
    public int $timeout = 180;

    /** Seconds kept free at the end of $timeout for storing the result. */
    public const DEADLINE_MARGIN_SECONDS = 15;

    // Only transient Langflow failures are retried (see handle()); every
    // other failure is caught and recorded — see `analysis:retry-failed`.
    public int $tries = 3;

    /** Seconds before the 2nd and 3rd attempt. */
    public array $backoff = [15, 45];

    // A run killed by $timeout has already spent minutes of AI calls: mark
    // it failed (see failed()) instead of re-running it.
    public bool $failOnTimeout = true;

    public function __construct(public readonly int $sessionId) {}

    public function handle(SessionAnalyzer $analyzer): void
    {
        $session = ConversationSession::find($this->sessionId);

        if (! $session || $session->lang !== 'mandarin' || $session->analyzed_at !== null) {
            return;
        }

        if (! $session->turns()->where('role', 'user')->exists()) {
            return;
        }

        $canRetry = $this->canRetryLater();

        if ($canRetry && $analyzer instanceof FallbackSessionAnalyzer) {
            $analyzer = $analyzer->deferringTransientFailures();
        }

        try {
            $result = GeminiRetry::within(
                $this->timeout - self::DEADLINE_MARGIN_SECONDS,
                fn () => $analyzer->analyze(SessionTranscript::build($session), $session->id),
            );
        } catch (Throwable $e) {
            report($e);

            if ($canRetry && $e instanceof LangflowRequestException && $e->transient) {
                $delay = $this->backoff[min($this->attempts(), count($this->backoff)) - 1];
                $this->release($delay);
                Log::info("AnalyzeSessionJob: session {$session->id} Langflow busy (HTTP {$e->status}), retrying in {$delay}s.");

                return;
            }

            $session->update(['error_analysis' => null, 'analyzed_at' => now()]);
            Log::info("AnalyzeSessionJob: session {$session->id} analysis failed.");

            return;
        }

        $usage = $result['usage'] ?? null;

        // normalize() keeps only items/catatan/sumber, so 'usage' is never stored.
        $session->update(['error_analysis' => ErrorAnalysis::normalize($result), 'analyzed_at' => now()]);

        if (is_array($usage) && (isset($usage['tokens_in']) || isset($usage['tokens_out']))) {
            ConversationApiCall::create([
                'session_id' => $session->id,
                'tokens_in' => $usage['tokens_in'] ?? null,
                'tokens_out' => $usage['tokens_out'] ?? null,
            ]);
        }
    }

    /**
     * The worker gave up on the job (killed by $timeout, or attempts ran out
     * without handle() finishing): record "tried and failed" so the summary
     * stops waiting and `analysis:retry-failed` can pick the session up.
     */
    public function failed(?Throwable $e): void
    {
        ConversationSession::whereKey($this->sessionId)
            ->whereNull('analyzed_at')
            ->update(['error_analysis' => null, 'analyzed_at' => now()]);
    }

    /** Whether release() would actually run this job again: a real queue worker with attempts left. */
    private function canRetryLater(): bool
    {
        return $this->job !== null
            && ! $this->job instanceof SyncJob
            && $this->attempts() < $this->tries;
    }
}
