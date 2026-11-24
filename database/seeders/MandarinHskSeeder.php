<?php

namespace Database\Seeders;

use App\Models\MandarinWord;
use App\Services\AnswerChecker;
use App\Support\HskCorrections;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * Imports the official HSK1-6 dataset (storage/app/seed-data/mandarin_hsk1to6_complete.json).
 *
 * HSK1 is NOT re-inserted: the existing 150 rows in mandarin_words drive the
 * the historical curriculum via batch_number/order_in_batch, and their
 * ids are referenced by user_word_progress, so they must keep their id, batch
 * and order. Instead we enrich each matched row in place with the columns the
 * curated data lacks (traditional, radical, frequency, ...) — never its
 * pinyin or meanings, which are curated and more reliable than this dataset
 * (see `php artisan hsk:repair-curriculum` for rows an older version of this
 * seeder already overwrote). 14 of the 150 curated hanzi have no exact match
 * in this dataset (different HSK standard/variant, e.g. "哪儿" vs "哪里") —
 * those rows are left untouched rather than guessed at.
 *
 * HSK2-6 (4,841 words) have no existing curriculum row, so they go into the
 * separate mandarin_hsk_words reference table used for browsing only.
 */
class MandarinHskSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(
            file_get_contents(storage_path('app/seed-data/mandarin_hsk1to6_complete.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->updateHsk1Curriculum($data['1']);
        $this->upsertReference($data);

        // Deliberately NOT dispatched here: translating meaning_id is a
        // separate, explicit, AI-quota-spending operation (see
        // TranslateMandarinMeaningsJob) — run it with
        // `php artisan translate:meanings` when you actually want it, not as
        // a side effect of every re-seed (including every test that seeds
        // this reference data).
    }

    private function updateHsk1Curriculum(array $words): void
    {
        $byHanzi = collect($words)->keyBy('hanzi');

        MandarinWord::where('hsk_level_old', 1)->get()->each(function (MandarinWord $existing) use ($byHanzi) {
            $match = $byHanzi->get($existing->hanzi);

            if (! $match) {
                return;
            }

            // pinyin/pinyin_normalized/meaning_en deliberately stay as curated
            // (storage/app/seed-data/reference): this dataset picks the wrong homograph
            // for several common words (个 "gě", 都 "Dū, surname Du", 听 "yǐn",
            // ...). Only columns the curated data doesn't have are filled in.
            $existing->update([
                'traditional' => $match['traditional'],
                'radical' => $match['radical'],
                'frequency' => $match['frequency'],
                'classifiers' => $match['classifiers'],
                'hsk_level_new' => $match['hsk_level_new'],
            ]);
        });
    }

    /**
     * Entries with a hand-checked correction (see HskCorrections) are inserted
     * with the corrected pinyin, meaning_id and data_status instead of the
     * dataset's wrong homograph, so a fresh seed matches seed +
     * `hsk:apply-corrections`, and re-seeding never brings a wrong row back.
     * meaning_id/data_status are insert-only (not in the upsert's update
     * columns), so existing translations and corrections are never reset.
     *
     * A corrected entry is skipped entirely (never inserted with its wrong
     * pinyin) when either its uncorrected row is still in the table — that
     * row is hsk:apply-corrections' job, and inserting a second, corrected
     * row next to it would make that command collide — or its corrected
     * pinyin is already another dataset entry of the same hanzi.
     */
    private function upsertReference(array $data): void
    {
        $now = now();
        $checker = new AnswerChecker;
        $corrections = HskCorrections::byOriginalKey();

        $existingKeys = $corrections === [] ? [] : DB::table('mandarin_hsk_words')->get(['hanzi', 'pinyin'])
            ->mapWithKeys(fn ($row) => [HskCorrections::key($row->hanzi, $row->pinyin) => true])
            ->all();

        $datasetKeys = [];
        foreach ($data as $level => $words) {
            foreach ((int) $level === 1 ? [] : $words as $w) {
                $datasetKeys[HskCorrections::key($w['hanzi'], $w['pinyin'])] = true;
            }
        }

        $skipped = 0;

        $rows = LazyCollection::make(function () use ($data, $now, $checker, $corrections, $existingKeys, $datasetKeys, &$skipped) {
            foreach ($data as $level => $words) {
                if ((int) $level === 1) {
                    continue;
                }

                foreach ($words as $w) {
                    $key = HskCorrections::key($w['hanzi'], $w['pinyin']);
                    $correction = $corrections[$key] ?? null;

                    if ($correction !== null && $correction['pinyin'] !== $w['pinyin']
                        && (isset($existingKeys[$key]) || isset($datasetKeys[HskCorrections::key($w['hanzi'], $correction['pinyin'])]))) {
                        $skipped++;

                        continue;
                    }

                    $pinyin = $correction['pinyin'] ?? $w['pinyin'];

                    yield [
                        'hanzi' => $w['hanzi'],
                        'traditional' => $w['traditional'],
                        'pinyin' => $pinyin,
                        'pinyin_normalized' => $checker->normalizePinyin($pinyin),
                        'meaning_en' => $w['meaning_en'],
                        'meaning_id' => $correction['indonesian'] ?? null,
                        'data_status' => $correction !== null ? HskCorrections::STATUS : null,
                        'pos' => json_encode($w['pos']),
                        'radical' => $w['radical'],
                        'frequency' => $w['frequency'],
                        'hsk_level_old' => $w['hsk_level_old'],
                        'hsk_level_new' => $w['hsk_level_new'],
                        'classifiers' => json_encode($w['classifiers']),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        });

        $rows->chunk(500)->each(function ($chunk) {
            DB::table('mandarin_hsk_words')->upsert(
                $chunk->all(),
                ['hanzi', 'pinyin'],
                ['traditional', 'pinyin_normalized', 'meaning_en', 'pos', 'radical', 'frequency', 'hsk_level_old', 'hsk_level_new', 'classifiers', 'updated_at']
            );
        });

        if ($skipped > 0) {
            $this->command?->warn("MandarinHskSeeder: {$skipped} corrected HSK2-3 entr(y/ies) skipped — run `php artisan hsk:apply-corrections` to fix the existing rows.");
        }
    }
}
