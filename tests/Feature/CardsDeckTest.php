<?php

namespace Tests\Feature;

use App\Livewire\Cards\Deck;
use App\Models\IeltsVocab;
use App\Models\MandarinHskWord;
use App\Models\MandarinWord;
use App\Models\User;
use App\Models\UserWordProgress;
use Database\Seeders\EnglishVocabSeeder;
use Database\Seeders\IrregularVerbSeeder;
use Database\Seeders\MandarinHskSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\WithSmallVocabularyFixtures;
use Tests\TestCase;

class CardsDeckTest extends TestCase
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

    public function test_mandarin_deck_defaults_to_hsk1_with_150_cards(): void
    {
        Livewire::actingAs($this->onboardedUser())->test(Deck::class, ['deck' => 'mandarin'])
            ->assertSet('mandarinLevel', '1')
            ->assertCount('cards', 150);
    }

    public function test_mandarin_deck_hsk4_shows_only_hsk4_words(): void
    {
        $this->skipWithoutFullMandarinCorpus('menguji jumlah lengkap 598 kartu HSK4');

        $component = Livewire::actingAs($this->onboardedUser())->test(Deck::class, ['deck' => 'mandarin'])
            ->call('setMandarinLevel', '4');

        $cards = $component->get('cards');
        $this->assertCount(598, $cards);

        foreach ($cards as $card) {
            $this->assertEquals(4, $card->hsk_level_old);
        }
    }

    public function test_mandarin_deck_semua_shows_the_full_union(): void
    {
        $this->skipWithoutFullMandarinCorpus('menguji jumlah lengkap 4.991 kartu HSK semua level');

        $component = Livewire::actingAs($this->onboardedUser())->test(Deck::class, ['deck' => 'mandarin'])
            ->call('setMandarinLevel', 'all');

        $this->assertCount(4991, $component->get('cards'));
    }

    public function test_mandarin_deck_with_a_batch_filter_shows_only_that_batch(): void
    {
        $expectedCount = MandarinWord::where('batch_number', 1)->count();

        $component = Livewire::actingAs($this->onboardedUser())->test(Deck::class, ['deck' => 'mandarin'])
            ->set('batch', 1);

        $cards = $component->get('cards');
        $this->assertCount($expectedCount, $cards);
        $this->assertLessThan(4991, $cards->count()); // not the full union — see AC #1 of the detail-modal task
    }

    public function test_setting_the_mandarin_level_clears_a_batch_filter(): void
    {
        $this->skipWithoutFullMandarinCorpus('menguji jumlah lengkap 147 kartu HSK2');

        $component = Livewire::actingAs($this->onboardedUser())->test(Deck::class, ['deck' => 'mandarin'])
            ->set('batch', 1)
            ->call('setMandarinLevel', '2');

        $this->assertNull($component->get('batch'));
        $this->assertCount(147, $component->get('cards'));
    }

    public function test_vocab_deck_with_a_topic_filter_shows_only_that_topic(): void
    {
        $expectedCount = IeltsVocab::where('topic', 'Family & Hometown')->count();

        $component = Livewire::actingAs($this->onboardedUser())->test(Deck::class, ['deck' => 'vocab'])
            ->set('topic', 'Family & Hometown');

        $cards = $component->get('cards');
        $this->assertCount($expectedCount, $cards);
        foreach ($cards as $card) {
            $this->assertEquals('Family & Hometown', $card->topic);
        }
    }

    public function test_verb_deck_shows_183_cards_from_the_reference_table(): void
    {
        $this->skipWithoutFullVerbCorpus('menguji jumlah lengkap 183 kartu kata kerja');

        Livewire::actingAs($this->onboardedUser())->test(Deck::class, ['deck' => 'verb'])
            ->assertCount('cards', 183)
            ->assertSee('1 / 183', false);
    }

    public function test_verb_card_back_shows_all_three_forms(): void
    {
        Livewire::actingAs($this->onboardedUser())->test(Deck::class, ['deck' => 'verb'])
            ->call('flip')
            ->assertSee('→'); // base → past → past_participle separators, all on one visible line
    }

    public function test_marking_hsk1_card_known_does_not_affect_hsk2_cards(): void
    {
        $user = $this->onboardedUser();

        $component = Livewire::actingAs($user)->test(Deck::class, ['deck' => 'mandarin']);
        $hsk1Card = $component->get('cards')->first();
        $component->call('markKnown', true);

        $this->assertDatabaseHas('user_word_progress', [
            'user_id' => $user->id, 'word_type' => 'mandarin', 'word_id' => $hsk1Card->id, 'is_known' => true,
        ]);

        $hsk2Component = Livewire::actingAs($user)->test(Deck::class, ['deck' => 'mandarin'])
            ->call('setMandarinLevel', '2');

        // None of the HSK2 cards should show as known.
        $known = $hsk2Component->get('knownIds');
        foreach ($hsk2Component->get('cards') as $card) {
            $this->assertFalse($known->contains($card->id));
        }
    }

    public function test_hide_known_on_one_level_does_not_hide_cards_on_another_level(): void
    {
        $this->skipWithoutFullMandarinCorpus('menguji jumlah lengkap 1.298 kartu HSK5');

        $user = $this->onboardedUser();

        $component = Livewire::actingAs($user)->test(Deck::class, ['deck' => 'mandarin'])
            ->call('setMandarinLevel', '4');

        $totalHsk4 = $component->get('cards')->count();
        $firstCard = $component->get('cards')->first();

        $component->call('markKnown', true);
        $component->call('toggleHideKnown');

        $this->assertCount($totalHsk4 - 1, $component->get('cards'));

        // Switching to HSK5 with hideKnown still on should show its full, unaffected set.
        $component->call('setMandarinLevel', '5');
        $this->assertCount(1298, $component->get('cards'));
    }

    public function test_old_verb_progress_does_not_leak_onto_the_new_reference_deck(): void
    {
        $user = $this->onboardedUser();

        // Simulate stale progress from the old 35-verb curriculum deck (word_id=1).
        UserWordProgress::create([
            'user_id' => $user->id, 'word_type' => 'verb', 'word_id' => 1, 'is_known' => true,
        ]);

        $component = Livewire::actingAs($user)->test(Deck::class, ['deck' => 'verb']);
        $known = $component->get('knownIds');

        foreach ($component->get('cards') as $card) {
            $this->assertFalse($known->contains($card->id));
        }
    }

    /** Opens the Mandarin deck at the given level, positioned on the card for $hanzi. */
    private function deckAt(string $level, string $hanzi)
    {
        $component = Livewire::actingAs($this->onboardedUser())->test(Deck::class, ['deck' => 'mandarin'])
            ->call('setMandarinLevel', $level);

        $index = $component->get('cards')->search(fn ($card) => $card->hanzi === $hanzi);
        $this->assertNotFalse($index);

        return $component->set('index', $index);
    }

    public function test_corrected_card_shows_indonesian_meaning_and_a_draft_badge(): void
    {
        MandarinHskWord::create([
            'hanzi' => '测试正', 'pinyin' => 'zhèng', 'meaning_en' => 'surname Zheng',
            'meaning_id' => 'benar; lurus', 'data_status' => 'corrected_draft', 'hsk_level_old' => 3,
        ]);

        $this->deckAt('3', '测试正')
            ->assertSee('Data dikoreksi (draf)')
            ->assertSeeHtmlInOrder(['benar; lurus', 'surname Zheng']);
    }

    public function test_untranslated_surname_card_says_meaning_not_available_instead_of_the_surname(): void
    {
        MandarinHskWord::create([
            'hanzi' => '测试姓', 'pinyin' => 'Cè', 'meaning_en' => 'surname Ce', 'hsk_level_old' => 2,
        ]);

        $this->deckAt('2', '测试姓')
            ->assertSeeHtmlInOrder(['Arti belum tersedia', 'surname Ce'])
            ->assertDontSee('Data dikoreksi (draf)');
    }

    public function test_untranslated_card_falls_back_to_a_plain_english_gloss_as_the_main_meaning(): void
    {
        MandarinHskWord::create([
            'hanzi' => '测试听', 'pinyin' => 'tīng', 'meaning_en' => 'to hear test', 'hsk_level_old' => 2,
        ]);

        $this->deckAt('2', '测试听')
            ->assertSeeHtml('<p class="text-white text-lg">to hear test</p>')
            ->assertDontSee('Arti belum tersedia');
    }
}
