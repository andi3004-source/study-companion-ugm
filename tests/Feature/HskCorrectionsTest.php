<?php

namespace Tests\Feature;

use App\Models\MandarinHskWord;
use Database\Seeders\MandarinHskSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithSmallVocabularyFixtures;
use Tests\TestCase;

/**
 * Uses tests/Fixtures/hsk_corrections.json (a few real wrong-homograph HSK2-3
 * entries, one HSK1 entry that must be ignored, and one that matches
 * nothing) instead of the real corrections file — phpunit.xml blanks
 * HSK_CORRECTIONS_PATH so no other test depends on that file's contents.
 */
class HskCorrectionsTest extends TestCase
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

    private function useFixture(): void
    {
        config(['hsk.corrections_path' => base_path('tests/Fixtures/hsk_corrections.json')]);
    }

    /** The table as seeded before any correction existed, plus a row that takes 白's corrected pinyin. */
    private function seedUncorrected(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(MandarinHskSeeder::class);

        MandarinHskWord::create([
            'hanzi' => '白', 'pinyin' => 'bái', 'pinyin_normalized' => 'bai',
            'meaning_en' => 'white', 'hsk_level_old' => 2,
        ]);
    }

    private function word(string $hanzi, string $pinyin): ?MandarinHskWord
    {
        return MandarinHskWord::where('hanzi', $hanzi)->where('pinyin', $pinyin)->first();
    }

    public function test_dry_run_reports_counts_without_writing_anything(): void
    {
        $this->seedUncorrected();
        $this->useFixture();

        $this->artisan('hsk:apply-corrections', ['--dry-run' => true])
            ->expectsOutputToContain('Matched: 5, would update: 4, collisions: 1, not found: 1. Dry run: nothing written.')
            ->assertSuccessful();

        $this->assertNotNull($this->word('也', 'Yě'));
        $this->assertSame(0, MandarinHskWord::whereNotNull('data_status')->count());
    }

    public function test_applies_corrections_matched_by_hanzi_and_original_pinyin(): void
    {
        $this->seedUncorrected();
        $this->useFixture();
        $before = $this->word('也', 'Yě');

        $this->artisan('hsk:apply-corrections')
            ->expectsOutputToContain('Matched: 5, updated: 4, collisions: 1, not found: 1.')
            ->assertSuccessful();

        $after = MandarinHskWord::find($before->id);
        $this->assertSame('yě', $after->pinyin);
        $this->assertSame('ye', $after->pinyin_normalized);
        $this->assertSame('juga', $after->meaning_id);
        $this->assertSame('corrected_draft', $after->data_status);
        $this->assertSame('surname Ye', $after->meaning_en); // English gloss kept as-is, only shown as secondary detail

        // Matched even though the fixture says HSK3 and the dataset says HSK2; level isn't changed.
        $zhang = $this->word('张', 'zhāng');
        $this->assertSame('lembar (kata bantu)', $zhang->meaning_id);
        $this->assertSame(2, $zhang->hsk_level_old);

        $this->assertNull($this->word('也', 'Yě'));
        $this->assertNull($this->word('花', 'Huā'));
    }

    public function test_collision_is_reported_and_skipped_without_deleting_anything(): void
    {
        $this->seedUncorrected();
        $this->useFixture();
        $count = MandarinHskWord::count();

        $this->artisan('hsk:apply-corrections')
            ->expectsOutputToContain('Collisions')
            ->assertSuccessful();

        $this->assertSame($count, MandarinHskWord::count());
        $this->assertNull($this->word('白', 'Bái')->data_status);
        $this->assertNull($this->word('白', 'bái')->data_status);
    }

    public function test_hsk1_entries_are_ignored(): void
    {
        $this->seedUncorrected();
        $this->useFixture();

        $this->artisan('hsk:apply-corrections')->assertSuccessful();

        $this->assertSame(0, MandarinHskWord::where('hanzi', '都')->count());
    }

    public function test_is_idempotent(): void
    {
        $this->seedUncorrected();
        $this->useFixture();

        $this->artisan('hsk:apply-corrections')->assertSuccessful();

        $this->artisan('hsk:apply-corrections')
            ->expectsOutputToContain('Matched: 5, updated: 0, collisions: 1, not found: 1.')
            ->assertSuccessful();
    }

    public function test_fails_clearly_when_the_corrections_file_is_missing(): void
    {
        config(['hsk.corrections_path' => base_path('tests/Fixtures/does_not_exist.json')]);

        $this->artisan('hsk:apply-corrections')
            ->expectsOutputToContain('Corrections file not found')
            ->assertFailed();
    }

    public function test_fresh_seed_inserts_corrected_entries_instead_of_the_wrong_homograph(): void
    {
        $this->useFixture();

        $this->seed(ReferenceDataSeeder::class);
        $this->seed(MandarinHskSeeder::class);
        $this->seed(MandarinHskSeeder::class); // re-seeding must not bring the wrong row back

        $expectedCount = static::hasFullMandarinCorpus() ? 4841 : 10;
        $this->assertSame($expectedCount, MandarinHskWord::count());
        $this->assertNull($this->word('也', 'Yě'));

        $ye = $this->word('也', 'yě');
        $this->assertSame('ye', $ye->pinyin_normalized);
        $this->assertSame('juga', $ye->meaning_id);
        $this->assertSame('corrected_draft', $ye->data_status);

        // Already consistent with the corrections file: nothing for the command to do.
        $this->artisan('hsk:apply-corrections')
            ->expectsOutputToContain('updated: 0, collisions: 0, not found: 1.')
            ->assertSuccessful();
    }

    public function test_reseeding_an_uncorrected_table_does_not_add_corrected_duplicates(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(MandarinHskSeeder::class);

        $this->useFixture();
        $this->seed(MandarinHskSeeder::class);

        // The wrong row is left for hsk:apply-corrections; no second (也, yě) row is inserted next to it.
        $expectedCount = static::hasFullMandarinCorpus() ? 4841 : 10;
        $this->assertSame($expectedCount, MandarinHskWord::count());
        $this->assertNull($this->word('也', 'yě'));

        $this->artisan('hsk:apply-corrections')
            ->expectsOutputToContain('updated: 5, collisions: 0, not found: 1.')
            ->assertSuccessful();

        $this->assertSame($expectedCount, MandarinHskWord::count());
    }
}
