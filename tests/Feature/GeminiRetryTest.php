<?php

namespace Tests\Feature;

use App\Livewire\Conversation\Chat;
use App\Models\ConversationSession;
use App\Models\User;
use App\Services\GeminiClient;
use App\Support\AiErrorMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Livewire\Livewire;
use Tests\Concerns\UsesScanCatalog;
use Tests\TestCase;

class GeminiRetryTest extends TestCase
{
    use RefreshDatabase;
    use UsesScanCatalog;

    private const KEY = 'kunci-rahasia-uji';

    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.gemini.key' => self::KEY]);
    }

    private function ok(string $text = 'Hello!'): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $text]]]]], 'usageMetadata' => ['promptTokenCount' => 3, 'candidatesTokenCount' => 2]];
    }

    private function chat(): array
    {
        return (new GeminiClient)->sendMessage('gemini-3.6-flash', 'sistem', [['role' => 'user', 'content' => 'Hi']]);
    }

    private function onboardedUser(): User
    {
        return User::factory()->create(['start_date' => now()->subDays(2)->toDateString(), 'track' => 'both', 'onboarded' => true]);
    }

    public function test_defaults_are_pinned(): void
    {
        $this->assertSame(2, config('gemini.retry_max'));
        $this->assertSame([1.0, 3.0], config('gemini.retry_delays_seconds'));
        $this->assertSame(250, config('gemini.retry_jitter_ms'));
        $this->assertSame(12.0, config('gemini.retry_budget_seconds'));
    }

    public function test_a_503_then_200_succeeds_after_one_retry(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['code' => 503]], 503)
            ->push($this->ok('Hello!'))]);

        $this->assertSame('Hello!', $this->chat()['text']);
        Http::assertSentCount(2);
        Sleep::assertSleptTimes(1);
    }

    public function test_waits_one_then_three_seconds_plus_small_jitter(): void
    {
        config(['gemini.retry_jitter_ms' => 0]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 503)]);

        try {
            $this->chat();
            $this->fail('Expected the last 503 to be thrown.');
        } catch (RequestException $e) {
            $this->assertSame(503, $e->response->status());
        }

        Http::assertSentCount(3);
        Sleep::assertSequence([Sleep::for(1000)->milliseconds(), Sleep::for(3000)->milliseconds()]);
    }

    public function test_jitter_stays_small(): void
    {
        $this->freezeTime();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 503)]);
        $start = now();

        rescue(fn () => $this->chat(), report: false);

        // Two waits (1s and 3s) plus at most 250ms jitter each.
        $slept = $start->diffInMilliseconds(now());
        $this->assertGreaterThanOrEqual(4000, $slept);
        $this->assertLessThanOrEqual(4500, $slept);
    }

    public function test_three_503s_in_chat_show_the_existing_busy_message(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['status' => 'UNAVAILABLE']], 503)]);
        $user = $this->onboardedUser();
        $session = ConversationSession::create(['user_id' => $user->id, 'lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now()]);

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->set('newMessage', 'Hello there')
            ->call('sendMessage')
            ->assertOk()
            ->assertSet('errorMessage', AiErrorMessage::BUSY)
            ->assertSet('newMessage', 'Hello there')
            ->assertSee('Layanan AI sedang penuh, coba lagi sebentar.');

        Http::assertSentCount(3);
    }

    public function test_400_403_404_and_429_are_not_retried(): void
    {
        $status = null;
        $calls = 0;
        Http::fake(function () use (&$calls, &$status) {
            $calls++;

            return Http::response([], $status);
        });

        foreach ([400, 403, 404, 429] as $status) {
            $calls = 0;

            try {
                $this->chat();
                $this->fail("HTTP {$status} should be thrown.");
            } catch (RequestException $e) {
                $this->assertSame($status, $e->response->status());
            }

            $this->assertSame(1, $calls, "HTTP {$status} must be sent once");
        }

        Sleep::assertNeverSlept();
    }

    public function test_other_5xx_are_not_retried(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            return Http::response([], 500);
        });

        rescue(fn () => $this->chat(), report: false);

        $this->assertSame(1, $calls);
    }

    public function test_connection_error_or_timeout_is_retried(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->pushFailedConnection('cURL error 28: Operation timed out')
            ->pushFailedConnection('cURL error 7: Failed to connect')
            ->push($this->ok('Recovered'))]);

        $this->assertSame('Recovered', $this->chat()['text']);
        Http::assertSentCount(3);
    }

    public function test_connection_errors_on_every_attempt_rethrow_after_three(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        $this->expectException(ConnectionException::class);

        try {
            $this->chat();
        } finally {
            $this->assertSame(3, $calls);
        }
    }

    public function test_the_time_budget_is_respected_including_call_time(): void
    {
        $this->freezeTime();
        config(['gemini.retry_jitter_ms' => 0, 'gemini.retry_budgets.chat' => 12]);
        $start = now();
        $timeouts = [];
        $calls = 0;
        // Each call "takes" 5 seconds before answering 503.
        Http::fake(function (Request $request, array $options) use (&$calls, &$timeouts) {
            $calls++;
            $timeouts[] = $options['timeout'];
            $this->travel(5)->seconds();

            return Http::response([], 503);
        });

        rescue(fn () => $this->chat(), report: false);

        // 5s call + 1s wait + 5s call = 11s; a 3s wait plus another call cannot fit in 12s.
        $this->assertSame(2, $calls);
        $this->assertLessThanOrEqual(12, $start->diffInSeconds(now()));
        // Each attempt's timeout is cut to what is left of the budget.
        $this->assertSame([12.0, 6.0], array_map('floatval', $timeouts));
    }

    public function test_retry_count_comes_from_config(): void
    {
        config(['gemini.retry_max' => 0]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 503)]);

        rescue(fn () => $this->chat(), report: false);

        Http::assertSentCount(1);
    }

    public function test_log_holds_only_error_class_status_attempt_and_model(): void
    {
        Log::spy();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'This model is currently experiencing high demand.']], 503)
            ->pushFailedConnection('cURL error 28 https://generativelanguage.googleapis.com/?key='.self::KEY)
            ->push($this->ok())]);

        (new GeminiClient)->sendMessage('gemini-3.6-flash', 'prompt sistem rahasia', [['role' => 'user', 'content' => 'pesan pengguna rahasia']]);

        Log::shouldHaveReceived('warning')->with('gemini: percobaan gagal', [
            'error' => RequestException::class, 'status' => 503, 'percobaan' => 1, 'model' => 'gemini-3.6-flash',
        ])->once();
        Log::shouldHaveReceived('warning')->with('gemini: percobaan gagal', [
            'error' => ConnectionException::class, 'status' => null, 'percobaan' => 2, 'model' => 'gemini-3.6-flash',
        ])->once();
        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context) {
            $logged = $message.json_encode($context);

            return array_keys($context) === ['error', 'status', 'percobaan', 'model']
                && ! str_contains($logged, self::KEY)
                && ! str_contains($logged, 'high demand')
                && ! str_contains($logged, 'rahasia');
        })->twice();
    }

    public function test_scan_retries_a_503_with_the_same_in_memory_photo_and_logs_no_image(): void
    {
        Log::spy();
        $this->useScanCatalog();
        config(['scan.enabled' => true]);
        $answer = json_encode(['status' => 'ok', 'benda_id' => 'cangkir', 'keyakinan' => 'tinggi', 'bentuk' => null, 'panduan_kamera' => null, 'catatan' => null]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push([], 503)
            ->push($this->ok($answer))]);

        try {
            $this->actingAs($this->onboardedUser())
                ->postJson(route('scan.recognize'), ['consent' => true, 'image' => self::PNG_BASE64])
                ->assertOk()
                ->assertJsonPath('jenis', 'pasti');
        } finally {
            $this->removeScanCatalog();
        }

        $sent = Http::recorded()->map(fn ($pair) => $pair[0]);
        $this->assertCount(2, $sent);
        foreach ($sent as $request) {
            $this->assertSame(self::PNG_BASE64, $request['contents'][0]['parts'][0]['inline_data']['data']);
            $this->assertSame(self::KEY, $request->header('x-goog-api-key')[0]);
            $this->assertStringNotContainsString(self::KEY, $request->url());
        }

        // The only warning is the failed first attempt: class, status, attempt, model. No image, no key.
        Log::shouldHaveReceived('warning')->once();
        Log::shouldHaveReceived('warning')->with('gemini: percobaan gagal', ['error' => RequestException::class, 'status' => 503, 'percobaan' => 1, 'model' => 'gemini-3.6-flash'])->once();
    }

    public function test_scan_503_on_every_attempt_is_the_existing_friendly_503(): void
    {
        $this->useScanCatalog();
        config(['scan.enabled' => true]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 503)]);

        try {
            $this->actingAs($this->onboardedUser())
                ->postJson(route('scan.recognize'), ['consent' => true, 'image' => self::PNG_BASE64])
                ->assertStatus(503)
                ->assertJsonPath('pesan', AiErrorMessage::BUSY);
        } finally {
            $this->removeScanCatalog();
        }

        Http::assertSentCount(3);
    }
}
