<?php

namespace App\Console\Commands;

use App\Models\MandarinHskWord;
use Illuminate\Console\Command;

/**
 * Clears meaning_id on uncorrected mandarin_hsk_words rows whose FIRST
 * English sense is "surname ..." or "(slang) ..." (see
 * MandarinHskWord::scopeUnreliableGloss). Those translations came from the
 * dataset's wrong-homograph gloss (也 "surname Ye" → "nama keluarga Ye") and
 * teach a wrong meaning; with meaning_id NULL, Ucapan shows "Arti belum
 * tersedia" instead and translate:meanings leaves the row alone.
 *
 * Hand-corrected rows (data_status='corrected_draft') are never touched.
 * Idempotent: a second run finds nothing left to clear.
 */
class ClearUnreliableTranslations extends Command
{
    protected $signature = 'hsk:clear-unreliable-translations {--dry-run : Only print what would be cleared}';

    protected $description = 'Clear meaning_id translated from wrong-homograph (surname/slang) glosses on uncorrected HSK rows';

    public function handle(): int
    {
        $rows = MandarinHskWord::whereNull('data_status')
            ->whereNotNull('meaning_id')
            ->unreliableGloss()
            ->orderBy('id')
            ->get(['id', 'hanzi', 'pinyin', 'meaning_en', 'meaning_id']);

        if ($rows->isEmpty()) {
            $this->info('Nothing to clear.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'hanzi', 'pinyin', 'meaning_en', 'meaning_id (old)'],
            $rows->map(fn (MandarinHskWord $w) => [$w->id, $w->hanzi, $w->pinyin, $w->meaning_en, $w->meaning_id])->all()
        );

        if ($this->option('dry-run')) {
            $this->info("{$rows->count()} row(s) would be cleared. Dry run: nothing written.");

            return self::SUCCESS;
        }

        MandarinHskWord::whereIn('id', $rows->pluck('id'))->update(['meaning_id' => null]);

        $this->info("{$rows->count()} row(s) cleared.");

        return self::SUCCESS;
    }
}
