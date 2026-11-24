<?php

namespace App\Services\Scan;

use App\Contracts\ObjectRecognizer;
use App\Services\KataBicaraCatalog;
use Illuminate\Support\Facades\Http;

/**
 * Vision recognizer on the existing Gemini configuration
 * (services.gemini.key / services.gemini.model). The text-only AiClient
 * contract can't carry images, so this sends the photo itself as base64
 * inline_data. The key goes in a header, not the URL, so it can't leak
 * through connection error messages. Nothing about the image or the reply
 * is logged here.
 */
class GeminiObjectRecognizer implements ObjectRecognizer
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct(
        private readonly KataBicaraCatalog $catalog,
        private readonly RecognitionNormalizer $normalizer,
    ) {}

    public function recognize(string $imageBytes, string $mime): array
    {
        $response = Http::timeout((int) config('scan.timeout', 30))
            ->withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
            ->post(self::BASE_URL.'/'.config('services.gemini.model').':generateContent', [
                'systemInstruction' => ['parts' => [['text' => $this->systemPrompt()]]],
                'contents' => [[
                    'role' => 'user',
                    'parts' => [
                        ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($imageBytes)]],
                        ['text' => 'Benda apa yang dipegang di foto ini? Jawab hanya dengan JSON sesuai format.'],
                    ],
                ]],
                'generationConfig' => [
                    'maxOutputTokens' => 400,
                    'responseMimeType' => 'application/json',
                    // Same reason as GeminiClient: 3.x models think by default and eat the budget.
                    'thinkingConfig' => ['thinkingLevel' => 'minimal'],
                ],
            ])->throw();

        return $this->normalizer->normalize((string) ($response->json('candidates.0.content.parts.0.text') ?? ''));
    }

    public function systemPrompt(): string
    {
        return ScanVisionPrompt::build($this->catalog);
    }
}
