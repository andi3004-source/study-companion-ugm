<?php

namespace Tests\Feature;

use App\Models\MandarinWord;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RepairHskCurriculumTest extends TestCase
{
    use RefreshDatabase;

    /** Reproduces what the old MandarinHskSeeder wrote over the curated rows. */
    private function corrupt(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $broken = [
            '个' => ['gě', 'ge', 'used in 自个儿'],
            '都' => ['Dū', 'du', 'surname Du'],
            '听' => ['yǐn', 'yin', 'smile (archaic)'],
            '冷' => ['Lěng', 'leng', 'surname Leng'],
            '爸爸' => ['bà ba', 'baba', 'father'],
        ];

        foreach ($broken as $hanzi => [$pinyin, $normalized, $meaningEn]) {
            MandarinWord::where('hanzi', $hanzi)->update([
                'pinyin' => $pinyin,
                'pinyin_normalized' => $normalized,
                'meaning_en' => $meaningEn,
            ]);
        }
    }

    public function test_dry_run_reports_changes_without_writing_anything(): void
    {
        $this->corrupt();

        $this->artisan('hsk:repair-curriculum', ['--dry-run' => true])
            ->expectsOutputToContain('5 row(s) would be repaired')
            ->assertSuccessful();

        $this->assertSame('Dū', MandarinWord::where('hanzi', '都')->value('pinyin'));
    }

    public function test_restores_curated_pinyin_normalized_and_meaning_en(): void
    {
        $this->corrupt();

        $this->artisan('hsk:repair-curriculum')
            ->expectsOutputToContain('5 row(s) repaired')
            ->assertSuccessful();

        $expected = [
            '个' => ['gè', 'ge', '(general measure word)'],
            '都' => ['dōu', 'dou', 'all / also'],
            '听' => ['tīng', 'ting', 'to listen'],
            // capitalised "surname" reading back to the common lowercase word
            '冷' => ['lěng', 'leng', 'cold'],
            '爸爸' => ['bàba', 'baba', 'father / dad'],
        ];

        foreach ($expected as $hanzi => [$pinyin, $normalized, $meaningEn]) {
            $word = MandarinWord::where('hanzi', $hanzi)->first();
            $this->assertSame($pinyin, $word->pinyin, "pinyin for {$hanzi}");
            $this->assertSame($normalized, $word->pinyin_normalized, "pinyin_normalized for {$hanzi}");
            $this->assertSame($meaningEn, $word->meaning_en, "meaning_en for {$hanzi}");
        }
    }

    public function test_keeps_row_identity_and_user_progress(): void
    {
        $this->corrupt();
        $user = User::factory()->create();

        $before = MandarinWord::where('hanzi', '都')->first();
        DB::table('user_word_progress')->insert(['user_id' => $user->id, 'word_type' => 'mandarin', 'word_id' => $before->id, 'is_known' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('hsk:repair-curriculum')->assertSuccessful();

        $after = MandarinWord::find($before->id);
        $this->assertSame($before->batch_number, $after->batch_number);
        $this->assertSame($before->order_in_batch, $after->order_in_batch);
        $this->assertSame($before->meaning_id, $after->meaning_id);
        $this->assertSame(150, MandarinWord::count());
        $this->assertEquals(1, DB::table('user_word_progress')->where('word_id', $before->id)->where('word_type', 'mandarin')->value('is_known'));
    }

    public function test_is_idempotent(): void
    {
        $this->corrupt();

        $this->artisan('hsk:repair-curriculum')->assertSuccessful();

        $this->artisan('hsk:repair-curriculum')
            ->expectsOutputToContain('Nothing to repair')
            ->assertSuccessful();
    }
}
