<?php

namespace Tests\Concerns;

trait WithSmallVocabularyFixtures
{
    /** @var array<string, bool> */
    protected array $temporarySeedFiles = [];

    protected static function hasFullMandarinCorpus(): bool
    {
        $path = storage_path('app/seed-data/mandarin_hsk1to6_complete.json');

        return is_file($path) && filesize($path) > 500_000;
    }

    protected static function hasFullVerbCorpus(): bool
    {
        $path = storage_path('app/seed-data/irregular_verbs_183_complete.json');
        if (! is_file($path)) {
            return false;
        }
        $data = json_decode(file_get_contents($path), true);

        return is_array($data) && count($data) >= 183;
    }

    protected static function hasFullEnglishCorpus(): bool
    {
        $path = storage_path('app/seed-data/english_vocab_cefr_10674.json');

        return is_file($path) && filesize($path) > 1_000_000;
    }

    protected static function hasFullVocabularyCorpus(): bool
    {
        return static::hasFullMandarinCorpus()
            && static::hasFullVerbCorpus()
            && static::hasFullEnglishCorpus();
    }

    protected function skipWithoutFullVocabularyCorpus(string $reason = ''): void
    {
        if (! static::hasFullVocabularyCorpus()) {
            $msg = 'Berkas korpus penuh storage/app/seed-data/{mandarin_hsk1to6_complete,irregular_verbs_183_complete,english_vocab_cefr_10674}.json tidak ditemukan. Ambil berkas dari riwayat git ke storage/app/seed-data/ (lihat README, bagian app:seed-content) untuk menguji seluruh korpus.';
            if ($reason !== '') {
                $msg .= ' ('.$reason.')';
            }
            $this->markTestSkipped($msg);
        }
    }

    protected function skipWithoutFullMandarinCorpus(string $reason = ''): void
    {
        if (! static::hasFullMandarinCorpus()) {
            $msg = 'Berkas korpus penuh storage/app/seed-data/mandarin_hsk1to6_complete.json tidak ditemukan. Ambil berkas dari riwayat git ke storage/app/seed-data/ (lihat README, bagian app:seed-content) untuk menguji seluruh korpus Mandarin.';
            if ($reason !== '') {
                $msg .= ' ('.$reason.')';
            }
            $this->markTestSkipped($msg);
        }
    }

    protected function skipWithoutFullVerbCorpus(string $reason = ''): void
    {
        if (! static::hasFullVerbCorpus()) {
            $msg = 'Berkas korpus penuh storage/app/seed-data/irregular_verbs_183_complete.json tidak ditemukan. Ambil berkas dari riwayat git ke storage/app/seed-data/ (lihat README, bagian app:seed-content) untuk menguji seluruh korpus kata kerja tak beraturan.';
            if ($reason !== '') {
                $msg .= ' ('.$reason.')';
            }
            $this->markTestSkipped($msg);
        }
    }

    protected function skipWithoutFullEnglishCorpus(string $reason = ''): void
    {
        if (! static::hasFullEnglishCorpus()) {
            $msg = 'Berkas korpus penuh storage/app/seed-data/english_vocab_cefr_10674.json tidak ditemukan. Ambil berkas dari riwayat git ke storage/app/seed-data/ (lihat README, bagian app:seed-content) untuk menguji seluruh korpus kosakata bahasa Inggris.';
            if ($reason !== '') {
                $msg .= ' ('.$reason.')';
            }
            $this->markTestSkipped($msg);
        }
    }

    protected function useSmallVocabularyFixtures(): void
    {
        $fixtureDir = dirname(__DIR__).'/Fixtures/vocabulary';

        $mapping = [
            'mandarin_hsk1to6_complete.json' => $fixtureDir.'/mandarin_hsk_small.json',
            'irregular_verbs_183_complete.json' => $fixtureDir.'/irregular_verbs_small.json',
            'english_vocab_cefr_10674.json' => $fixtureDir.'/english_vocab_small.json',
        ];

        $targetDirs = array_unique([
            storage_path('app/seed-data'),
            dirname(__DIR__, 2).'/storage/app/seed-data',
        ]);

        foreach ($targetDirs as $dir) {
            if (! is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }

            foreach ($mapping as $targetName => $fixturePath) {
                $targetPath = $dir.'/'.$targetName;
                if (! file_exists($targetPath)) {
                    copy($fixturePath, $targetPath);
                    $this->temporarySeedFiles[$targetPath] = true;
                } else {
                    $this->temporarySeedFiles[$targetPath] = false;
                }
            }
        }

        $this->beforeApplicationDestroyed(function () {
            $this->removeSmallVocabularyFixtures();
        });
    }

    protected function removeSmallVocabularyFixtures(): void
    {
        foreach ($this->temporarySeedFiles as $targetPath => $isTemporary) {
            if ($isTemporary && file_exists($targetPath)) {
                @unlink($targetPath);
            }
        }
        $this->temporarySeedFiles = [];
    }
}
