<?php

namespace App\Services;

use App\Contracts\AiClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper around any OpenAI-compatible /chat/completions endpoint
 * (local routers like 9router, LiteLLM, Ollama, llama.cpp server, vLLM,
 * or hosted OpenRouter). The key, model, and base URL are read only from
 * config (backed by env), never passed to or exposed in any view/JS.
 *
 * Implements the same AiClient contract as GeminiClient/AnthropicClient so
 * ConversationService's rate-limit/history/system-prompt logic never has to
 * know which provider is behind it — only the wire format differs here:
 * the system prompt is sent as a {role: 'system'} message, 'assistant'
 * needs no renaming, and the reply/usage live under choices[0].message
 * and usage (prompt_tokens/completion_tokens).
 */
class OpenAiCompatibleClient implements AiClient
{
    private ?string $apiKey;

    private string $model;

    private string $baseUrl;

    public function __construct(?string $apiKey = null, ?string $model = null, ?string $baseUrl = null)
    {
        $this->apiKey = $apiKey ?? config('services.openai_compatible.key');
        $this->model = $model ?? config('services.openai_compatible.model', 'default');
        $this->baseUrl = rtrim($baseUrl ?? config('services.openai_compatible.base_url', ''), '/');
    }

    public function isConfigured(): bool
    {
        return filled($this->baseUrl);
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{text: string, tokens_in: ?int, tokens_out: ?int}
     */
    public function sendMessage(string $model, string $system, array $messages, int $maxTokens = 200): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('OPENAI_COMPATIBLE_API_KEY / OPENAI_COMPATIBLE_BASE_URL is not set; cannot call the OpenAI-compatible API.');
        }

        $parts = parse_url($this->baseUrl);
        $local = in_array($parts['host'] ?? null, ['127.0.0.1', 'localhost'], true);
        if (! is_array($parts) || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || ($parts['scheme'] !== 'https' && (app()->environment('production') || ! $local))) {
            throw new RuntimeException('OPENAI_COMPATIBLE_BASE_URL must use HTTPS (localhost is allowed outside production).');
        }

        return AiDailyBudget::run($maxTokens + strlen($system) + strlen(AiDailyBudget::CHILD_SAFETY) + strlen(json_encode($messages)), function () use ($model, $system, $messages, $maxTokens) {

            $payload = [
                'model' => $model !== '' ? $model : $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $system."\n\n".AiDailyBudget::CHILD_SAFETY],
                    ...array_map(fn (array $m) => [
                        'role' => $m['role'],
                        'content' => $m['content'],
                    ], $messages),
                ],
                'max_tokens' => $maxTokens,
                'stream' => false,
            ];

            $request = Http::acceptJson();
            if (filled($this->apiKey)) {
                $request = $request->withToken($this->apiKey);
            }

            $response = $request->post($this->baseUrl.'/chat/completions', $payload);
            if ($response->status() === 400 && ($response->json('error.code') === 'content_filter' || $response->json('error.type') === 'content_filter')) {
                return ['text' => AiDailyBudget::SAFE_REPLY, 'tokens_in' => null, 'tokens_out' => null];
            }
            $response->throw();

            // Some routers (e.g. 9router) answer with server-sent events even when
            // stream:false was requested — a "data: {...}" chunk per line instead
            // of one JSON object. Detect and stitch those together.
            $body = (string) $response->body();
            if (str_contains($response->header('Content-Type'), 'text/event-stream') || str_starts_with(ltrim($body), 'data:')) {
                return OpenAiStreamParser::parse($body);
            }

            $data = $response->json();

            $text = ($data['choices'][0]['finish_reason'] ?? null) === 'content_filter'
                ? AiDailyBudget::SAFE_REPLY : ($data['choices'][0]['message']['content'] ?? '');

            return [
                'text' => is_string($text) ? $text : '',
                'tokens_in' => $data['usage']['prompt_tokens'] ?? null,
                'tokens_out' => $data['usage']['completion_tokens'] ?? null,
            ];
        });
    }
}
