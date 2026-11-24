<?php

namespace Tests\Feature;

use App\Contracts\AiClient;
use App\Livewire\Conversation\Chat;
use App\Models\ConversationSession;
use App\Models\ConversationTurn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class ConversationQuotaOnFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_ai_call_preserves_text_and_quota(): void
    {
        RateLimiter::clear('ngobrol:day:127.0.0.1');
        $session = ConversationSession::create(['lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now()]);
        session()->push('ngobrol.sesi', $session->id);
        $this->app->instance(AiClient::class, new class implements AiClient
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function sendMessage(string $model, string $system, array $messages, int $maxTokens = 200): array
            {
                throw new RuntimeException('429 high demand');
            }
        });
        Livewire::test(Chat::class, ['session' => $session])->set('newMessage', 'Hello?')->call('sendMessage')
            ->assertSet('newMessage', 'Hello?')->assertSet('errorMessage', 'Layanan AI sedang penuh, coba lagi sebentar.');
        $this->assertSame(0, ConversationTurn::count());
        $this->assertSame(0, RateLimiter::attempts('ngobrol:day:127.0.0.1'));
    }
}
