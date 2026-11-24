<?php

namespace App\Services;

/**
 * Stitches an OpenAI-compatible server-sent-events body ("data: {chunk}"
 * per line) back into plain text plus token usage. Some routers (e.g.
 * 9router) answer with SSE even when stream:false was requested, so both
 * the text client and the vision recognizer share this parser.
 */
final class OpenAiStreamParser
{
    /**
     * @return array{text: string, tokens_in: ?int, tokens_out: ?int}
     */
    public static function parse(string $body): array
    {
        $text = '';
        $tokensIn = null;
        $tokensOut = null;

        foreach (preg_split('/\r?\n/', $body) as $line) {
            $line = trim($line);
            if (! str_starts_with($line, 'data:')) {
                continue;
            }
            $data = trim(substr($line, 5));
            if ($data === '' || $data === '[DONE]') {
                continue;
            }
            $chunk = json_decode($data, true);
            if (! is_array($chunk)) {
                continue;
            }
            // Non-streaming-shaped chunks (choices[0].message) are accepted
            // too, so a mixed body still yields its text.
            $delta = $chunk['choices'][0]['delta']['content']
                ?? $chunk['choices'][0]['message']['content']
                ?? '';
            if (is_string($delta)) {
                $text .= $delta;
            }
            $tokensIn = $chunk['usage']['prompt_tokens'] ?? $tokensIn;
            $tokensOut = $chunk['usage']['completion_tokens'] ?? $tokensOut;
        }

        return ['text' => $text, 'tokens_in' => $tokensIn, 'tokens_out' => $tokensOut];
    }
}
