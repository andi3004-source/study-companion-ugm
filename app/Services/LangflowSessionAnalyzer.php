<?php

namespace App\Services;

use App\Contracts\SessionAnalyzer;
use App\Exceptions\LangflowRequestException;
use App\Support\ErrorAnalysis;
use App\Support\SecretRedactor;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Error analysis through an IBM Langflow flow, called the way Langflow's
 * "API access" panel shows: POST {url}/api/v1/run/{flow_id}?stream=false
 * with an x-api-key header and a JSON body of output_type, input_type,
 * input_value and session_id. The prompt lives in Langflow itself.
 *
 * Langflow's run response nests the chat output several levels deep and its
 * exact shape depends on the flow's components and version, so extractText()
 * tries the known locations and then searches. Any failure throws (a non-2xx
 * response as LangflowRequestException, with the body redacted);
 * FallbackSessionAnalyzer then falls back to Gemini, or AnalyzeSessionJob
 * retries later if the failure is transient. Headers and the key are never
 * logged.
 */
class LangflowSessionAnalyzer implements SessionAnalyzer
{
    public const DEFAULT_TIMEOUT = 120;

    private string $url;

    private ?string $flowId;

    private ?string $apiKey;

    private int $timeout;

    public function __construct(?string $url = null, ?string $flowId = null, ?string $apiKey = null, ?int $timeout = null)
    {
        $this->url = self::normalizeBaseUrl((string) ($url ?? config('services.langflow.url')));
        $this->flowId = $flowId ?? config('services.langflow.flow_id');
        $this->apiKey = $apiKey ?? config('services.langflow.api_key');
        $timeout ??= (int) config('services.langflow.timeout', self::DEFAULT_TIMEOUT);
        $this->timeout = $timeout > 0 ? $timeout : self::DEFAULT_TIMEOUT; // 0 would mean "no timeout" to Guzzle
    }

    /**
     * LANGFLOW_URL is the server's base URL, but a copied "…/api" or a
     * trailing slash would otherwise double up in the run path.
     */
    public static function normalizeBaseUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');

        return rtrim((string) preg_replace('#/api$#i', '', $url), '/');
    }

    public function isConfigured(): bool
    {
        return filled($this->url) && filled($this->flowId);
    }

    public function endpoint(): string
    {
        return "{$this->url}/api/v1/run/{$this->flowId}?stream=false";
    }

    public function timeout(): int
    {
        return $this->timeout;
    }

    /**
     * A fresh Langflow session_id per analysis, so Langflow's chat memory
     * never mixes one session's transcript (or a retry's) into another.
     */
    public static function newSessionId(?int $sessionId = null): string
    {
        return 'analisis-'.($sessionId ?? 'manual').'-'.now()->format('YmdHisv').'-'.Str::lower(Str::random(4));
    }

    public function analyze(string $transcript, ?int $sessionId = null): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('LANGFLOW_URL / LANGFLOW_FLOW_ID are not set; cannot call Langflow.');
        }

        $response = $this->send($transcript, self::newSessionId($sessionId));

        if (! $response->successful()) {
            throw LangflowRequestException::fromResponse($response);
        }

        $text = self::extractText((array) $response->json());

        if ($text === null) {
            Log::debug('LangflowSessionAnalyzer: no text found in the run response.', ['body' => SecretRedactor::redact($response->body())]);

            throw new RuntimeException('Langflow response contains no output text.');
        }

        return ErrorAnalysis::parse($text) + ['sumber' => 'langflow'];
    }

    /** One run request, exactly as Langflow's API panel shows it; the response is returned as-is (not thrown). */
    public function send(string $input, string $langflowSessionId): Response
    {
        return AiDailyBudget::run(1000 + strlen($input), fn () => Http::timeout($this->timeout)
            ->asJson()
            ->withHeaders(filled($this->apiKey) ? ['x-api-key' => $this->apiKey] : [])
            ->post($this->endpoint(), [
                'output_type' => 'chat',
                'input_type' => 'chat',
                'input_value' => $input,
                'session_id' => $langflowSessionId,
            ]));
    }

    public static function extractText(array $data): ?string
    {
        foreach (['outputs.0.outputs.0.results.message.text', 'outputs.0.outputs.0.outputs.message.message'] as $path) {
            $value = data_get($data, $path);

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return self::findText($data);
    }

    /** First non-empty string under a 'text' or 'message' key, depth-first in document order. */
    private static function findText(array $node): ?string
    {
        foreach ($node as $key => $value) {
            if (in_array($key, ['text', 'message'], true) && is_string($value) && trim($value) !== '') {
                return $value;
            }

            if (is_array($value) && ($found = self::findText($value)) !== null) {
                return $found;
            }
        }

        return null;
    }
}
