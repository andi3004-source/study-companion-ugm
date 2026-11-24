<?php

namespace Tests\Feature;

use App\Contracts\AiClient;
use App\Providers\AppServiceProvider;
use App\Services\OpenAiCompatibleClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenAiCompatibleClientTest extends TestCase
{
    private function configure(): void
    {
        config([
            'services.openai_compatible.key' => 'test-key',
            'services.openai_compatible.model' => 'test-model',
            'services.openai_compatible.base_url' => 'http://127.0.0.1:20128/v1',
        ]);
    }

    public function test_sends_system_and_messages_to_chat_completions(): void
    {
        $this->configure();
        Http::fake([
            '127.0.0.1:20128/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'Halo!']]],
                'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 3],
            ], 200),
        ]);

        $result = (new OpenAiCompatibleClient)->sendMessage(
            'test-model', 'Kamu tutor.', [['role' => 'user', 'content' => 'Hai']]
        );

        $this->assertSame('Halo!', $result['text']);
        $this->assertSame(12, $result['tokens_in']);
        $this->assertSame(3, $result['tokens_out']);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://127.0.0.1:20128/v1/chat/completions'
                && $request->header('Authorization') === ['Bearer test-key']
                && $request['model'] === 'test-model'
                && $request['max_tokens'] === 200
                && $request['messages'] === [
                    ['role' => 'system', 'content' => 'Kamu tutor.'],
                    ['role' => 'user', 'content' => 'Hai'],
                ];
        });
    }

    public function test_streams_are_stitched_into_one_reply(): void
    {
        $this->configure();
        $sse = "data: {\"choices\":[{\"delta\":{\"role\":\"assistant\"}}]}\n\n"
            ."data: {\"choices\":[{\"delta\":{\"content\":\"nyam\"}}]}\n\n"
            ."data: {\"choices\":[{\"delta\":{\"content\":\"bung\"}}],\"usage\":{\"prompt_tokens\":10,\"completion_tokens\":2}}\n\n"
            ."data: [DONE]\n";
        Http::fake([
            '127.0.0.1:20128/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $result = (new OpenAiCompatibleClient)->sendMessage('test-model', 's', []);

        $this->assertSame('nyambung', $result['text']);
        $this->assertSame(10, $result['tokens_in']);
        $this->assertSame(2, $result['tokens_out']);
    }

    public function test_missing_key_allows_unauthenticated_request(): void
    {
        config(['services.openai_compatible.key' => null]);
        config(['services.openai_compatible.base_url' => 'http://127.0.0.1:20128/v1']);
        Http::fake([
            '127.0.0.1:20128/*' => Http::response([
                'choices' => [['message' => ['content' => 'Local reply']]],
            ], 200),
        ]);

        $result = (new OpenAiCompatibleClient)->sendMessage('m', 's', []);

        $this->assertSame('Local reply', $result['text']);
        Http::assertSent(function ($request) {
            return $request->url() === 'http://127.0.0.1:20128/v1/chat/completions'
                && $request->header('Authorization') === [];
        });
    }

    public function test_missing_base_url_throws_without_calling_http(): void
    {
        config(['services.openai_compatible.key' => 'test-key']);
        config(['services.openai_compatible.base_url' => null]);
        Http::fake();

        $this->expectException(RuntimeException::class);

        try {
            (new OpenAiCompatibleClient)->sendMessage('m', 's', []);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_provider_switch_binds_openai_compatible_client_and_model(): void
    {
        config([
            'services.ai_provider' => 'openai_compatible',
            'services.openai_compatible.model' => 'router-model',
        ]);
        (new AppServiceProvider($this->app))->register();

        $this->assertInstanceOf(OpenAiCompatibleClient::class, $this->app->make(AiClient::class));
        $this->assertSame('router-model', config('services.active_ai_model'));
    }

    public function test_unknown_provider_falls_back_to_gemini(): void
    {
        config(['services.ai_provider' => 'whatever']);
        (new AppServiceProvider($this->app))->register();

        $this->assertSame('gemini-3.6-flash', config('services.active_ai_model'));
    }
}
