<?php

namespace App\Support;

/**
 * Masks API keys in text that may reach a log, an exception message or the
 * console (Langflow error bodies echo request details back). The configured
 * provider keys are replaced literally first, then anything that
 * looks like an OpenAI/Langflow (sk-…), Google (AIza…) or Astra (AstraCS:…)
 * key.
 */
class SecretRedactor
{
    public const MASK = '[REDACTED]';

    private const PATTERNS = [
        '/\bsk-[A-Za-z0-9_\-]{6,}/',
        '/\bAIza[0-9A-Za-z_\-]{10,}/',
        '/\bAstraCS:[A-Za-z0-9:_\-]+/',
    ];

    // Shorter "keys" (test placeholders, typos) would mask ordinary words.
    private const MIN_LITERAL_LENGTH = 8;

    public static function redact(string $text): string
    {
        foreach (self::configuredSecrets() as $secret) {
            $text = str_replace($secret, self::MASK, $text);
        }

        return (string) preg_replace(self::PATTERNS, self::MASK, $text);
    }

    /** Redacts first, then truncates — so a key cut at the limit can't leak its first half. */
    public static function snippet(string $text, int $limit = 500): string
    {
        return mb_substr(self::redact($text), 0, $limit);
    }

    /** @return array<int, string> */
    private static function configuredSecrets(): array
    {
        return collect([config('services.langflow.api_key'), config('services.gemini.key'), config('services.anthropic.key'), config('services.openai_compatible.key')])
            ->filter(fn ($secret) => is_string($secret) && strlen(trim($secret)) >= self::MIN_LITERAL_LENGTH)
            ->map(fn (string $secret) => trim($secret))
            ->all();
    }
}
