<?php

namespace Tests\Feature;

use App\Contracts\ObjectRecognizer;
use App\Services\Scan\GeminiObjectRecognizer;
use App\Services\Scan\NullObjectRecognizer;
use App\Services\Scan\OpenAiVisionRecognizer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\UsesScanCatalog;
use Tests\TestCase;

class OpenAiVisionRecognizerTest extends TestCase
{
    use UsesScanCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->useScanCatalog();
    }

    protected function tearDown(): void
    {
        $this->removeScanCatalog();
        parent::tearDown();
    }

    private function useOpenAiKey(): void
    {
        config([
            'services.gemini.key' => null,
            'services.openai_compatible.key' => 'router-key',
            'services.openai_compatible.vision_model' => 'vision-model-uji',
            'services.openai_compatible.base_url' => 'http://router-uji:20128/v1',
        ]);
    }

    public function test_binding_with_openai_key_is_the_openai_vision_recognizer(): void
    {
        $this->useOpenAiKey();

        $this->assertInstanceOf(OpenAiVisionRecognizer::class, app(ObjectRecognizer::class));
    }

    public function test_gemini_key_still_wins_when_both_are_set(): void
    {
        $this->useOpenAiKey();
        config(['services.gemini.key' => 'gemini-key']);

        $this->assertInstanceOf(GeminiObjectRecognizer::class, app(ObjectRecognizer::class));
    }

    public function test_no_keys_is_still_the_null_recognizer(): void
    {
        config(['services.gemini.key' => null, 'services.openai_compatible.key' => null]);

        $this->assertInstanceOf(NullObjectRecognizer::class, app(ObjectRecognizer::class));
    }

    public function test_sends_the_image_as_a_data_url_with_bearer_key(): void
    {
        $this->useOpenAiKey();
        Http::fake(['router-uji:20128/*' => Http::response([
            'choices' => [['message' => ['content' => '{"status":"ok","benda_id":"cangkir","keyakinan":"tinggi","bentuk":"Bulat"}']]],
        ], 200)]);

        $result = app(OpenAiVisionRecognizer::class)->recognize('BYTES-GAMBAR', 'image/png');

        $this->assertSame('cangkir', $result['benda_id']);
        Http::assertSent(function (Request $request) {
            $content = $request['messages'][1]['content'];

            return str_contains($request->url(), 'router-uji:20128/v1/chat/completions')
                && ! str_contains($request->url(), 'router-key')
                && $request->hasHeader('Authorization', 'Bearer router-key')
                && $request['model'] === 'vision-model-uji'
                && $request['messages'][0]['role'] === 'system'
                && $content[0]['image_url']['url'] === 'data:image/png;base64,'.base64_encode('BYTES-GAMBAR');
        });
    }

    public function test_streamed_reply_is_stitched_before_normalizing(): void
    {
        $this->useOpenAiKey();
        Http::fake(['router-uji:20128/*' => Http::response(
            "data: {\"choices\":[{\"delta\":{\"content\":\"{\\\"status\\\":\\\"ok\\\",\\\"benda_id\\\":\\\"sendok\\\"\"}}]}\n\n"
            ."data: {\"choices\":[{\"delta\":{\"content\":\",\\\"keyakinan\\\":\\\"sedang\\\"}\"}}]}\n\n"
            ."data: [DONE]\n",
            200, ['Content-Type' => 'text/event-stream']
        )]);

        $result = app(OpenAiVisionRecognizer::class)->recognize('x', 'image/jpeg');

        $this->assertSame('ok', $result['status']);
        $this->assertSame('sendok', $result['benda_id']);
    }

    public function test_missing_key_sends_without_authorization_header(): void
    {
        config(['services.gemini.key' => null, 'services.openai_compatible.key' => null]);
        config(['services.openai_compatible.base_url' => 'http://router-uji:20128/v1']);
        Http::fake(['router-uji:20128/*' => Http::response([
            'choices' => [['message' => ['content' => '{"status":"tidak_yakin","keyakinan":"rendah"}']]],
        ], 200)]);

        $result = app(OpenAiVisionRecognizer::class)->recognize('x', 'image/jpeg');

        $this->assertSame('tidak_yakin', $result['status']);
        Http::assertSent(fn (Request $request) => ! $request->hasHeader('Authorization'));
    }

    public function test_missing_base_url_throws_without_calling_http(): void
    {
        config(['services.gemini.key' => null, 'services.openai_compatible.key' => 'k']);
        config(['services.openai_compatible.base_url' => null]);
        Http::fake();

        $this->expectException(\RuntimeException::class);

        try {
            app(OpenAiVisionRecognizer::class)->recognize('x', 'image/jpeg');
        } finally {
            Http::assertNothingSent();
        }
    }
}
