<?php

namespace App\Services;

use App\Contracts\PronunciationChecker;

/**
 * Free, local pronunciation check: compares what the browser's own speech
 * recognizer heard ($sttTranscript) against the target text. No audio
 * analysis, no external calls — always available, which is why
 * MlPronunciationChecker falls back to this whenever pronunciation-service
 * is unreachable (see MlPronunciationChecker::check()), and why it's also
 * the only checker multi-character Mandarin targets ever reach (tone-me's
 * model only classifies a single syllable — see MlPronunciationChecker's
 * multi-character guard).
 *
 * English uses fuzzy string similarity (a mispronounced word can still
 * transcribe close enough to pass, and vice versa — the STT transcript is
 * itself an imperfect proxy for pronunciation). Mandarin requires an exact
 * character match: each Hanzi carries its own tone/meaning, so "close" isn't
 * a meaningful notion the way it is for an English sentence — and an exact
 * match lets a wrong multi-character answer point at exactly which
 * character(s) differ instead of just reporting a similarity percentage.
 */
class SttMatchPronunciationChecker implements PronunciationChecker
{
    private const CORRECT_THRESHOLD = 80.0;

    public function check(string $audioPath, string $targetText, string $lang, ?string $sttTranscript = null, ?string $targetPinyin = null): array
    {
        $heard = trim((string) $sttTranscript);
        $target = trim($targetText);

        if ($heard === '') {
            return [
                'isCorrect' => false,
                'feedback' => 'Tidak ada suara yang terdeteksi. Coba ucapkan lagi lebih jelas.',
            ];
        }

        if ($this->isUnrecognizable($heard, $lang)) {
            return [
                'isCorrect' => false,
                'feedback' => "Mesin pengenalan suara kesulitan dengan kata pendek ini (yang tertangkap: \"{$heard}\") — coba ucapkan dengan sedikit lebih panjang, atau coba kata lain dulu.",
            ];
        }

        return $lang === 'mandarin'
            ? $this->checkMandarin($heard, $target, $targetPinyin)
            : $this->checkEnglish($heard, $target);
    }

    private function checkEnglish(string $heard, string $target): array
    {
        // Spaces are ignored too, so "ice cream", "ice-cream" and "icecream" compare equal.
        similar_text(str_replace(' ', '', $this->normalize($heard)), str_replace(' ', '', $this->normalize($target)), $percent);

        $isCorrect = $percent >= self::CORRECT_THRESHOLD;

        $feedback = $isCorrect
            ? "Bagus! Pengucapan \"{$target}\" terdengar sudah tepat."
            : "Kurang tepat. Target: \"{$target}\", yang terdengar: \"{$heard}\" (kemiripan ".round($percent).'%).';

        return ['isCorrect' => $isCorrect, 'feedback' => $feedback];
    }

    private function checkMandarin(string $heard, string $target, ?string $targetPinyin): array
    {
        // Chrome's zh-CN engine almost always ends with "。"; compare (and number
        // character positions) on the text without punctuation or spaces, and
        // case-insensitively for Latin letters inside words like "T恤" or "卡拉OK".
        $targetChars = mb_str_split(mb_strtolower($this->stripPunctuationAndSpaces($target)));
        $heardChars = mb_str_split(mb_strtolower($this->stripPunctuationAndSpaces($heard)));

        if ($heardChars === $targetChars) {
            return ['isCorrect' => true, 'feedback' => "Bagus! Pengucapan \"{$target}\" terdengar sudah tepat."];
        }

        $targetLabel = $targetPinyin ? "{$target} ({$targetPinyin})" : $target;

        if (count($targetChars) > 1) {
            $diffs = [];
            $length = max(count($targetChars), count($heardChars));

            for ($i = 0; $i < $length; $i++) {
                $t = $targetChars[$i] ?? null;
                $h = $heardChars[$i] ?? null;

                if ($t !== $h) {
                    $diffs[] = 'karakter ke-'.($i + 1).' beda (target "'.($t ?? '-').'", terdengar "'.($h ?? '-').'")';
                }
            }

            return [
                'isCorrect' => false,
                'feedback' => "Target: {$targetLabel}, terdengar: \"{$heard}\" — ".implode(', ', $diffs).'.',
            ];
        }

        return [
            'isCorrect' => false,
            'feedback' => "Kurang tepat. Target: \"{$targetLabel}\", yang terdengar: \"{$heard}\".",
        ];
    }

    /**
     * True when the STT engine didn't actually transcribe a word in the
     * target language — e.g. "——" or "1111" for a short, flat-tone Mandarin
     * syllable like "一". Chrome's zh-CN engine can fail short syllables this
     * way (punctuation, or misreading a number word as a digit), and that's
     * the engine failing, not evidence the user mispronounced the word —
     * same \p{Han} check used in PronunciationCheck::checkPronunciation() to
     * detect whether a target has Hanzi at all.
     */
    private function isUnrecognizable(string $heard, string $lang): bool
    {
        // Checked on the text without punctuation: PCRE's \p{Han} also matches
        // CJK punctuation such as "。" (Script_Extensions), so a lone "。"
        // would otherwise look like a recognized Hanzi.
        $heard = $this->stripPunctuationAndSpaces($heard);

        return $lang === 'mandarin'
            ? ! preg_match('/\p{Han}/u', $heard)
            : ! preg_match('/\p{L}/u', $heard);
    }

    /**
     * Removes Unicode punctuation (Chinese 。，、！？；：「」『』（）…—· and ASCII
     * punctuation), ASCII symbols, and all whitespace including the full-width
     * space U+3000. Letters, Hanzi and digits are kept, so a different
     * character is still a difference.
     */
    private function stripPunctuationAndSpaces(string $text): string
    {
        return preg_replace('/[\p{P}\p{Z}\s!-\/:-@\[-`{-~]+/u', '', $text);
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);

        return trim(preg_replace('/\s+/', ' ', $text));
    }
}
