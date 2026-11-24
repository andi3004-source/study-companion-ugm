<?php

namespace Tests\Feature;

use App\Livewire\Kamus\Search;
use App\Models\MandarinHskWord;
use App\Models\User;
use Database\Seeders\EnglishVocabSeeder;
use Database\Seeders\IrregularVerbSeeder;
use Database\Seeders\MandarinHskSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\WithSmallVocabularyFixtures;
use Tests\TestCase;

class KamusSearchTest extends TestCase
{
    use RefreshDatabase, WithSmallVocabularyFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSmallVocabularyFixtures();

        $this->seed(ReferenceDataSeeder::class);
        $this->seed(MandarinHskSeeder::class);
        $this->seed(IrregularVerbSeeder::class);
        $this->seed(EnglishVocabSeeder::class);
    }

    protected function tearDown(): void
    {
        $this->removeSmallVocabularyFixtures();
        parent::tearDown();
    }

    protected function onboardedUser(): User
    {
        return User::factory()->create([
            'start_date' => now()->subDays(2)->toDateString(),
            'track' => 'both',
            'onboarded' => true,
        ]);
    }

    public function test_mandarin_filter_with_no_search_shows_the_full_hsk1to6_count(): void
    {
        $this->skipWithoutFullMandarinCorpus('menguji jumlah lengkap 4.991 kata HSK');

        Livewire::actingAs($this->onboardedUser())->test(Search::class)
            ->set('filterLang', 'mandarin')
            ->assertSet('totalCount', 4991);
    }

    public function test_mandarin_hsk6_level_filter_shows_only_hsk6_words(): void
    {
        $this->skipWithoutFullMandarinCorpus('menguji jumlah lengkap 2.500 kata HSK6');

        $component = Livewire::actingAs($this->onboardedUser())->test(Search::class)
            ->set('filterLang', 'mandarin')
            ->set('filterLevel', '6');

        $component->assertSet('totalCount', 2500);

        foreach ($component->get('results') as $row) {
            $this->assertSame(6, $row->raw_level);
        }
    }

    public function test_english_filter_with_no_search_shows_cefr_plus_verbs(): void
    {
        $this->skipWithoutFullVocabularyCorpus('menguji jumlah lengkap 10.706 kata CEFR dan kata kerja');

        // 10674 CEFR + 32 irregular verbs with no english_vocab entry of
        // their own. The other 151 irregular verbs are already CEFR words
        // (e.g. "eat"), so their V1/V2/V3 rides along on that same row
        // instead of appearing a second time as a standalone verb result.
        Livewire::actingAs($this->onboardedUser())->test(Search::class)
            ->set('filterLang', 'english')
            ->assertSet('totalCount', 10706);
    }

    public function test_verb_in_both_tables_shows_a_single_merged_result_with_conjugation(): void
    {
        // "eat" also matches ~800 definitions/translations containing that
        // substring (e.g. "creates"), so the exact word isn't guaranteed to
        // land in the default 50-row page — bump the limit instead of
        // relying on alphabetical luck.
        $component = Livewire::actingAs($this->onboardedUser())->test(Search::class)
            ->set('filterLang', 'english')
            ->set('query', 'eat')
            ->set('limit', 2000);

        $rows = $component->get('results')->filter(fn ($row) => strtolower($row->headword) === 'eat');

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame('cefr', $row->source);
        $this->assertTrue($row->is_verb);
        $this->assertSame('ate', $row->verb_past);
        $this->assertSame('eaten', $row->verb_past_participle);
        $this->assertNotNull($row->example_en);
    }

    public function test_regular_verb_shows_a_beraturan_badge_instead_of_conjugation(): void
    {
        $component = Livewire::actingAs($this->onboardedUser())->test(Search::class)
            ->set('filterLang', 'english')
            ->set('query', 'study')
            ->set('limit', 2000);

        $row = $component->get('results')->first(fn ($row) => strtolower($row->headword) === 'study');

        $this->assertNotNull($row);
        $this->assertTrue($row->is_verb);
        $this->assertNull($row->verb_past);

        $component->assertSee('Kata kerja beraturan');
    }

    public function test_non_verb_word_shows_no_conjugation_section(): void
    {
        $component = Livewire::actingAs($this->onboardedUser())->test(Search::class)
            ->set('filterLang', 'english')
            ->set('query', 'beautiful');

        $row = $component->get('results')->first(fn ($row) => strtolower($row->headword) === 'beautiful');

        $this->assertNotNull($row);
        $this->assertFalse($row->is_verb);

        $component->assertDontSee('Kata kerja beraturan');
    }

    public function test_orphan_irregular_verb_still_appears_with_conjugation(): void
    {
        // "dwell" isn't in english_vocab at all, so it must still surface
        // via the standalone irregular_verbs_reference branch.
        $component = Livewire::actingAs($this->onboardedUser())->test(Search::class)
            ->set('filterLang', 'english')
            ->set('query', 'dwell');

        $row = $component->get('results')->first(fn ($row) => strtolower($row->headword) === 'dwell');

        $this->assertNotNull($row);
        $this->assertSame('verb', $row->source);
        $this->assertSame('dwelt', $row->verb_past);
    }

    public function test_english_b2_level_filter_excludes_verbs(): void
    {
        $this->skipWithoutFullEnglishCorpus('menguji jumlah lengkap 2.253 kata B2');

        $component = Livewire::actingAs($this->onboardedUser())->test(Search::class)
            ->set('filterLang', 'english')
            ->set('filterLevel', 'B2');

        $component->assertSet('totalCount', 2253);

        foreach ($component->get('results') as $row) {
            $this->assertSame('cefr', $row->source);
            $this->assertSame('B2', $row->raw_level);
        }
    }

    public function test_tone_insensitive_search_combines_with_level_filter(): void
    {
        $component = Livewire::actingAs($this->onboardedUser())->test(Search::class)
            ->set('filterLang', 'mandarin')
            ->set('filterLevel', '3')
            ->set('query', 'hao'); // no tone marks

        $results = $component->get('results');
        $this->assertGreaterThanOrEqual(1, $results->count());

        foreach ($results as $row) {
            $this->assertSame(3, $row->raw_level);
        }
    }

    public function test_search_updates_results_via_livewire_without_a_full_reload(): void
    {
        Livewire::actingAs($this->onboardedUser())->test(Search::class)
            ->set('filterLang', 'mandarin')
            ->set('query', 'ài')
            ->assertSee('爱');
    }

    public function test_kamus_page_loads_quickly_with_the_full_dataset(): void
    {
        $this->skipWithoutFullVocabularyCorpus('menguji kecepatan halaman kamus dengan seluruh korpus');

        $user = $this->onboardedUser();

        $start = microtime(true);
        $this->actingAs($user)->get('/kamus')->assertOk();
        $elapsedMs = (microtime(true) - $start) * 1000;

        $this->assertLessThan(1000, $elapsedMs, "Kamus page took {$elapsedMs}ms to load");
    }

    public function test_results_are_capped_and_load_more_extends_them(): void
    {
        $this->skipWithoutFullMandarinCorpus('menguji batas hasil 50 dan 100 dengan korpus penuh');

        $component = Livewire::actingAs($this->onboardedUser())->test(Search::class)
            ->set('filterLang', 'mandarin');

        $this->assertCount(50, $component->get('results'));

        $component->call('loadMore');
        $this->assertCount(100, $component->get('results'));
    }

    public function test_mandarin_results_expose_both_indonesian_and_english_meaning_separately(): void
    {
        // Search by the curated Indonesian meaning itself ("cinta") rather than
        // a pinyin syllable — "ai"/"ài" alone matches 700+ words, and with the
        // default 50-row limit + alphabetical-by-hanzi ordering, 爱 isn't
        // guaranteed to be in the first page. Searching its own meaning is a
        // much more selective match.
        $component = Livewire::actingAs($this->onboardedUser())->test(Search::class)
            ->set('filterLang', 'mandarin')
            ->set('query', 'cinta');

        $row = $component->get('results')->firstWhere('headword', '爱');

        $this->assertNotNull($row);
        $this->assertSame('cinta / suka', $row->meaning_id); // curated Indonesian, not just an English fallback
        $this->assertNotNull($row->meaning_en);
        $this->assertNotSame($row->meaning_id, $row->meaning_en);
    }

    public function test_kamus_page_renders_both_indonesian_and_english_meaning_lines(): void
    {
        // Filtered + searched so a curriculum word with a real curated
        // Indonesian meaning (unlike most of the untranslated CEFR/HSK
        // reference data) is guaranteed to be on the page.
        Livewire::actingAs($this->onboardedUser())->test(Search::class)
            ->set('filterLang', 'mandarin')
            ->set('query', 'cinta')
            ->assertSee('🇮🇩', false)
            ->assertSee('🇬🇧', false);
    }

    public function test_indonesian_meaning_search_does_not_match_a_substring_inside_an_unrelated_word(): void
    {
        // "ayo" used to match meaning_en values like "layout" and "...as a
        // mayor..." purely because those words happen to contain the letters
        // "ayo" somewhere inside them — none of them mean "ayo". Meaning
        // search must require a whole-word match, not a substring anywhere.
        $component = Livewire::actingAs($this->onboardedUser())->test(Search::class)
            ->set('filterLang', 'mandarin')
            ->set('query', 'ayo');

        $this->assertCount(0, $component->get('results')->filter(function ($row) {
            $en = strtolower((string) $row->meaning_en);

            return str_contains($en, 'layout') || str_contains($en, 'mayor');
        }));

        foreach ($component->get('results') as $row) {
            $this->assertStringNotContainsString('layout', strtolower((string) $row->meaning_en));
            $this->assertStringNotContainsString('mayor', strtolower((string) $row->meaning_en));
        }
    }

    public function test_changing_language_resets_the_level_filter(): void
    {
        Livewire::actingAs($this->onboardedUser())->test(Search::class)
            ->set('filterLang', 'mandarin')
            ->set('filterLevel', '3')
            ->set('filterLang', 'english')
            ->assertSet('filterLevel', 'all');
    }

    public function test_surname_gloss_is_only_a_secondary_detail_never_the_main_meaning(): void
    {
        MandarinHskWord::create([
            'hanzi' => '测试姓', 'pinyin' => 'Cè', 'pinyin_normalized' => 'ce',
            'meaning_en' => 'surname Ce', 'hsk_level_old' => 2,
        ]);

        Livewire::actingAs($this->onboardedUser())->test(Search::class)
            ->set('query', '测试姓')
            ->assertSeeHtmlInOrder(['Arti belum tersedia', '🇬🇧 surname Ce'])
            ->assertDontSeeHtml('<p class="text-sm text-ink">🇬🇧 surname Ce</p>')
            ->assertDontSee('Data dikoreksi (draf)');
    }

    public function test_corrected_entry_shows_indonesian_meaning_first_and_a_draft_badge(): void
    {
        MandarinHskWord::create([
            'hanzi' => '测试正', 'pinyin' => 'zhèng', 'pinyin_normalized' => 'zheng',
            'meaning_en' => 'surname Zheng', 'meaning_id' => 'benar; lurus',
            'data_status' => 'corrected_draft', 'hsk_level_old' => 3,
        ]);

        Livewire::actingAs($this->onboardedUser())->test(Search::class)
            ->set('query', '测试正')
            ->assertSee('Data dikoreksi (draf)')
            ->assertSeeHtmlInOrder(['🇮🇩 benar; lurus', '🇬🇧 surname Zheng']);
    }
}
