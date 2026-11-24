<?php

namespace App\Services;

use App\Contracts\AiClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper around the Gemini generateContent API. The key is read only
 * from config (backed by env), never passed to or exposed in any view/JS —
 * it travels in the x-goog-api-key header, never in the URL.
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

    private ?string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('services.gemini.key');
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
            throw new RuntimeException('GEMINI_API_KEY is not set; cannot call the Gemini API.');
        }

        $contents = array_map(fn (array $m) => [
            'role' => $m['role'] === 'assistant' ? 'model' : $m['role'],
            'parts' => [['text' => $m['content']]],
        ], $messages);

        $endpoint = self::BASE_URL.'/'.$model.':generateContent';

        return AiDailyBudget::run($maxTokens + strlen($system) + strlen(AiDailyBudget::CHILD_SAFETY) + strlen(json_encode($messages)), function () use ($endpoint, $contents, $system, $maxTokens) {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])->post($endpoint, [
                'contents' => $contents,
                'systemInstruction' => ['parts' => [['text' => $system."\n\n".AiDailyBudget::CHILD_SAFETY]]],
                'safetySettings' => array_map(fn ($category) => ['category' => $category, 'threshold' => 'BLOCK_LOW_AND_ABOVE'], [
                    'HARM_CATEGORY_HARASSMENT', 'HARM_CATEGORY_HATE_SPEECH',
                    'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'HARM_CATEGORY_DANGEROUS_CONTENT',
                ]),
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
            ])->throw();

            $data = $response->json();

            $text = ($data['promptFeedback']['blockReason'] ?? null) || ($data['candidates'][0]['finishReason'] ?? null) === 'SAFETY'
                ? AiDailyBudget::SAFE_REPLY : ($data['candidates'][0]['content']['parts'][0]['text'] ?? '');

            return [
                'text' => $text,
                'tokens_in' => $data['usageMetadata']['promptTokenCount'] ?? null,
                'tokens_out' => isset($data['usageMetadata']['totalTokenCount'], $data['usageMetadata']['promptTokenCount'])
                    ? $data['usageMetadata']['totalTokenCount'] - $data['usageMetadata']['promptTokenCount']
                    : ($data['usageMetadata']['candidatesTokenCount'] ?? null),
            ];
        });
    }
}
