<?php

namespace Tests\Feature;

use App\Models\EnglishVocab;
use App\Models\IrregularVerb;
use App\Models\IrregularVerbReference;
use App\Models\MandarinHskWord;
use App\Models\MandarinWord;
use App\Services\AnswerChecker;
use Database\Seeders\EnglishVocabSeeder;
use Database\Seeders\IrregularVerbSeeder;
use Database\Seeders\MandarinHskSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\WithSmallVocabularyFixtures;
use Tests\TestCase;

class VocabularyImportTest extends TestCase
{
    use RefreshDatabase, WithSmallVocabularyFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSmallVocabularyFixtures();
    }

    protected function tearDown(): void
    {
        $this->removeSmallVocabularyFixtures();
        parent::tearDown();
    }

    public function test_migrations_add_expected_schema(): void
    {
        foreach (['traditional', 'radical', 'frequency', 'classifiers'] as $column) {
            $this->assertTrue(Schema::hasColumn('mandarin_words', $column));
        }

        $this->assertTrue(Schema::hasTable('mandarin_hsk_words'));
        $this->assertTrue(Schema::hasTable('irregular_verbs_reference'));
        $this->assertTrue(Schema::hasTable('english_vocab'));

        // The existing curriculum table must be untouched.
        $this->assertTrue(Schema::hasColumn('irregular_verbs', 'past_participle'));
    }

    public function test_seeders_import_expected_row_counts(): void
    {
        $this->skipWithoutFullVocabularyCorpus('menguji jumlah baris korpus penuh 10.674/183/4.841');

        $this->seed(ReferenceDataSeeder::class);
        $this->seed(MandarinHskSeeder::class);
        $this->seed(IrregularVerbSeeder::class);
        $this->seed(EnglishVocabSeeder::class);

        $this->assertSame(150, MandarinWord::count());
        $this->assertSame(4841, MandarinHskWord::count());
        $this->assertSame(35, IrregularVerb::count()); // existing curriculum, unchanged
        $this->assertSame(183, IrregularVerbReference::count());
        $this->assertSame(10674, EnglishVocab::count());

        // AC5: exact HSK3 row count.
        $this->assertSame(298, MandarinHskWord::where('hsk_level_old', 3)->count());

        // AC6: english_vocab is queryable by cefr_level.
        $this->assertSame(1071, EnglishVocab::where('cefr_level', 'A1')->count());
    }

    public function test_seeders_are_idempotent_on_second_run(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(MandarinHskSeeder::class);
        $this->seed(IrregularVerbSeeder::class);
        $this->seed(EnglishVocabSeeder::class);

        $expectedHsk = static::hasFullMandarinCorpus() ? 4841 : MandarinHskWord::count();
        $expectedVerb = static::hasFullVerbCorpus() ? 183 : IrregularVerbReference::count();
        $expectedEnglish = static::hasFullEnglishCorpus() ? 10674 : EnglishVocab::count();

        $this->seed(MandarinHskSeeder::class);
        $this->seed(IrregularVerbSeeder::class);
        $this->seed(EnglishVocabSeeder::class);

        $this->assertSame(150, MandarinWord::count());
        $this->assertSame($expectedHsk, MandarinHskWord::count());
        $this->assertSame($expectedVerb, IrregularVerbReference::count());
        $this->assertSame($expectedEnglish, EnglishVocab::count());
    }

    public function test_hsk1_curriculum_rows_keep_identity_but_get_enriched_content(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $before = MandarinWord::where('hanzi', '爱')->first();
        $this->assertNotNull($before);
        $this->assertNull($before->traditional);

        $this->seed(MandarinHskSeeder::class);

        $after = MandarinWord::find($before->id);
        $this->assertSame($before->id, $after->id);
        $this->assertSame($before->batch_number, $after->batch_number);
        $this->assertSame($before->order_in_batch, $after->order_in_batch);
        $this->assertSame($before->meaning_id, $after->meaning_id); // curated translation preserved
        $this->assertNotNull($after->traditional); // enriched from the new dataset
        $this->assertSame('爱', $after->hanzi);
    }

    public function test_unmatched_curated_word_is_left_untouched(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        // "没有" has no exact match in the new HSK1 dataset (different HSK
        // standard/variant), so it must survive the import unchanged.
        $before = MandarinWord::where('hanzi', '没有')->first();
        $this->assertNotNull($before);

        $this->seed(MandarinHskSeeder::class);

        $after = MandarinWord::find($before->id);
        $this->assertNull($after->traditional);
        $this->assertSame($before->meaning_en, $after->meaning_en);
    }

    public function test_hsk_seeder_keeps_curated_pinyin_instead_of_the_datasets_wrong_homographs(): void
    {
        // mandarin_hsk1to6_complete.json picks the wrong reading for some
        // common HSK1 words (个 "gě, used in 自个儿", 都 "Dū, surname Du", 听
        // "yǐn, smile (archaic)", ...). The curated curriculum data is right,
        // and must survive the HSK import.
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(MandarinHskSeeder::class);

        $expected = [
            '个' => ['gè', 'ge'],
            '看' => ['kàn', 'kan'],
            '都' => ['dōu', 'dou'],
            '吗' => ['ma', 'ma'],
            '听' => ['tīng', 'ting'],
            '读' => ['dú', 'du'],
        ];

        $checker = new AnswerChecker;

        foreach ($expected as $hanzi => [$pinyin, $normalized]) {
            $word = MandarinWord::where('hanzi', $hanzi)->first();
            $this->assertSame($pinyin, $word->pinyin, "pinyin for {$hanzi}");
            $this->assertSame($normalized, $word->pinyin_normalized, "pinyin_normalized for {$hanzi}");
            // Latihan Isian checks against the stored pinyin: a correct answer must be accepted.
            $this->assertTrue($checker->pinyinMatches($normalized, $word->pinyin), "Latihan Isian answer for {$hanzi}");
        }
    }

    public function test_seeding_alone_never_triggers_translation(): void
    {
        // Translating meaning_id spends AI quota and is explicitly opt-in via
        // `php artisan translate:meanings` (see TranslateMeanings) — it must
        // never fire as a side effect of seeding, whether or not
        // GEMINI_API_KEY happens to be configured (it is, in this app's real
        // .env, for the Conversation Partner feature — if seeding ever
        // dispatched the translation jobs again, this would start making
        // real Gemini calls on every test that seeds reference data).
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(MandarinHskSeeder::class);
        $this->seed(EnglishVocabSeeder::class);

        $this->assertSame(0, MandarinHskWord::whereNotNull('meaning_id')->count());
        $this->assertSame(0, EnglishVocab::whereJsonContainsKey('translations->id')->count());
    }
}
