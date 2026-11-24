<?php

namespace App\Console\Commands;

use Database\Seeders\EnglishVocabSeeder;
use Database\Seeders\IrregularVerbSeeder;
use Database\Seeders\MandarinHskSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Console\Command;

/**
 * One command to fill the content tables after `migrate` (no users): the
 * Mandarin words and week topics, HSK 1-6, irregular verbs and English CEFR
 * vocabulary, all read from storage/app/seed-data. Safe to run again: every
 * seeder is idempotent (insert-if-empty or upsert). A seeder whose source
 * file is missing is skipped with a message, and the command then fails so
 * a deploy log shows it.
 */
class SeedContent extends Command
{
    protected $signature = 'app:seed-content';

    protected $description = 'Isi tabel konten (kurikulum, HSK, kata kerja, kosakata) dari storage/app/seed-data; aman dijalankan ulang';

    /** seeder => source files under storage/ */
    public const SEEDERS = [
        ReferenceDataSeeder::class => [ReferenceDataSeeder::DATA_PATH.'/mandarin_words.json'],
        MandarinHskSeeder::class => ['app/seed-data/mandarin_hsk1to6_complete.json'],
        IrregularVerbSeeder::class => ['app/seed-data/irregular_verbs_183_complete.json'],
        EnglishVocabSeeder::class => ['app/seed-data/english_vocab_cefr_10674.json'],
    ];

    public function handle(): int
    {
        $missing = [];

        foreach (self::SEEDERS as $seeder => $files) {
            $absent = array_values(array_filter($files, fn ($f) => ! is_file(storage_path($f))));
            if ($absent !== []) {
                $missing = [...$missing, ...$absent];
                $this->warn(class_basename($seeder).' dilewati: berkas storage/'.implode(', storage/', $absent).' tidak ada.');

                continue;
            }

            $this->info('Menjalankan '.class_basename($seeder).'…');
            $this->call('db:seed', ['--class' => $seeder, '--force' => true]);
        }

        if ($missing !== []) {
            $this->error('Sebagian konten belum diisi. Letakkan berkas yang hilang di storage/app/seed-data, lalu jalankan ulang perintah ini.');

            return self::FAILURE;
        }

        $this->info('Konten siap.');

        return self::SUCCESS;
    }
}
