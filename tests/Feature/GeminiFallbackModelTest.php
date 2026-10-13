<?php

namespace Tests\Feature;

use App\Services\GeminiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class GeminiFallbackModelTest extends TestCase
{
    private const MAIN = 'gemini-3.6-flash';

    private const SPARE = 'model-cadangan-uji';

    /** @var string[] model of every request, in order */
    private array $models = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.gemini.key' => 'test-key']);
    }

    /** @param  array<string, int|string>  $statusByModel  'ok', 'connection' or an HTTP status */
    private function fakeModels(array $statusByModel): void
    {
        Http::fake(function (Request $request) use ($statusByModel) {
            preg_match('#/models/([^:]+):generateContent#', $request->url(), $m);
            $this->models[] = $m[1];
            $outcome = $statusByModel[$m[1]];

            if ($outcome === 'connection') {
                throw new ConnectionException('cURL error 28');
            }

            return $outcome === 'ok'
                ? Http::response(['candidates' => [['content' => ['parts' => [['text' => 'dari '.$m[1]]]]]]])
                : Http::response([], $outcome);
        });
    }

    private function chat(): array
    {
        return (new GeminiClient)->sendMessage(self::MAIN, 'sistem', [['role' => 'user', 'content' => 'Hi']]);
    }

    public function test_fallback_list_is_empty_by_default_so_only_the_main_model_is_tried(): void
    {
        $this->assertSame([], config('gemini.fallback_models'));
        $this->fakeModels([self::MAIN => 503]);

        rescue(fn () => $this->chat(), report: false);

        $this->assertSame([self::MAIN, self::MAIN, self::MAIN], $this->models);
    }

    public function test_a_configured_fallback_is_used_after_the_main_model_stays_503(): void
    {
        config(['gemini.fallback_models' => [self::SPARE]]);
        $this->fakeModels([self::MAIN => 503, self::SPARE => 'ok']);

        $this->assertSame('dari '.self::SPARE, $this->chat()['text']);
        $this->assertSame([self::MAIN, self::MAIN, self::MAIN, self::SPARE], $this->models);
    }

    public function test_the_fallback_is_not_used_when_the_main_model_answers(): void
    {
        config(['gemini.fallback_models' => [self::SPARE]]);
        $this->fakeModels([self::MAIN => 'ok', self::SPARE => 'ok']);

        $this->assertSame('dari '.self::MAIN, $this->chat()['text']);
        $this->assertSame([self::MAIN], $this->models);
    }

    public function test_the_fallback_is_not_used_for_non_503_failures(): void
    {
        config(['gemini.fallback_models' => [self::SPARE]]);
        $status = null;
        Http::fake(function (Request $request) use (&$status) {
            $this->models[] = str_contains($request->url(), self::SPARE) ? self::SPARE : self::MAIN;

            return Http::response([], $status);
        });

        foreach ([400, 403, 404, 429] as $status) {
            $this->models = [];

            rescue(fn () => $this->chat(), report: false);

            $this->assertSame([self::MAIN], $this->models, "HTTP {$status}");
        }
    }

    public function test_the_fallback_is_not_used_after_connection_errors(): void
    {
        config(['gemini.fallback_models' => [self::SPARE]]);
        $this->fakeModels([self::MAIN => 'connection', self::SPARE => 'ok']);

        $this->expectException(ConnectionException::class);

        try {
            $this->chat();
        } finally {
            $this->assertSame([self::MAIN, self::MAIN, self::MAIN], $this->models);
        }
    }

    public function test_the_fallback_follows_the_same_retry_rules_and_list_order(): void
    {
        config(['gemini.fallback_models' => [self::SPARE, 'model-ketiga'], 'gemini.retry_budget_seconds' => 60]);
        $this->fakeModels([self::MAIN => 503, self::SPARE => 503, 'model-ketiga' => 'ok']);

        $this->assertSame('dari model-ketiga', $this->chat()['text']);
        $this->assertSame([self::MAIN, self::MAIN, self::MAIN, self::SPARE, self::SPARE, self::SPARE, 'model-ketiga'], $this->models);
    }

    public function test_the_fallback_shares_the_main_models_time_budget(): void
    {
        $this->freezeTime();
        config(['gemini.fallback_models' => [self::SPARE], 'gemini.retry_jitter_ms' => 0, 'gemini.retry_budgets.chat' => 12]);
        $start = now();
        // Each call "takes" 2 seconds: main 2+1+2+3+2 = 10s, leaving 2s for the spare.
        Http::fake(function (Request $request) {
            preg_match('#/models/([^:]+):generateContent#', $request->url(), $m);
            $this->models[] = $m[1];
            $this->travel(2)->seconds();

            return Http::response([], 503);
        });

        try {
            $this->chat();
            $this->fail('Expected a 503.');
        } catch (RequestException $e) {
            $this->assertSame(503, $e->response->status());
        }

        $this->assertSame([self::MAIN, self::MAIN, self::MAIN, self::SPARE], $this->models);
        $this->assertLessThanOrEqual(12, $start->diffInSeconds(now()));
    }

    public function test_the_log_names_the_model_of_each_attempt(): void
    {
        Log::spy();
        config(['gemini.fallback_models' => [self::SPARE]]);
        $this->fakeModels([self::MAIN => 503, self::SPARE => 'ok']);

        $this->chat();

        foreach ([1, 2, 3] as $attempt) {
            Log::shouldHaveReceived('warning')->with('gemini: percobaan gagal', [
                'error' => RequestException::class, 'status' => 503, 'percobaan' => $attempt, 'model' => self::MAIN,
            ])->once();
        }
        Log::shouldHaveReceived('warning')->times(3);
    }
}
