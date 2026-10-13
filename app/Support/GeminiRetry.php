<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * The one place that retries Gemini HTTP calls (GeminiClient, so chat and the
 * Gemini error analyzer, and GeminiObjectRecognizer). Only a 503 or a
 * connection error/timeout is retried; 400, 403, 404 and 429 fail at once.
 * When the model still answers 503 after its retries, the next model in
 * gemini.fallback_models (empty by default) gets the same rules. Everything,
 * waits and fallbacks included, stays inside the budget for that kind of
 * call (gemini.retry_budgets: chat, scan, analysis).
 *
 * The caller's closure holds the request body (for a scan, the photo), so a
 * retry resends it from memory: nothing is written to disk here. The log gets
 * the error class, HTTP status, attempt number and model, never a message,
 * body, image or key.
 */
class GeminiRetry
{
    public const CHAT = 'chat';

    public const SCAN = 'scan';

    public const ANALYSIS = 'analysis';

    /** An attempt is not started with less time than this left in the budget. */
    private const MIN_ATTEMPT_SECONDS = 1.0;

    /** Outer deadline set by within(); null outside it. */
    private static ?Carbon $deadline = null;

    /**
     * Runs $callback with every Gemini call inside it ending by $seconds from
     * now: each call's budget is cut to the time left. AnalyzeSessionJob uses
     * this so Langflow plus the Gemini fallback never outlive the job's own
     * timeout. Nested calls keep the earlier deadline.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function within(float $seconds, Closure $callback): mixed
    {
        $previous = self::$deadline;
        $deadline = Carbon::now()->addMilliseconds((int) round($seconds * 1000));
        self::$deadline = $previous !== null && $previous->lt($deadline) ? $previous : $deadline;

        try {
            return $callback();
        } finally {
            self::$deadline = $previous;
        }
    }

    /**
     * @param  string  $kind  self::CHAT, self::SCAN or self::ANALYSIS: picks the time budget
     * @param  float  $timeout  per-attempt timeout; INF means "whatever is left of the budget"
     * @param  Closure(string $model, float $timeout): Response  $send  must throw on failure (->throw())
     */
    public static function send(string $kind, string $model, float $timeout, Closure $send): Response
    {
        $start = Carbon::now();
        $budget = self::budget($kind);

        if (self::$deadline !== null) {
            $budget = min($budget, max(0.0, $start->diffInMilliseconds(self::$deadline) / 1000));
        }
        $models = array_values(array_unique([$model, ...(array) config('gemini.fallback_models', [])]));

        foreach ($models as $i => $current) {
            try {
                return self::attempts($current, $timeout, $send, $start, $budget);
            } catch (RequestException $e) {
                $next = $i + 1 < count($models);

                if (! $next || $e->response->status() !== 503 || $budget - self::elapsed($start) < self::MIN_ATTEMPT_SECONDS) {
                    throw $e;
                }
            }
        }
    }

    /** The first attempt plus up to gemini.retry_max retries on one model. */
    private static function attempts(string $model, float $timeout, Closure $send, Carbon $start, float $budget): Response
    {
        $maxRetries = max(0, (int) config('gemini.retry_max', 2));
        $attempt = 0;

        while (true) {
            $attempt++;
            $remaining = $budget - self::elapsed($start);

            try {
                return $send($model, min($timeout, max($remaining, self::MIN_ATTEMPT_SECONDS)));
            } catch (RequestException $e) {
                $status = $e->response->status();
                $retryable = $status === 503;
            } catch (ConnectionException $e) {
                $status = null;
                $retryable = true;
            }

            Log::warning('gemini: percobaan gagal', [
                'error' => $e::class,
                'status' => $status,
                'percobaan' => $attempt,
                'model' => $model,
            ]);

            if (! $retryable || $attempt > $maxRetries) {
                throw $e;
            }

            $delay = self::delay($attempt);

            if (self::elapsed($start) + $delay + self::MIN_ATTEMPT_SECONDS > $budget) {
                throw $e;
            }

            Sleep::for((int) round($delay * 1000))->milliseconds();
        }
    }

    /** Seconds for the whole call: gemini.retry_budgets.{kind}, else the older gemini.retry_budget_seconds. */
    public static function budget(string $kind): float
    {
        return (float) (config("gemini.retry_budgets.{$kind}") ?: config('gemini.retry_budget_seconds', 12));
    }

    /** Seconds to wait before retry number $retry (1-based), jitter included. */
    private static function delay(int $retry): float
    {
        $delays = config('gemini.retry_delays_seconds') ?: [1, 3];
        $base = (float) ($delays[$retry - 1] ?? end($delays));
        $jitterMs = max(0, (int) config('gemini.retry_jitter_ms', 250));

        return $base + random_int(0, $jitterMs) / 1000;
    }

    private static function elapsed(Carbon $start): float
    {
        return abs($start->diffInMilliseconds(Carbon::now())) / 1000;
    }
}
