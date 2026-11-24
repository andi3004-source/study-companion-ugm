<?php

namespace Tests\Feature;

use App\Livewire\Conversation\Chat;
use App\Livewire\Conversation\Picker;
use App\Models\ConversationSession;
use App\Services\AiBudgetExceeded;
use App\Services\AiDailyBudget;
use App\Services\AnthropicClient;
use App\Services\GeminiClient;
use App\Services\LangflowSessionAnalyzer;
use App\Services\OpenAiCompatibleClient;
use App\Support\SecretRedactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AiSecurityBudgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_budget_stops_gemini_before_http_and_chat_announces_limit(): void
    {
        config(['services.gemini.key' => 'test-key', 'conversation.ai_daily_token_budget' => 10]);
        Http::fake();
        $session = ConversationSession::create(['lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now()]);
        session()->push('ngobrol.sesi', $session->id);

        Livewire::test(Chat::class, ['session' => $session])->set('newMessage', 'Hello')->call('sendMessage')
            ->assertSet('statusMessage', AiDailyBudget::MESSAGE);
        Http::assertNothingSent();
        $this->assertDatabaseCount('conversation_turns', 0);
    }

    public function test_budget_counts_provider_usage_globally_and_unknown_usage_as_a_call(): void
    {
        config(['conversation.ai_daily_token_budget' => 1500]);
        $this->assertSame(['tokens_in' => 400, 'tokens_out' => 100], AiDailyBudget::run(500, fn () => ['tokens_in' => 400, 'tokens_out' => 100]));
        AiDailyBudget::run(1000, fn () => ['text' => 'no usage']);
        $this->expectException(AiBudgetExceeded::class);
        AiDailyBudget::run(1, fn () => $this->fail('Provider called after global limit'));
    }

    public function test_random_opener_is_stopped_by_the_same_budget(): void
    {
        config(['services.gemini.key' => 'test-key', 'conversation.ai_daily_token_budget' => 10]);
        Http::fake();
        Livewire::test(Picker::class)->set('lang', 'mandarin')->call('startRandom')
            ->assertSet('errorMessage', AiDailyBudget::MESSAGE);
        Http::assertNothingSent();
        $this->assertDatabaseCount('conversation_sessions', 0);
    }

    public function test_langflow_analysis_uses_the_global_budget(): void
    {
        config([
            'conversation.ai_daily_token_budget' => 10,
            'services.langflow.url' => 'https://langflow.test',
            'services.langflow.flow_id' => 'flow-123',
        ]);
        Http::fake();
        $this->expectException(AiBudgetExceeded::class);
        try {
            app(LangflowSessionAnalyzer::class)->analyze('User: 你好');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_gemini_key_safety_and_blocked_reply(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['finishReason' => 'SAFETY']]])]);
        $result = (new GeminiClient('test-key'))->sendMessage('gemini-test', 'Tutor', [['role' => 'user', 'content' => 'Hai']]);

        $this->assertSame('Maaf, ayo pilih topik belajar yang aman. Mau cerita tentang kegiatan hari ini?', $result['text']);
        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'key=')
            && $request->header('x-goog-api-key') === ['test-key']
            && count($request['safetySettings']) === 4
            && collect($request['safetySettings'])->every(fn ($setting) => $setting['threshold'] === 'BLOCK_LOW_AND_ABOVE')
            && str_contains($request['systemInstruction']['parts'][0]['text'], 'anak'));
    }

    public function test_gemini_counts_thinking_tokens_when_provider_reports_total(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Halo!']]]]],
            'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 2, 'totalTokenCount' => 42],
        ])]);
        $result = (new GeminiClient('test-key'))->sendMessage('gemini-test', 'Tutor', []);
        $this->assertSame(32, $result['tokens_out']);
    }

    public function test_other_clients_receive_child_safety_and_filter_blocked_reply(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Terblokir']], 'stop_reason' => 'refusal']),
            '127.0.0.1:20128/*' => Http::response(['choices' => [['finish_reason' => 'content_filter']]]),
        ]);
        $this->assertStringContainsString('topik belajar yang aman', (new AnthropicClient('test-key'))->sendMessage('m', 'Tutor', [])['text']);
        $this->assertStringContainsString('topik belajar yang aman', (new OpenAiCompatibleClient('test-key', 'm', 'http://127.0.0.1:20128/v1'))->sendMessage('m', 'Tutor', [])['text']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com') && str_contains($request['system'], 'anak'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '127.0.0.1') && str_contains($request['messages'][0]['content'], 'anak'));
    }

    public function test_openai_http_content_filter_is_a_friendly_reply(): void
    {
        Http::fake(['127.0.0.1:20128/*' => Http::response(['error' => ['code' => 'content_filter']], 400)]);
        $result = (new OpenAiCompatibleClient('test-key', 'm', 'http://127.0.0.1:20128/v1'))->sendMessage('m', 'Tutor', []);
        $this->assertSame(AiDailyBudget::SAFE_REPLY, $result['text']);
    }

    public function test_configured_secrets_are_redacted_and_remote_http_is_rejected(): void
    {
        config(['services.anthropic.key' => 'anthropic-secret', 'services.openai_compatible.key' => 'router-secret']);
        $this->assertSame('[REDACTED] [REDACTED]', SecretRedactor::redact('anthropic-secret router-secret'));
        Http::fake();
        $this->expectException(\RuntimeException::class);
        try {
            (new OpenAiCompatibleClient('key', 'm', 'http://remote.example/v1'))->sendMessage('m', 's', []);
        } finally {
            Http::assertNothingSent();
        }
    }
}
