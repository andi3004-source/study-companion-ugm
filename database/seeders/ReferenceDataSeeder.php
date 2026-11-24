<?php

namespace Database\Seeders;

use App\Models\IrregularVerb;
use App\Models\MandarinWord;
use App\Models\WeekTopic;
use App\Services\AnswerChecker;
use Illuminate\Database\Seeder;

/**
 * Curriculum and reference tables from storage/app/seed-data/reference/*.json.
 * Idempotent: a table that already has rows is left alone (the ids are
 * referenced by user progress, so they are never truncated and re-inserted).
 */
class ReferenceDataSeeder extends Seeder
{
    public const DATA_PATH = 'app/seed-data/reference';

    protected string $dataPath;

    public function __construct()
    {
        $this->dataPath = storage_path(self::DATA_PATH);
    }

    protected function loadJson(string $file): mixed
    {
        return json_decode(file_get_contents($this->dataPath.'/'.$file), true, 512, JSON_THROW_ON_ERROR);
    }

    public function run(): void
    {
        $tables = [
            MandarinWord::class => fn () => $this->seedMandarinWords(),
            IrregularVerb::class => fn () => $this->seedIrregularVerbs(),
            WeekTopic::class => fn () => $this->seedWeekTopics(),
        ];

        foreach ($tables as $model => $seed) {
            if ($model::query()->exists()) {
                continue;
            }
            $seed();
        }
    }

    protected function seedMandarinWords(): void
    {
        $words = $this->loadJson('mandarin_words.json');
        $english = $this->loadJson('mandarin_words_en.json');
        $batches = $this->loadJson('mandarin_batches.json');

        $checker = new AnswerChecker;

        $rows = [];
        foreach ($batches as $batchIndex => [$start, $end]) {
            $batchNumber = $batchIndex + 1;
            $orderInBatch = 1;
            for ($i = $start; $i <= $end; $i++) {
                $wordIndex = $i - 1; // 1-indexed range -> 0-indexed array
                [$hanzi, $pinyin, $meaningId] = $words[$wordIndex];
                $rows[] = [
                    'hanzi' => $hanzi,
                    'pinyin' => $pinyin,
                    'pinyin_normalized' => $checker->normalizePinyin($pinyin),
                    'meaning_id' => $meaningId,
                    'meaning_en' => $english[$wordIndex],
                    'batch_number' => $batchNumber,
                    'order_in_batch' => $orderInBatch++,
                    'hsk_level_old' => 1,
                    'hsk_level_new' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        MandarinWord::insert($rows);
    }

    protected function seedIrregularVerbs(): void
    {
        $verbs = $this->loadJson('irregular_verbs.json');
        $rows = [];
        foreach ($verbs as $i => [$base, $past, $pastParticiple, $meaningId, $example]) {
            $batchNumber = intdiv($i, 5) + 1;
            $orderInBatch = ($i % 5) + 1;
            $rows[] = [
                'base_form' => $base,
                'past_form' => $past,
                'past_participle' => $pastParticiple,
                'meaning_id' => $meaningId,
                'example_sentence' => $example,
                'batch_number' => $batchNumber,
                'order_in_batch' => $orderInBatch,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        IrregularVerb::insert($rows);
    }

    protected function seedWeekTopics(): void
    {
        $weeks = $this->loadJson('week_topics.json');
        $rows = [];
        foreach ($weeks as $weekIndex => $topics) {
            foreach ($topics as $topicOrder => $topicName) {
                $rows[] = [
                    'week_number' => $weekIndex + 1,
                    'topic_order' => $topicOrder,
                    'topic_name' => $topicName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        WeekTopic::insert($rows);
    }
}
