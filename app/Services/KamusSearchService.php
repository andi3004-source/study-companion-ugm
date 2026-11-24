<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Server-side search across the full dictionary (~15.8k rows spread over 4
 * tables). Rows are never loaded into a Livewire property in bulk — every
 * keystroke re-runs a bounded SQL UNION ALL with its own WHERE/LIMIT, so the
 * dataset size doesn't matter to page weight.
 *
 * Mandarin = mandarin_words (curriculum, HSK1) + mandarin_hsk_words (HSK2-6).
 * English = english_vocab (CEFR) + irregular_verbs_reference (verbs, no CEFR
 * level of their own — shown only when no specific CEFR level is selected).
 */
class KamusSearchService
{
    public const MANDARIN_LEVELS = ['1', '2', '3', '4', '5', '6'];

    public const ENGLISH_LEVELS = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];

    public function __construct(private readonly AnswerChecker $checker) {}

    /** @return Collection<int, object{lang: string, source: string, headword: string, sub: ?string, meaning_en: ?string, meaning_id: ?string, raw_level: mixed, lang_code: string, example_en: ?string, verb_past: ?string, verb_past_participle: ?string, data_status: ?string, is_verb: bool}> */
    public function results(string $lang, string $level, string $search, int $limit): Collection
    {
        $rows = $this->buildUnion($lang, $level, $search)
            ->orderBy('headword')
            ->limit($limit)
            ->get();

        $rows->each(function (object $entry): void {
            $entry->is_verb = $entry->lang === 'english'
                && ($entry->source === 'verb' || self::isVerbPos($entry->sub));
        });

        return $rows;
    }

    public function pronunciationWord(string $lang, string $headword, ?string $sub): ?array
    {
        if (! in_array($lang, ['english', 'mandarin'], true) || mb_strlen($headword) > 100 || mb_strlen($sub ?? '') > 100) {
            return null;
        }

        $row = DB::query()->fromSub($this->buildUnion($lang, 'all', $headword), 'words')
            ->where('headword', $headword)
            ->where('sub', $sub ?? '')
            ->first();

        return $row ? [
            'headword' => $row->headword,
            'sub' => $row->sub,
            'meaning_id' => $row->meaning_id,
            'meaning_en' => $row->meaning_en,
            'lang_code' => $row->lang_code,
        ] : null;
    }

    public function count(string $lang, string $level, string $search): int
    {
        return DB::query()->fromSub($this->buildUnion($lang, $level, $search), 'kamus_union')->count();
    }

    private function buildUnion(string $lang, string $level, string $search): Builder
    {
        $rawSearch = trim($search) !== '' ? trim($search) : null;
        $normalizedSearch = $rawSearch !== null ? $this->checker->normalizePinyin($rawSearch) : null;

        $mandarinLevel = in_array($level, self::MANDARIN_LEVELS, true) ? (int) $level : null;
        $englishLevel = in_array($level, self::ENGLISH_LEVELS, true) ? $level : null;

        $subqueries = [];

        if (in_array($lang, ['all', 'mandarin'], true)) {
            $subqueries[] = $this->mandarinQuery('mandarin_words', 'curriculum', $mandarinLevel, $rawSearch, $normalizedSearch);
            $subqueries[] = $this->mandarinQuery('mandarin_hsk_words', 'hsk_reference', $mandarinLevel, $rawSearch, $normalizedSearch);
        }

        if (in_array($lang, ['all', 'english'], true)) {
            $subqueries[] = $this->englishCefrQuery($englishLevel, $rawSearch);

            // Verbs have no CEFR level, so a specific level filter excludes them entirely.
            if ($englishLevel === null) {
                $subqueries[] = $this->englishVerbQuery($rawSearch);
            }
        }

        $first = array_shift($subqueries);
        foreach ($subqueries as $q) {
            $first->unionAll($q);
        }

        return $first;
    }

    private function mandarinQuery(string $table, string $source, ?int $level, ?string $rawSearch, ?string $normalizedSearch): Builder
    {
        $q = DB::table($table)->select([
            DB::raw("'mandarin' as lang"),
            DB::raw("'{$source}' as source"),
            'hanzi as headword',
            'pinyin as sub',
            'meaning_en',
            'meaning_id',
            'hsk_level_old as raw_level',
            DB::raw("'zh-CN' as lang_code"),
            DB::raw('NULL as example_en'),
            DB::raw('NULL as verb_past'),
            DB::raw('NULL as verb_past_participle'),
            // only the HSK2-6 reference table has hand-corrected rows
            $table === 'mandarin_hsk_words' ? 'data_status' : DB::raw('NULL as data_status'),
        ]);

        if ($level !== null) {
            $q->where('hsk_level_old', $level);
        }

        if ($rawSearch !== null) {
            $q->where(function (Builder $w) use ($rawSearch, $normalizedSearch) {
                $w->where('hanzi', 'like', "%{$rawSearch}%")
                    ->orWhere('pinyin_normalized', 'like', "%{$normalizedSearch}%");
                $this->orWholeWordLike($w, 'meaning_id', $rawSearch);
                $this->orWholeWordLike($w, 'meaning_en', $rawSearch);
            });
        }

        return $q;
    }

    /**
     * LEFT JOINs irregular_verbs_reference (183 rows, unique-indexed on
     * base_form) so a verb entry's V1/V2/V3 rides along on the same row
     * instead of surfacing as a second, disconnected search result.
     */
    private function englishCefrQuery(?string $level, ?string $rawSearch): Builder
    {
        $q = DB::table('english_vocab as ev')
            ->leftJoin('irregular_verbs_reference as iv', DB::raw('LOWER(ev.word)'), '=', 'iv.base_form')
            ->select([
                DB::raw("'english' as lang"),
                DB::raw("'cefr' as source"),
                'ev.word as headword',
                DB::raw("COALESCE(ev.pos, '') as sub"),
                'ev.definition as meaning_en',
                DB::raw(self::jsonText('ev.translations', '$.id').' as meaning_id'),
                'ev.cefr_level as raw_level',
                DB::raw("'en-US' as lang_code"),
                DB::raw(self::jsonText('ev.examples', '$[0].en').' as example_en'),
                'iv.past_form as verb_past',
                'iv.past_participle as verb_past_participle',
                DB::raw('NULL as data_status'),
            ]);

        if ($level !== null) {
            $q->where('ev.cefr_level', $level);
        }

        if ($rawSearch !== null) {
            $q->where(function (Builder $w) use ($rawSearch) {
                $w->where('ev.word', 'like', "%{$rawSearch}%");
                $this->orWholeWordLike($w, 'ev.definition', $rawSearch);
                $this->orWholeWordLike($w, self::jsonText('ev.translations', '$.id'), $rawSearch);
            });
        }

        return $q;
    }

    /**
     * Only the ~32 irregular verbs with no english_vocab entry of their own
     * surface here — the rest are already folded into englishCefrQuery's
     * JOIN above, so this excludes them to avoid showing the same word
     * twice. Deliberately a NOT IN subquery rather than a LEFT JOIN +
     * whereNull: a join driven from this table (183 rows) against
     * english_vocab (10k+ rows) can't use the LOWER(word) expression index
     * from this direction and falls back to a full table scan per row
     * (~2s); NOT IN lets SQLite build one ephemeral lookup set instead.
     */
    private function englishVerbQuery(?string $rawSearch): Builder
    {
        $q = DB::table('irregular_verbs_reference as iv')
            ->whereNotIn('iv.base_form', function ($sub) {
                $sub->select('word')->from('english_vocab');
            })
            ->select([
                DB::raw("'english' as lang"),
                DB::raw("'verb' as source"),
                'iv.base_form as headword',
                DB::raw("'' as sub"),
                DB::raw('NULL as meaning_en'),
                'iv.meaning_id',
                DB::raw('NULL as raw_level'),
                DB::raw("'en-US' as lang_code"),
                DB::raw('NULL as example_en'),
                'iv.past_form as verb_past',
                'iv.past_participle as verb_past_participle',
                DB::raw('NULL as data_status'),
            ]);

        if ($rawSearch !== null) {
            $q->where(function (Builder $w) use ($rawSearch) {
                $w->where('iv.base_form', 'like', "%{$rawSearch}%")
                    ->orWhere('iv.past_form', 'like', "%{$rawSearch}%")
                    ->orWhere('iv.past_participle', 'like', "%{$rawSearch}%");
                $this->orWholeWordLike($w, 'iv.meaning_id', $rawSearch);
            });
        }

        return $q;
    }

    /**
     * Whole-word LIKE for free-text meaning/definition columns, appended to
     * $w as an OR condition. Plain substring search on these columns lets an
     * unrelated word swallow the query just because it happens to contain
     * those letters somewhere inside it — e.g. searching "ayo" (Indonesian)
     * matched meaning_en values "layout" and "...as a mayor..." even though
     * neither means "ayo". Padding the column with boundary spaces (after
     * turning common punctuation into spaces too, so "makan, minum" still
     * has a boundary around "makan") and requiring "% term %" forces the
     * query to appear as a whole word, not a fragment of a different one.
     * Headword/pinyin columns deliberately keep substring matching — that's
     * intentional partial-word/pinyin search, not the failure mode here.
     */
    private function orWholeWordLike(Builder $w, string $column, string $term): void
    {
        $cleaned = "REPLACE(REPLACE(REPLACE(REPLACE(LOWER({$column}), ',', ' '), '/', ' '), ';', ' '), '.', ' ')";
        // MySQL treats || as OR, not string concatenation.
        $paddedColumn = self::isMysql() ? "CONCAT(' ', {$cleaned}, ' ')" : "(' ' || {$cleaned} || ' ')";

        $w->orWhereRaw("{$paddedColumn} LIKE ?", ['% '.mb_strtolower(trim($term)).' %']);
    }

    /**
     * A JSON value as plain text. SQLite's json_extract already returns text;
     * MySQL's returns a quoted JSON string, so it needs JSON_UNQUOTE.
     */
    private static function jsonText(string $column, string $path): string
    {
        return self::isMysql()
            ? "JSON_UNQUOTE(JSON_EXTRACT({$column}, '{$path}'))"
            : "json_extract({$column}, '{$path}')";
    }

    private static function isMysql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    /**
     * english_vocab.pos is free text from the source dataset ("v.", "verb",
     * "v./n.", "modal v.", "adv./adj.", ...). Tokenizing after stripping
     * dots avoids false positives like "adv." (adverb) matching on "v".
     */
    private static function isVerbPos(?string $pos): bool
    {
        if (! $pos) {
            return false;
        }

        $tokens = preg_split('/[\/\s]+/', str_replace('.', '', strtolower($pos)), -1, PREG_SPLIT_NO_EMPTY);

        return in_array('v', $tokens, true) || in_array('verb', $tokens, true);
    }
}
