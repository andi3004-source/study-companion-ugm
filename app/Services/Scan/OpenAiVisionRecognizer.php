<?php

namespace App\Services\Scan;

use App\Contracts\ObjectRecognizer;
use App\Services\KataBicaraCatalog;
use App\Services\OpenAiStreamParser;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Vision recognizer on the OpenAI-compatible configuration
 * (services.openai_compatible.key / .vision_model / .base_url): the same
 * closed-catalog "Raba lalu dengar" contract as GeminiObjectRecognizer, but
 * the photo travels as an image_url data URL in a /chat/completions call
 * instead of Gemini inline_data. Used when AI_PROVIDER-style routing goes
 * through a local router (9router) or hosted OpenRouter and no GEMINI_API_KEY
 * is set — see the ObjectRecognizer binding in AppServiceProvider.
 *
 * The raw model reply goes through the same RecognitionNormalizer, so an
 * unknown benda_id or malformed JSON still becomes tidak_yakin, and any
 * name text the model returns is dropped in favor of the catalog.
 */
class OpenAiVisionRecognizer implements ObjectRecognizer
{
    public function __construct(
        private readonly KataBicaraCatalog $catalog,
        private readonly RecognitionNormalizer $normalizer,
    ) {}

    public function recognize(string $imageBytes, string $mime): array
    {
        $baseUrl = rtrim((string) config('services.openai_compatible.base_url'), '/');
        $key = (string) config('services.openai_compatible.key');

        if ($baseUrl === '') {
            throw new RuntimeException('OPENAI_COMPATIBLE_BASE_URL is not set; cannot call the OpenAI-compatible vision API.');
        }

        // The key is optional: truly-local routers (Ollama, llama.cpp) take
        // no key at all. The ObjectRecognizer binding still uses a filled key
        // as the opt-in signal, so put anything there for keyless endpoints.
        $request = Http::timeout((int) config('scan.timeout', 30))->acceptJson();
        if ($key !== '') {
            $request = $request->withToken($key);
        }

        $response = $request->post($baseUrl.'/chat/completions', [
            'model' => config('services.openai_compatible.vision_model', 'default'),
            'messages' => [
                ['role' => 'system', 'content' => ScanVisionPrompt::build($this->catalog)],
                ['role' => 'user', 'content' => [
                    [
                        'type' => 'image_url',
                        'image_url' => ['url' => "data:{$mime};base64,".base64_encode($imageBytes)],
                    ],
                    ['type' => 'text', 'text' => 'Benda apa yang dipegang di foto ini? Jawab hanya dengan JSON sesuai format.'],
                ]],
            ],
            'max_tokens' => 400,
            'response_format' => ['type' => 'json_object'],
            'stream' => false,
        ])->throw();

        $body = (string) $response->body();
        if (str_contains($response->header('Content-Type'), 'text/event-stream') || str_starts_with(ltrim($body), 'data:')) {
            $streamed = OpenAiStreamParser::parse($body);

            return $this->normalizer->normalize($streamed['text']);
        }

        return $this->normalizer->normalize((string) ($response->json('choices.0.message.content') ?? ''));
    }
}
