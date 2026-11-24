<?php

namespace Tests\Feature;

use App\Contracts\AiClient;
use App\Contracts\SessionAnalyzer;
use App\Jobs\AnalyzeSessionJob;
use App\Livewire\Conversation\Chat;
use App\Livewire\Conversation\Picker;
use App\Models\ConversationApiCall;
use App\Models\ConversationSession;
use App\Models\ConversationTurn;
use App\Services\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['start', 'finish', 'minute', 'day'] as $name) {
            RateLimiter::clear('ngobrol:'.$name.':ip:127.0.0.1');
            RateLimiter::clear('ngobrol:'.$name.':session:'.session()->getId());
        }
    }

    private function client(bool $fail = false): void
    {
        $this->app->instance(AiClient::class, new class($fail) implements AiClient
        {
            public function __construct(private bool $fail) {}

            public function isConfigured(): bool
            {
                return true;
            }

            public function sendMessage(string $model, string $system, array $messages, int $maxTokens = 200): array
            {
                if ($this->fail) {
                    throw new RuntimeException('429 high demand');
                }

                return ['text' => 'Hello!', 'tokens_in' => 1, 'tokens_out' => 1];
            }
        });
    }

    private function ownedFixture(): ConversationSession
    {
        $session = ConversationSession::create(['lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now()]);
        session()->push('ngobrol.sesi', $session->id);

        return $session;
    }

    public function test_anonymous_start_registers_only_successful_session(): void
    {
        Livewire::test(Picker::class)->set('lang', 'english')->call('selectScenario', 'job_interview')->assertRedirect();
        $session = ConversationSession::firstOrFail();
        $this->assertNull($session->user_id);
        $this->assertContains($session->id, session('ngobrol.sesi'));
        $this->assertDatabaseCount('users', 0);

        $this->client(true);
        Livewire::test(Picker::class)->set('lang', 'mandarin')->call('startRandom')->assertSet('errorMessage', 'Layanan AI sedang penuh, coba lagi sebentar.');
        $this->assertDatabaseCount('conversation_sessions', 1);
        $this->assertCount(1, session('ngobrol.sesi'));
    }

    public function test_foreign_session_is_404_on_get_and_actions(): void
    {
        $session = $this->ownedFixture();
        $this->get(route('conversation.chat', $session))->assertOk();
        $this->assertFalse(Livewire::test(Chat::class, ['session' => $session])->get('autoPlay'));
        session()->forget('ngobrol.sesi');
        $this->get(route('conversation.chat', $session))->assertNotFound();
        foreach (['sendMessage', 'finishSession', 'pollAnalysis'] as $action) {
            session()->push('ngobrol.sesi', $session->id);
            $chat = Livewire::test(Chat::class, ['session' => $session]);
            session()->forget('ngobrol.sesi');
            $chat->call($action)->assertNotFound();
        }
    }

    public function test_successful_message_counts_once_and_failure_counts_zero(): void
    {
        $session = $this->ownedFixture();
        $this->client(true);
        Livewire::test(Chat::class, ['session' => $session])->set('newMessage', 'Hello')->call('sendMessage')->assertSet('errorMessage', 'Layanan AI sedang penuh, coba lagi sebentar.');
        $this->assertDatabaseCount('conversation_turns', 0);
        $this->assertSame(50, app(ConversationService::class)->remainingMessagesToday());
        $this->client();
        Livewire::test(Chat::class, ['session' => $session])->set('newMessage', 'Hello')->call('sendMessage')->assertSet('errorMessage', null);
        $this->assertDatabaseCount('conversation_turns', 2);
        $this->assertSame(49, app(ConversationService::class)->remainingMessagesToday());
    }

    public function test_minute_and_daily_limits_reject_without_ai(): void
    {
        $this->client();
        $session = $this->ownedFixture();
        $minuteKey = 'ngobrol:minute:session:'.session()->getId();
        RateLimiter::hit($minuteKey, 60);
        for ($i = 1; $i < 10; $i++) {
            RateLimiter::hit($minuteKey, 60);
        }
        Livewire::test(Chat::class, ['session' => $session])->set('newMessage', 'Hello')->call('sendMessage')->assertSet('errorMessage', 'Terlalu banyak pesan. Coba lagi sebentar.');
        RateLimiter::clear($minuteKey);
        for ($i = 0; $i < 50; $i++) {
            RateLimiter::hit('ngobrol:day:session:'.session()->getId(), 86400);
        }
        Livewire::test(Chat::class, ['session' => $session])->set('newMessage', 'Hello')->call('sendMessage')->assertSet('errorMessage', 'Kuota harian habis, lanjut besok.');
        $this->assertDatabaseCount('conversation_turns', 0);
    }

    public function test_old_sessions_are_pruned_with_children(): void
    {
        $old = ConversationSession::create(['lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now()->subHours(25)]);
        $young = $this->ownedFixture();
        ConversationTurn::create(['session_id' => $old->id, 'role' => 'user', 'content' => 'Hi']);
        ConversationApiCall::create(['session_id' => $old->id, 'tokens_in' => 1, 'tokens_out' => 1]);
        Artisan::call('model:prune', ['--model' => ConversationSession::class]);
        $this->assertDatabaseMissing('conversation_sessions', ['id' => $old->id]);
        $this->assertDatabaseHas('conversation_sessions', ['id' => $young->id]);
        $this->assertDatabaseCount('conversation_turns', 0);
        $this->assertDatabaseCount('conversation_api_calls', 0);
    }

    public function test_start_and_finish_are_limited_per_session(): void
    {
        for ($i = 0; $i < 20; $i++) {
            RateLimiter::hit('ngobrol:start:session:'.session()->getId(), 3600);
            RateLimiter::hit('ngobrol:finish:session:'.session()->getId(), 3600);
        }
        Livewire::test(Picker::class)->set('lang', 'english')->call('selectScenario', 'job_interview')->assertStatus(429);
        $session = $this->ownedFixture();
        Livewire::test(Chat::class, ['session' => $session])->call('finishSession')->assertStatus(429);
    }

    public function test_shared_ip_has_a_looser_daily_cap_than_one_browser(): void
    {
        $this->client();
        $session = $this->ownedFixture();
        for ($i = 0; $i < 50; $i++) {
            RateLimiter::hit('ngobrol:day:session:'.session()->getId(), 86400);
        }
        $this->assertSame(0, app(ConversationService::class)->remainingMessagesToday());
        session()->migrate();
        session()->push('ngobrol.sesi', $session->id);
        $this->assertSame(50, app(ConversationService::class)->remainingMessagesToday());

        for ($i = 0; $i < 500; $i++) {
            RateLimiter::hit('ngobrol:day:ip:127.0.0.1', 86400);
        }
        $this->assertSame(0, app(ConversationService::class)->remainingMessagesToday());
    }

    public function test_delayed_analysis_job_skips_a_deleted_session(): void
    {
        $session = $this->ownedFixture();
        $session->delete();
        (new AnalyzeSessionJob($session->id))->handle(app(SessionAnalyzer::class));
        $this->assertDatabaseCount('conversation_api_calls', 0);
    }
}
