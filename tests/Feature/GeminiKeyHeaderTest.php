<?php

namespace Tests\Feature;

use App\Livewire\Conversation\Chat;
use App\Models\ConversationSession;
use App\Models\User;
use App\Services\GeminiClient;
use App\Services\GeminiMeaningTranslator;
use App\Services\GeminiSessionAnalyzer;
use App\Support\GeminiRetry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\TestCase;
use Throwable;

/** The Gemini key goes in the x-goog-api-key header only: never the URL, a log line or an error message. */
class GeminiKeyHeaderTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'AIza-kunci-rahasia-uji';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.gemini.key' => self::KEY]);
    }

    private function ok(string $text): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $text]]]]]];
    }

    private function assertKeyOnlyInHeader(): void
    {
        $requests = Http::recorded()->map(fn ($pair) => $pair[0]);
        $this->assertNotEmpty($requests);

        foreach ($requests as $request) {
            /** @var Request $request */
            $this->assertSame([self::KEY], $request->header('x-goog-api-key'));
            $this->assertStringNotContainsString(self::KEY, $request->url());
            $this->assertStringNotContainsString('key=', $request->url());
            $this->assertStringNotContainsString(self::KEY, $request->body());
        }
    }

    public function test_every_kind_of_call_sends_the_key_as_a_header(): void
    {
        // The analyzer wants an object, every other call here a one-item array.
        Http::fake(fn (Request $request) => Http::response($this->ok(
            str_contains($request->body(), 'penganalisis') ? '{"items":[],"catatan":"ok"}' : '["halo"]'
        )));

        foreach ([GeminiRetry::CHAT, GeminiRetry::ANALYSIS, GeminiClient::TRANSLATION] as $kind) {
            (new GeminiClient)->forKind($kind)->sendMessage('gemini-3.6-flash', 'sistem', [['role' => 'user', 'content' => 'Hi']]);
        }
        app(GeminiMeaningTranslator::class)->translateBatch(['hello']);
        app(GeminiSessionAnalyzer::class)->analyze('User: 你好');

        Http::assertSentCount(5);
        $this->assertKeyOnlyInHeader();
    }

    public function test_failures_keep_the_key_out_of_logs_and_messages(): void
    {
        Log::spy();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'busy']], 503)
            ->pushFailedConnection()
            ->push(['error' => ['message' => 'API key not valid']], 400)]);

        try {
            (new GeminiClient)->sendMessage('gemini-3.6-flash', 'sistem', [['role' => 'user', 'content' => 'Hi']]);
            $this->fail('Expected the 400 to be thrown.');
        } catch (Throwable $e) {
            $this->assertStringNotContainsString(self::KEY, $e->getMessage());
        }

        $this->assertKeyOnlyInHeader();
        Log::shouldHaveReceived('warning')->times(3);
        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => ! str_contains($message.json_encode($context), self::KEY))->times(3);
    }

    public function test_the_chat_error_shown_to_the_learner_has_no_key(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'busy']], 503)]);
        $user = User::factory()->create(['start_date' => now()->subDays(2)->toDateString(), 'track' => 'both', 'onboarded' => true]);
        $session = ConversationSession::create(['user_id' => $user->id, 'lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now()]);

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->set('newMessage', 'Hello')
            ->call('sendMessage')
            ->assertSet('errorMessage', 'Layanan AI sedang penuh, coba lagi sebentar.')
            ->assertDontSee(self::KEY);

        $this->assertKeyOnlyInHeader();
    }
}
