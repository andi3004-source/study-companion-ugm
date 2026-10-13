<?php

namespace Tests\Feature;

use App\Contracts\SessionAnalyzer;
use App\Jobs\AnalyzeSessionJob;
use App\Models\ConversationSession;
use App\Models\ConversationTurn;
use App\Models\User;
use App\Services\FallbackSessionAnalyzer;
use App\Services\GeminiSessionAnalyzer;
use App\Support\GeminiRetry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * The end-of-session analysis must finish inside AnalyzeSessionJob::$timeout,
 * and $timeout must stay below the queue's retry_after, or a slow run is
 * handed to a second worker and spends its AI calls twice.
 */
class AnalysisDeadlineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.gemini.key' => 'test-key', 'gemini.retry_jitter_ms' => 0]);
        $this->freezeTime();
    }

    private function mandarinSession(): ConversationSession
    {
        $session = ConversationSession::create(['user_id' => User::factory()->create()->id, 'lang' => 'mandarin', 'scenario_key' => 'restaurant_ordering', 'started_at' => now(), 'ended_at' => now()]);
        ConversationTurn::create(['session_id' => $session->id, 'role' => 'user', 'content' => '我不有钱', 'input_mode' => 'text']);

        return $session;
    }

    /** A Langflow stand-in that "takes" $seconds and then fails, so Gemini takes over. */
    private function slowFailingLangflow(int $seconds): SessionAnalyzer
    {
        return new class($seconds) implements SessionAnalyzer
        {
            public function __construct(private int $seconds) {}

            public function isConfigured(): bool
            {
                return true;
            }

            public function analyze(string $transcript, ?int $sessionId = null): array
            {
                Carbon::setTestNow(Carbon::now()->addSeconds($this->seconds));
                throw new RuntimeException('langflow down');
            }
        };
    }

    public function test_job_timeout_stays_below_the_queue_retry_after(): void
    {
        $job = new AnalyzeSessionJob(1);

        $this->assertSame(200, config('queue.connections.database.retry_after'));
        $this->assertSame(180, $job->timeout);
        $this->assertLessThan(config('queue.connections.database.retry_after'), $job->timeout);
        $this->assertTrue($job->failOnTimeout);
    }

    public function test_analysis_alone_gets_its_full_budget_as_the_per_call_timeout(): void
    {
        $timeouts = [];
        Http::fake(function (Request $request, array $options) use (&$timeouts) {
            $timeouts[] = $options['timeout'];

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"items":[],"catatan":"Bagus."}']]]]]]);
        });
        $session = $this->mandarinSession();

        (new AnalyzeSessionJob($session->id))->handle(app(GeminiSessionAnalyzer::class));

        $this->assertSame('Bagus.', $session->fresh()->error_analysis['catatan']);
        $this->assertSame([120.0], array_map('floatval', $timeouts));
    }

    public function test_slow_langflow_then_busy_gemini_still_ends_before_the_job_timeout(): void
    {
        $this->app->instance(SessionAnalyzer::class, new FallbackSessionAnalyzer(
            $this->slowFailingLangflow(120),
            app(GeminiSessionAnalyzer::class),
        ));
        $timeouts = [];
        // Every Gemini attempt hangs until its timeout and then answers 503.
        Http::fake(function (Request $request, array $options) use (&$timeouts) {
            $timeouts[] = $options['timeout'];
            $this->travel((int) ceil($options['timeout']))->seconds();

            return Http::response([], 503);
        });
        $session = $this->mandarinSession();
        $start = now();

        (new AnalyzeSessionJob($session->id))->handle(app(SessionAnalyzer::class));

        // 180s job timeout - 15s margin - 120s Langflow = 45s left for Gemini, not its usual 120s.
        $this->assertSame(45.0, (float) $timeouts[0]);
        $this->assertLessThanOrEqual(180 - AnalyzeSessionJob::DEADLINE_MARGIN_SECONDS, $start->diffInSeconds(now()));
        $this->assertNotNull($session->fresh()->analyzed_at);
        $this->assertNull($session->fresh()->error_analysis);
    }

    public function test_within_cuts_a_budget_and_restores_it_afterwards(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            $this->travel(5)->seconds();

            return Http::response([], 503);
        });
        $send = fn (string $model, float $timeout) => Http::timeout($timeout)->post('https://generativelanguage.googleapis.com/x')->throw();

        // 5s call + 1s wait + 1s minimum call > 6s: no retry inside a 6s deadline.
        rescue(fn () => GeminiRetry::within(6, fn () => GeminiRetry::send(GeminiRetry::ANALYSIS, 'm', INF, $send)), report: false);
        $this->assertSame(1, $calls);

        // Outside within(), the 120s analysis budget allows all three attempts.
        $calls = 0;
        rescue(fn () => GeminiRetry::send(GeminiRetry::ANALYSIS, 'm', INF, $send), report: false);
        $this->assertSame(3, $calls);
    }

    public function test_nested_within_keeps_the_earlier_deadline(): void
    {
        $timeouts = [];
        Http::fake(function (Request $request, array $options) use (&$timeouts) {
            $timeouts[] = $options['timeout'];

            return Http::response([]);
        });
        $send = fn (string $model, float $timeout) => Http::timeout($timeout)->post('https://generativelanguage.googleapis.com/x')->throw();

        GeminiRetry::within(10, fn () => GeminiRetry::within(100, fn () => GeminiRetry::send(GeminiRetry::ANALYSIS, 'm', INF, $send)));

        $this->assertSame([10.0], array_map('floatval', $timeouts));
    }
}
