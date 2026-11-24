<?php

namespace Tests\Feature;

use App\Livewire\Cards\Deck;
use App\Livewire\Conversation\Picker;
use App\Livewire\Dashboard;
use App\Livewire\Kamus\Search;
use App\Livewire\LatihanIsian;
use App\Livewire\PronunciationCheck;
use App\Models\User;
use Database\Seeders\EnglishVocabSeeder;
use Database\Seeders\IrregularVerbSeeder;
use Database\Seeders\MandarinHskSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\WithSmallVocabularyFixtures;
use Tests\TestCase;

class AccessibilityFixesTest extends TestCase
{
    use RefreshDatabase, WithSmallVocabularyFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSmallVocabularyFixtures();
        $this->seed(ReferenceDataSeeder::class);
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

    public function test_flip_card_is_keyboard_operable_and_speaker_has_a_name(): void
    {
        $this->seed(MandarinHskSeeder::class);

        Livewire::actingAs($this->onboardedUser())->test(Deck::class, ['deck' => 'mandarin'])
            ->assertSeeHtml('role="button" tabindex="0"')
            ->assertSeeHtml('wire:keydown.enter.prevent="flip"')
            ->assertSeeHtml('wire:keydown.space.prevent="flip"')
            ->assertSeeHtml('aria-label="Dengarkan')
            ->assertSeeHtml('aria-pressed="true"')
            ->call('flip')
            ->assertSee('Kartu dibalik, sisi belakang ditampilkan.');
    }

    public function test_dashboard_checklist_toggle_and_day_picker_are_named(): void
    {
        Livewire::actingAs($this->onboardedUser())->test(Dashboard::class)
            ->assertSeeHtml('aria-label="Tandai selesai:')
            ->assertSeeHtml('aria-pressed="false"')
            ->set('showDayPicker', true)
            ->assertSeeHtml('role="dialog" aria-modal="true" aria-labelledby="judul-pilih-hari"')
            ->assertSeeHtml('x-on:keydown.escape=')
            ->assertSeeHtml('aria-label="Tutup"')
            ->assertSeeHtml('grid-cols-[repeat(auto-fill,minmax(44px,1fr))]')
            ->assertDontSeeHtml('grid-cols-7');
    }

    public function test_kamus_search_has_a_label_status_and_named_icon_buttons(): void
    {
        $this->seed(MandarinHskSeeder::class);
        $this->seed(IrregularVerbSeeder::class);
        $this->seed(EnglishVocabSeeder::class);

        Livewire::actingAs($this->onboardedUser())->test(Search::class)
            ->assertSeeHtml('<label for="cari-kamus"')
            ->assertSeeHtml('id="cari-kamus"')
            ->assertSeeHtml('role="status"')
            ->assertSeeHtml('aria-pressed="true"')
            ->set('filterLang', 'mandarin')
            ->assertSeeHtml('aria-label="Dengarkan')
            ->assertSeeHtml('aria-label="Latihan tulis')
            ->assertSeeHtml('role="dialog" aria-modal="true" aria-labelledby="judul-latihan-tulis"');
    }

    public function test_isian_controls_are_labelled_and_result_is_announced(): void
    {
        $this->seed(MandarinHskSeeder::class);

        Livewire::actingAs($this->onboardedUser())->test(LatihanIsian::class)
            ->assertSeeHtml('<label for="isian-mode"')
            ->assertSeeHtml('id="isian-mode"')
            ->assertSeeHtml('<label for="isian-jumlah"')
            ->assertSeeHtml('<label for="isian-urutan"')
            ->assertSeeHtml('untuk soal 1"')
            ->call('checkAnswers')
            ->assertSee('Hasil analisis:');
    }

    public function test_pronunciation_controls_are_labelled_and_messages_live(): void
    {
        Livewire::actingAs($this->onboardedUser())->test(PronunciationCheck::class)
            ->assertSeeHtml('<label for="ucapan-bahasa"')
            ->assertSeeHtml('id="ucapan-bahasa"')
            ->assertSeeHtml('<label for="ucapan-cari"')
            ->assertSeeHtml('role="status"')
            ->assertDontSeeHtml('text-red-500');
    }

    public function test_conversation_language_choice_exposes_pressed_state(): void
    {
        Livewire::actingAs($this->onboardedUser())->test(Picker::class)
            ->assertSeeHtml('aria-pressed="true"')
            ->assertSeeHtml('aria-pressed="false"');
    }

    public function test_low_contrast_utility_classes_are_gone_from_views(): void
    {
        $views = collect(glob(resource_path('views/livewire/{,*/,*/*/}*.blade.php'), GLOB_BRACE));

        foreach ($views as $path) {
            $html = file_get_contents($path);
            $this->assertDoesNotMatchRegularExpression('#text-white/(50|60|70)\b|text-red-500|bg-red-500|text-ink-faint/70#', $html, $path);
        }
    }

    public function test_mobile_bottom_tabs_meet_44px(): void
    {
        $this->actingAs($this->onboardedUser())->get(route('progress'))
            ->assertOk()
            ->assertSee('min-h-[44px] min-w-[44px]', false)
            ->assertSee('aria-labelledby="judul-lainnya"', false);
    }
}
