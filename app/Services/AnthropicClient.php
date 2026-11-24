<?php

namespace App\Services;

use App\Contracts\AiClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper around the Claude Messages API. The key is read only from
 * config (backed by env), never passed to or exposed in any view/JS.
 *
 * Selected through AI_PROVIDER=anthropic in AppServiceProvider.
 */
class AnthropicClient implements AiClient
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2023-06-01';

    private ?string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('services.anthropic.key');
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{text: string, tokens_in: ?int, tokens_out: ?int}
     */
    public function sendMessage(string $model, string $system, array $messages, int $maxTokens = 200): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('ANTHROPIC_API_KEY is not set; cannot call the Claude API.');
        }

        return AiDailyBudget::run($maxTokens + strlen($system) + strlen(AiDailyBudget::CHILD_SAFETY) + strlen(json_encode($messages)), function () use ($model, $system, $messages, $maxTokens) {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
            ])->post(self::ENDPOINT, [
                'model' => $model,
                'system' => $system."\n\n".AiDailyBudget::CHILD_SAFETY,
                'messages' => $messages,
                'max_tokens' => $maxTokens,
            ])->throw();

            $data = $response->json();

            $text = ($data['stop_reason'] ?? null) === 'refusal'
                ? AiDailyBudget::SAFE_REPLY
                : (collect($data['content'] ?? [])->firstWhere('type', 'text')['text'] ?? '');

            return [
                'text' => $text,
                'tokens_in' => $data['usage']['input_tokens'] ?? null,
                'tokens_out' => $data['usage']['output_tokens'] ?? null,
            ];
        });
    }
}
