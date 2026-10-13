<?php

namespace App\Services;

use App\Contracts\AiClient;
use Illuminate\Http\Client\RequestException;
use InvalidArgumentException;
use RuntimeException;

/**
 * Batch-translates short English dictionary glosses/headwords to Indonesian
 * via the already-integrated Gemini AiClient (see GeminiClient) — reuses the
 * same free provider as the Conversation Partner instead of standing up a
 * separate paid translation API. One request per batch: the whole list goes
 * in as a JSON array and Gemini is asked to return a JSON array of the same
 * length/order, so parsing back into per-row updates is unambiguous even
 * when a gloss itself contains commas/semicolons.
 */
class GeminiMeaningTranslator
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
        Kamu penerjemah kamus profesional Inggris ke Indonesia. Kamu menerima satu JSON array berisi kata atau frasa singkat berbahasa Inggris dari kamus. Balas HANYA dengan satu JSON array berisi terjemahan Bahasa Indonesia yang singkat dan natural untuk tiap item (istilah kamus, bukan kalimat penuh), PERSIS sepanjang array input dan dalam urutan yang sama. Jangan beri penjelasan, markdown, atau teks apapun di luar JSON array itu sendiri.
        Peringatan: glos Inggris ini berasal dari kamus Mandarin yang kadang memilih homograf yang salah, misalnya nama marga ("surname Du") atau makna kuno/langka ("(archaic)"), padahal kata itu adalah kosakata HSK yang umum. Terjemahkan ke makna yang paling umum dipakai pembelajar HSK; jangan menerjemahkan nama marga atau makna kuno sebagai arti utama.
        Untuk kosakata Mandarin, tiap item array berupa objek {"hanzi", "pinyin", "en"}: terjemahkan "en" sesuai makna kata HSK yang ditunjuk oleh "hanzi" dan "pinyin" tersebut, dan balas tetap berupa JSON array berisi string terjemahan saja (bukan objek).
        PROMPT;

    /** Retried statuses: 429 (rate limit) and 5xx (transient server-side, incl. Gemini's own 503 "overloaded"). */
    private const RETRYABLE_STATUSES = [429, 500, 502, 503, 504];

    private const MAX_ATTEMPTS = 4;

    /** @var int[] seconds to wait before attempts 2, 3, 4 — a real ~15k-word run hit both 429 and 503 within the first minute on attempt 1 alone. */
    private const RETRY_DELAYS_SECONDS = [15, 30, 60];

    private readonly AiClient $client;

    public function __construct(AiClient $client)
    {
        // One plain call per attempt: the loop below is the only retry, so retries never stack.
        $this->client = $client instanceof GeminiClient ? $client->forKind(GeminiClient::TRANSLATION) : $client;
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * $headwords (Mandarin only) carries each gloss's hanzi and pinyin, so
     * the model can tell which word a gloss belongs to instead of guessing
     * from the English alone — the dataset's glosses sometimes describe the
     * wrong homograph. Items are then sent as {hanzi, pinyin, en} objects;
     * without it (English vocab), a plain array of strings.
     *
     * @param  string[]  $texts
     * @param  array<int, array{hanzi: string, pinyin: string}>|null  $headwords  same order and count as $texts
     * @return string[] translations, same order and count as $texts
     */
    public function translateBatch(array $texts, ?array $headwords = null): array
    {
        if (empty($texts)) {
            return [];
        }

        $texts = array_values($texts);
        $payload = $texts;

        if ($headwords !== null) {
            $headwords = array_values($headwords);

            if (count($headwords) !== count($texts)) {
                throw new InvalidArgumentException('$headwords must have the same count as $texts.');
            }

            $payload = array_map(
                fn (string $en, array $h) => ['hanzi' => $h['hanzi'], 'pinyin' => $h['pinyin'], 'en' => $en],
                $texts,
                $headwords
            );
        }

        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $result = $this->client->sendMessage(
                    model: config('services.active_ai_model'),
                    system: self::SYSTEM_PROMPT,
                    messages: [['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE)]],
                    maxTokens: 300 + count($texts) * 40,
                );

                break;
            } catch (RequestException $e) {
                $status = $e->response->status();

                if ($attempt >= self::MAX_ATTEMPTS || ! in_array($status, self::RETRYABLE_STATUSES, true)) {
                    throw $e;
                }

                sleep(self::RETRY_DELAYS_SECONDS[$attempt - 1]);
            }
        }

        $translated = $this->decodeJsonArray($result['text']);

        if ($translated === null || count($translated) !== count($texts)) {
            throw new RuntimeException('Gemini returned a malformed or mismatched-length translation batch.');
        }

        return $translated;
    }

    /** @return ?string[] */
    private function decodeJsonArray(string $text): ?array
    {
        $text = trim($text);
        // Gemini sometimes wraps JSON in a ```json fence despite being told not to.
        $text = preg_replace('/^```(?:json)?|```$/i', '', $text);
        $decoded = json_decode(trim((string) $text), true);

        return is_array($decoded) ? array_values($decoded) : null;
    }
}
