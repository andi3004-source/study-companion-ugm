<?php

namespace Tests\Feature;

use App\Livewire\Games\ErrorHunter;
use App\Models\MandarinHskWord;
use App\Models\User;
use App\Support\ErrorGameItems;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class GameLowVisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        ErrorGameItems::flush();
        config([
            'game.items_path' => base_path('tests/Fixtures/kesalahan_game.json'),
            'katabicara.path' => base_path('tests/Fixtures/does_not_exist.json'),
        ]);

        foreach ([['我', 'saya'], ['你', 'kamu'], ['他', 'dia'], ['好', 'baik'], ['大', 'besar']] as [$hanzi, $arti]) {
            MandarinHskWord::create(['hanzi' => $hanzi, 'pinyin' => 'x', 'meaning_en' => 'x', 'meaning_id' => $arti, 'hsk_level_old' => 1]);
        }
    }

    private function user(): User
    {
        return User::factory()->create(['start_date' => now()->toDateString(), 'track' => 'both', 'onboarded' => true]);
    }

    public function test_display_panel_offers_relaxed_spacing_and_html_classes_change(): void
    {
        $html = $this->actingAs($this->user())->get('/game')->assertOk()->getContent();

        $this->assertStringContainsString('Jarak baris dan huruf lebih lega', $html);
        $this->assertStringContainsString('x-model="lega"', $html);
        // The inline <head> script sets the classes before first paint.
        $this->assertStringContainsString("if (tampilan.ukuran === 'sangat-besar') kelas.add('teks-sangat-besar');", $html);
        $this->assertStringContainsString("if (tampilan.lega) kelas.add('teks-lega');", $html);

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('html.teks-sangat-besar', $css);
        $this->assertStringContainsString('html.teks-lega body', $css);
        $this->assertStringContainsString('color-scheme: only light', $css);
        $this->assertStringContainsString('@media (forced-colors: active)', $css);

        $js = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString("kelas.toggle('teks-lega', this.lega);", $js);
        $this->assertStringContainsString('...current,', $js); // other keys in 'tampilan' survive
    }

    public function test_game_pages_use_fieldsets_text_symbols_and_large_relative_sizes(): void
    {
        $user = $this->user();

        foreach (['/game/dengar-pilih', '/game/pemburu-kesalahan'] as $url) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, '<h1'), $url);
            $this->assertStringContainsString('<fieldset', $html, $url);
            $this->assertStringContainsString('<legend', $html, $url);
            $this->assertStringContainsString('type="radio"', $html, $url);
            $this->assertStringContainsString('text-[4rem]', $html, $url); // hanzi grows with the text size setting
            $this->assertStringContainsString('min-h-[3.5rem]', $html, $url); // 56px+ answer targets
            $this->assertStringContainsString('lang="zh-Hans"', $html, $url);
            $this->assertStringNotContainsString('sm:grid-cols-2', $html, $url); // one column
        }
    }

    public function test_error_hunter_feedback_has_symbols_and_radio_answers_work(): void
    {
        $game = Livewire::actingAs($this->user())->test(ErrorHunter::class);
        $correct = ErrorGameItems::find($game->get('questions')[0]['id'])['kategori'];

        $game->call('jawabPilihan')->assertHasErrors('pilihanKategori')->assertSet('chosen', null);
        $game->set('pilihanKategori', 'bukan-opsi')->call('jawabPilihan')->assertSet('chosen', null);

        $game->set('pilihanKategori', $correct)->call('jawabPilihan')
            ->assertSet('chosen', $correct)
            ->assertSet('score', 1)
            ->assertSee('✓ Benar!')
            ->assertSee('jawaban benar')
            ->assertSee('Dengarkan kalimat');
    }

    public function test_game_views_have_no_low_contrast_or_tiny_text_classes(): void
    {
        foreach (glob(resource_path('views/livewire/games/*.blade.php')) as $path) {
            $view = file_get_contents($path);

            $this->assertDoesNotMatchRegularExpression('#text-white/(50|60|70)\b|text-red-[45]00|text-ink-faint/\d+|text-\[1[01]px\]|text-\[9px\]|border-red-400#', $view, $path);
        }
    }
}
