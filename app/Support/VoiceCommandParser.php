<?php

namespace App\Support;

/**
 * Turns one spoken Indonesian command (a speech-to-text transcript) into a
 * small, fixed command for "Mode suara". Pure and stateless; the transcript
 * is never stored or logged.
 *
 * Matching is on the whole utterance after normalising (lowercase, no
 * punctuation, filler words like "buka", "masuk ke" or "dong" removed), so a
 * sentence that mixes two commands, or two different numbers, is 'unknown'
 * rather than a guess. When nothing matches exactly, one misheard word is
 * tolerated (Levenshtein distance 1, words of 5 letters or more); if that
 * fits two different commands equally well the answer is 'clarify'.
 *
 * Result: ['type' => string, 'target' => ?string, 'index' => ?int]
 *   navigate        target = route name
 *   start           target = null ("mulai")
 *   choose_number   index  = 0-based (1-12, or a-d)
 *   clarify         plus 'candidates' => two results, each with a 'label' (the phrase to offer)
 *   back, repeat, read_page, list_actions, more, confirm_yes, confirm_no,
 *   stop, help, dictate_start, dictate_stop, unknown
 */
class VoiceCommandParser
{
    public const MAX_NUMBER = 12;

    /** Words that may surround a page name or a command without changing it. */
    private const FILLERS = [
        'tolong', 'coba', 'buka', 'bukakan', 'masuk', 'pergi', 'ke', 'halaman', 'menu', 'fitur',
        'saya', 'aku', 'mau', 'ingin', 'pengen', 'ayo', 'yuk', 'dong', 'deh', 'tes', 'dulu', 'nih', 'sih',
    ];

    /** Fillers that may also surround a number ("saya pilih dua dong"). */
    private const NUMBER_FILLERS = ['tolong', 'coba', 'saya', 'aku', 'mau', 'ingin', 'ayo', 'yuk', 'dong', 'deh'];

    /** phrase => route name */
    private const PAGES = [
        'beranda' => 'home',
        'hari ini' => 'home',
        'ngobrol' => 'conversation',
        'obrolan' => 'conversation',
        'percakapan' => 'conversation',
        'chat' => 'conversation',
        'ucapan' => 'pronunciation-check',
        'pengucapan' => 'pronunciation-check',
        'cek pengucapan' => 'pronunciation-check',
        'buku saku' => 'buku-saku',
        'bantuan' => 'bantuan',
    ];

    /** type => phrases (whole utterance) */
    private const COMMANDS = [
        'back' => ['kembali', 'mundur', 'halaman sebelumnya'],
        'repeat' => ['ulangi', 'ulang', 'sekali lagi'],
        'read_page' => ['baca halaman', 'bacakan halaman', 'baca', 'bacakan'],
        'list_actions' => ['daftar tindakan', 'tindakan', 'apa saja', 'pilihan apa saja'],
        'more' => ['lanjut', 'lanjutkan'],
        'confirm_yes' => ['ya', 'iya', 'betul', 'benar', 'oke', 'ok', 'yes'],
        'confirm_no' => ['tidak', 'bukan', 'batal', 'jangan', 'no'],
        'stop' => ['berhenti', 'stop', 'diam', 'matikan mode suara'],
        'help' => ['perintah', 'bantuan suara', 'daftar perintah', 'apa yang bisa saya katakan'],
        'dictate_start' => ['isi', 'ketik', 'mulai isi', 'mulai ketik'],
        'dictate_stop' => ['selesai isi', 'berhenti isi', 'selesai ketik', 'berhenti ketik'],
    ];

    private const CARDINALS = [
        'dua belas' => 12, 'sebelas' => 11, 'sepuluh' => 10, 'sembilan' => 9, 'delapan' => 8, 'tujuh' => 7,
        'enam' => 6, 'lima' => 5, 'empat' => 4, 'tiga' => 3, 'dua' => 2, 'satu' => 1,
    ];

    /** Words allowed around a number in a choice ("nomor tiga", "pilihan dua", "jawab tiga", "yang kedua"). */
    private const NUMBER_WORDS = ['nomor', 'pilihan', 'pilih', 'yang', 'jawaban', 'jawab', 'opsi', 'huruf', 'angka'];

    /** Fuzzy matching only for words at least this long (shorter words must match exactly). */
    private const FUZZY_MIN_LENGTH = 5;

    public static function parse(string $transcript): array
    {
        $text = self::normalize($transcript);

        if ($text === '') {
            return self::result('unknown');
        }

        $matches = self::exact($text);

        // Exactly one interpretation: done. Two or more: never guess between them.
        if (count($matches) === 1) {
            return $matches[0];
        }
        if (count($matches) > 1) {
            return self::result('unknown');
        }

        return self::fuzzy(self::withoutFillers($text));
    }

    public static function normalize(string $transcript): string
    {
        $text = mb_strtolower($transcript);
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** A short phrase for a result, to offer in a spoken choice ("buka ngobrol", "nomor 3", "kembali"). */
    public static function label(array $result): string
    {
        if ($result['type'] === 'choose_number') {
            return 'nomor '.($result['index'] + 1);
        }

        $wanted = ['type' => $result['type'], 'target' => $result['target'], 'index' => $result['index']];
        foreach (self::phrases() as $phrase) {
            if (self::exact($phrase) === [$wanted]) {
                return $phrase;
            }
        }

        return $result['type'];
    }

    /** @return list<array> every interpretation of the text, with no tolerance for mishearing */
    private static function exact(string $text): array
    {
        return array_values(array_filter([
            self::page($text),
            self::command($text),
            self::number($text),
            self::start($text),
        ]));
    }

    private static function page(string $text): ?array
    {
        $phrase = self::withoutFillers($text);

        $route = self::PAGES[$phrase] ?? null;

        if ($route === null) {
            return null;
        }

        return self::result('navigate', $route);
    }

    private static function command(string $text): ?array
    {
        $found = [];
        foreach (self::COMMANDS as $type => $phrases) {
            if (in_array($text, $phrases, true) || in_array(self::withoutFillers($text), $phrases, true)) {
                $found[] = $type;
            }
        }

        return count($found) === 1 ? self::result($found[0]) : null;
    }

    /** A plain start command. */
    private static function start(string $text): ?array
    {
        if (! preg_match('/^(mulai|main)(?: (.+))?$/u', self::withoutFillers($text), $m)) {
            return null;
        }

        $rest = $m[2] ?? '';

        return $rest === '' ? self::result('start') : null;
    }

    private static function number(string $text): ?array
    {
        $words = explode(' ', $text);

        // A single letter a-d, optionally after "pilihan"/"jawaban"/"huruf".
        $letters = array_values(array_diff($words, self::NUMBER_WORDS));
        if (count($letters) === 1 && preg_match('/^[a-d]$/', $letters[0]) && count($words) <= 2) {
            return self::result('choose_number', null, ord($letters[0]) - ord('a'));
        }

        // Ordinals first ("kedua belas", "ketiga"), then cardinals, then "pertama".
        $cardinals = implode('|', array_keys(self::CARDINALS));
        $text = preg_replace_callback('/\bke ?('.$cardinals.')\b/u', fn ($m) => ' '.self::CARDINALS[$m[1]].' ', $text);
        $text = preg_replace_callback('/\b('.$cardinals.')\b/u', fn ($m) => ' '.self::CARDINALS[$m[1]].' ', $text);
        $text = preg_replace('/\b(pertama|kesatu)\b/u', ' 1 ', $text);

        $tokens = array_values(array_filter(explode(' ', $text), fn ($t) => $t !== ''));
        $numbers = array_values(array_unique(array_filter($tokens, fn ($t) => ctype_digit($t))));
        $rest = array_diff($tokens, $numbers, self::NUMBER_WORDS, self::NUMBER_FILLERS, ['ke']);

        if (count($numbers) !== 1 || $rest !== []) {
            return null;
        }

        $n = (int) $numbers[0];

        return $n >= 1 && $n <= self::MAX_NUMBER ? self::result('choose_number', null, $n - 1) : null;
    }

    /**
     * One misheard word ("ngobroll", "lanjud"): the known phrase with the
     * same number of words and the smallest total distance wins. Two different
     * commands at the same distance give 'clarify' with both, never a guess.
     */
    private static function fuzzy(string $text): array
    {
        $words = explode(' ', $text);
        $best = null;
        $candidates = [];

        foreach (self::phrases() as $phrase) {
            $distance = self::distance($words, explode(' ', $phrase));
            if ($distance === null || $distance === 0 || ($best !== null && $distance > $best)) {
                continue;
            }

            $matches = self::exact($phrase);
            if (count($matches) !== 1) {
                continue;
            }

            if ($best === null || $distance < $best) {
                $best = $distance;
                $candidates = [];
            }
            $key = $matches[0]['type'].'|'.$matches[0]['target'].'|'.$matches[0]['index'];
            $candidates[$key] ??= $matches[0] + ['label' => $phrase];
        }

        $candidates = array_values($candidates);

        if (count($candidates) === 1) {
            unset($candidates[0]['label']);

            return $candidates[0];
        }

        if (count($candidates) > 1) {
            return self::result('clarify') + ['candidates' => array_slice($candidates, 0, 2)];
        }

        return self::result('unknown');
    }

    /** Sum of per-word distances, or null when a word is too far (or too short to be fuzzy). */
    private static function distance(array $heard, array $known): ?int
    {
        if (count($heard) !== count($known)) {
            return null;
        }

        $total = 0;
        foreach ($heard as $i => $word) {
            if ($word === $known[$i]) {
                continue;
            }
            if (min(mb_strlen($word), mb_strlen($known[$i])) < self::FUZZY_MIN_LENGTH || levenshtein($word, $known[$i]) > 1) {
                return null;
            }
            $total++;
        }

        return $total;
    }

    /** @return list<string> every known phrase (pages, commands, starts) */
    private static function phrases(): array
    {
        $starts = ['mulai'];

        return array_values(array_unique([
            ...array_keys(self::PAGES),
            ...array_merge(...array_values(self::COMMANDS)),
            ...$starts,
        ]));
    }

    private static function withoutFillers(string $text): string
    {
        $words = array_values(array_filter(explode(' ', $text), fn ($w) => ! in_array($w, self::FILLERS, true)));

        return $words === [] ? $text : implode(' ', $words);
    }

    private static function result(string $type, ?string $target = null, ?int $index = null): array
    {
        return ['type' => $type, 'target' => $target, 'index' => $index];
    }
}
