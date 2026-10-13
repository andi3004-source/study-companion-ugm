<?php

namespace App\Services;

use App\Contracts\AiClient;
use App\Support\GeminiRetry;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper around the Gemini generateContent API. The key is read only
 * from config (backed by env), never passed to or exposed in any view/JS.
 * It travels in the x-goog-api-key header, never in the URL, so it can't
 * leak through connection error messages (which quote the URL) or logs.
 *
 * Implements the same AiClient contract as AnthropicClient so
 * ConversationService's rate-limit/history/system-prompt logic never has to
 * know which provider is behind it — only the wire format differs here:
 * role naming ('assistant' -> 'model'), system prompt as a separate
 * systemInstruction field, messages wrapped in parts[], and the response
 * shape under candidates[0].content.parts[0].text.
 */
class GeminiClient implements AiClient
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    /** Meaning translations: one plain call, no GeminiRetry (GeminiMeaningTranslator retries on its own). */
    public const TRANSLATION = 'translation';

    /** Per-attempt timeout for chat and translations, the HTTP client's own default. */
    private const TIMEOUT_SECONDS = 30;

    private ?string $apiKey;

    /** GeminiRetry::CHAT, ::ANALYSIS or self::TRANSLATION; see forKind(). */
    private string $kind = GeminiRetry::CHAT;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('services.gemini.key');
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * A copy for another kind of call, which picks the GeminiRetry time budget.
     * Chat is the default; analysis waits per attempt for whatever is left of
     * its budget; translation skips GeminiRetry so retries never stack.
     */
    public function forKind(string $kind): static
    {
        $clone = clone $this;
        $clone->kind = $kind;

        return $clone;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{text: string, tokens_in: ?int, tokens_out: ?int}
     */
    public function sendMessage(string $model, string $system, array $messages, int $maxTokens = 200): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('GEMINI_API_KEY is not set; cannot call the Gemini API.');
        }

        $contents = array_map(fn (array $m) => [
            'role' => $m['role'] === 'assistant' ? 'model' : $m['role'],
            'parts' => [['text' => $m['content']]],
        ], $messages);

        $body = [
            'contents' => $contents,
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'generationConfig' => [
                'maxOutputTokens' => $maxTokens,
                // gemini-3.x models "think" by default, and maxOutputTokens caps
                // thinking + visible output combined — without this, replies were
                // silently coming back empty (finishReason: MAX_TOKENS) because the
                // whole budget went to internal reasoning. 'minimal' is the lowest
                // level 3.x exposes (there's no full off-switch, unlike 2.x's
                // thinkingBudget: 0). Confirmed against a live API call 2026-09-10.
                'thinkingConfig' => ['thinkingLevel' => 'minimal'],
            ],
        ];

        $send = fn (string $model, float $timeout) => Http::timeout($timeout)
            ->withHeaders(['x-goog-api-key' => (string) $this->apiKey])
            ->post(self::BASE_URL.'/'.$model.':generateContent', $body)
            ->throw();

        $response = match ($this->kind) {
            self::TRANSLATION => $send($model, self::TIMEOUT_SECONDS),
            GeminiRetry::ANALYSIS => GeminiRetry::send($this->kind, $model, INF, $send),
            default => GeminiRetry::send($this->kind, $model, self::TIMEOUT_SECONDS, $send),
        };

        $data = $response->json();

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return [
            'text' => $text,
            'tokens_in' => $data['usageMetadata']['promptTokenCount'] ?? null,
            'tokens_out' => $data['usageMetadata']['candidatesTokenCount'] ?? null,
        ];
    }
}
