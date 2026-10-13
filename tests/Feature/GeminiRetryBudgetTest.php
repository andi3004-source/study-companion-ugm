<?php

namespace Tests\Feature;

use App\Contracts\SessionAnalyzer;
use App\Jobs\AnalyzeSessionJob;
use App\Models\ConversationSession;
use App\Models\ConversationTurn;
use App\Models\User;
use App\Services\GeminiClient;
use App\Support\GeminiRetry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Concerns\UsesScanCatalog;
use Tests\TestCase;

/** Each kind of Gemini call has its own time budget; see config/gemini.php. */
class GeminiRetryBudgetTest extends TestCase
{
    use RefreshDatabase;
    use UsesScanCatalog;

    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    /** @var float[] per-attempt timeout handed to the HTTP client */
    private array $timeouts = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.gemini.key' => 'test-key', 'gemini.retry_jitter_ms' => 0]);
        $this->freezeTime();
    }

    private function ok(string $text): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $text]]]]]];
    }

    /**
     * Each attempt "takes" its number of seconds (never more than its timeout,
     * as a real request would be cut off) and then answers with its status.
     *
     * @param  array<int, array{0: int, 1: int}>  $script  [seconds, status] per attempt; 200 answers $okText
     */
    private function fakeAttempts(array $script, string $okText = 'Halo'): void
    {
        Http::fake(function (Request $request, array $options) use (&$script, $okText) {
            [$seconds, $status] = array_shift($script) ?? [1, 503];
            $this->timeouts[] = (float) $options['timeout'];
            $this->travel((int) min($seconds, $options['timeout']))->seconds();

            return $status === 200 ? Http::response($this->ok($okText)) : Http::response([], $status);
        });
    }

    private function chat(string $kind = GeminiRetry::CHAT): array
    {
        return (new GeminiClient)->forKind($kind)->sendMessage('gemini-3.6-flash', 'sistem', [['role' => 'user', 'content' => 'Hi']]);
    }

    /** Evaluates config/gemini.php under the given environment variables, then restores them. */
    private function geminiConfigWith(array $env): array
    {
        $saved = [];
        foreach ($env as $name => $value) {
            $saved[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null, getenv($name)];
            $_ENV[$name] = $_SERVER[$name] = $value;
            putenv("{$name}={$value}");
        }

        try {
            return require config_path('gemini.php');
        } finally {
            foreach ($saved as $name => [$env, $server, $put]) {
                $_ENV[$name] = $env;
                $_SERVER[$name] = $server;
                putenv($put === false ? $name : "{$name}={$put}");
            }
        }
    }

    public function test_default_budgets_are_pinned(): void
    {
        $this->assertSame(['chat' => 15.0, 'scan' => 30.0, 'analysis' => 120.0], config('gemini.retry_budgets'));
        $this->assertSame(12.0, config('gemini.retry_budget_seconds'));
        $this->assertSame(15.0, GeminiRetry::budget(GeminiRetry::CHAT));
        $this->assertSame(30.0, GeminiRetry::budget(GeminiRetry::SCAN));
        $this->assertSame(120.0, GeminiRetry::budget(GeminiRetry::ANALYSIS));
    }

    public function test_budgets_come_from_their_own_variables(): void
    {
        $config = $this->geminiConfigWith(['GEMINI_RETRY_BUDGET_CHAT' => '20', 'GEMINI_RETRY_BUDGET_SCAN' => '40', 'GEMINI_RETRY_BUDGET_ANALYSIS' => '90']);

        $this->assertSame(['chat' => 20.0, 'scan' => 40.0, 'analysis' => 90.0], $config['retry_budgets']);
    }

    public function test_scan_budget_honours_scan_benda_timeout_when_its_own_variable_is_empty(): void
    {
        $this->assertSame(45.0, $this->geminiConfigWith(['GEMINI_RETRY_BUDGET_SCAN' => '', 'SCAN_BENDA_TIMEOUT' => '45'])['retry_budgets']['scan']);
        $this->assertSame(30.0, $this->geminiConfigWith(['GEMINI_RETRY_BUDGET_SCAN' => '', 'SCAN_BENDA_TIMEOUT' => ''])['retry_budgets']['scan']);
        $this->assertSame(50.0, $this->geminiConfigWith(['GEMINI_RETRY_BUDGET_SCAN' => '50', 'SCAN_BENDA_TIMEOUT' => '45'])['retry_budgets']['scan']);
    }

    public function test_empty_variables_fall_back_to_the_defaults(): void
    {
        $config = $this->geminiConfigWith(['GEMINI_RETRY_BUDGET_CHAT' => '', 'GEMINI_RETRY_BUDGET_ANALYSIS' => '']);

        $this->assertSame(15.0, $config['retry_budgets']['chat']);
        $this->assertSame(120.0, $config['retry_budgets']['analysis']);
    }

    public function test_the_old_single_budget_still_covers_kinds_without_their_own(): void
    {
        config(['gemini.retry_budget_seconds' => 7]);

        $this->assertSame(7.0, GeminiRetry::budget('jenis-lain'));
        $this->assertSame(15.0, GeminiRetry::budget(GeminiRetry::CHAT));
    }

    public function test_chat_budget_is_15_seconds_including_call_time(): void
    {
        $start = now();
        // Every attempt hangs for 5s, then 503: 5 + 1 + 5 + 3 + 1 (cut to what is left) = 15.
        $this->fakeAttempts([[5, 503], [5, 503], [5, 503]]);

        rescue(fn () => $this->chat(), report: false);

        $this->assertSame([15.0, 9.0, 1.0], $this->timeouts);
        $this->assertSame(15, (int) $start->diffInSeconds(now()));
    }

    public function test_a_slow_first_chat_attempt_is_not_retried_past_the_chat_budget(): void
    {
        $this->fakeAttempts([[14, 503], [1, 200]]);

        $this->expectException(RequestException::class);

        try {
            $this->chat();
        } finally {
            // 14s + 1s wait + 1s minimum call would pass 15s.
            $this->assertCount(1, $this->timeouts);
        }
    }

    public function test_the_same_slow_attempt_is_retried_under_the_analysis_budget(): void
    {
        $this->fakeAttempts([[14, 503], [1, 200]]);

        $this->assertSame('Halo', $this->chat(GeminiRetry::ANALYSIS)['text']);
        $this->assertCount(2, $this->timeouts);
    }

    public function test_analysis_of_60_seconds_succeeds_after_one_503_within_120_seconds(): void
    {
        $session = ConversationSession::create(['user_id' => User::factory()->create()->id, 'lang' => 'mandarin', 'scenario_key' => 'restaurant_ordering', 'started_at' => now(), 'ended_at' => now()]);
        ConversationTurn::create(['session_id' => $session->id, 'role' => 'user', 'content' => '我不有钱', 'input_mode' => 'text']);
        $this->fakeAttempts([[60, 503], [50, 200]], '{"items":[],"catatan":"Analisis selesai."}');
        $start = now();

        (new AnalyzeSessionJob($session->id))->handle(app(SessionAnalyzer::class));

        $this->assertSame('Analisis selesai.', $session->fresh()->error_analysis['catatan']);
        $this->assertSame('gemini', $session->fresh()->error_analysis['sumber']);
        // The first attempt may use the whole 120s; the second gets 120 - 60 - 1 = 59s.
        $this->assertSame([120.0, 59.0], $this->timeouts);
        $this->assertSame(111, (int) $start->diffInSeconds(now()));
    }

    public function test_camera_is_not_cut_before_30_seconds(): void
    {
        $this->useScanCatalog();
        config(['scan.enabled' => true]);
        $answer = json_encode(['status' => 'ok', 'benda_id' => 'cangkir', 'keyakinan' => 'tinggi', 'bentuk' => null, 'panduan_kamera' => null, 'catatan' => null]);
        $this->fakeAttempts([[29, 200]], $answer);

        try {
            $this->actingAs(User::factory()->create(['onboarded' => true, 'start_date' => now()->toDateString(), 'track' => 'both']))
                ->postJson(route('scan.recognize'), ['consent' => true, 'image' => self::PNG_BASE64])
                ->assertOk()
                ->assertJsonPath('jenis', 'pasti');
        } finally {
            $this->removeScanCatalog();
        }

        $this->assertSame([30.0], $this->timeouts);
    }

    public function test_camera_budget_is_honoured_separately(): void
    {
        // 20s + 1s wait leaves 9s of the 30s camera budget for the second attempt.
        $this->fakeAttempts([[20, 503], [9, 503]]);
        $send = fn (string $model, float $timeout) => Http::timeout($timeout)->post('https://generativelanguage.googleapis.com/x')->throw();

        rescue(fn () => GeminiRetry::send(GeminiRetry::SCAN, 'm', (float) config('scan.timeout'), $send), report: false);

        $this->assertSame([30.0, 9.0], $this->timeouts);
    }

    public function test_translation_is_one_plain_call_without_gemini_retry(): void
    {
        $this->fakeAttempts([[1, 503], [1, 200]]);

        $this->expectException(RequestException::class);

        try {
            $this->chat(GeminiClient::TRANSLATION);
        } finally {
            $this->assertSame([30.0], $this->timeouts);
            Sleep::assertNeverSlept();
        }
    }
}
