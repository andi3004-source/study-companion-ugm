<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The logged-in app uses the home page's look: palette, self-hosted font, outline icons, brand logo. */
class AppLookTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Livewire::flushState();
        parent::tearDown();
    }

    private function page(string $url = '/dashboard'): string
    {
        $this->seed(ReferenceDataSeeder::class);
        $user = User::factory()->create(['start_date' => now()->subDays(2)->toDateString(), 'track' => 'both', 'onboarded' => true]);

        return $this->actingAs($user)->get($url)->assertOk()->getContent();
    }

    public function test_palette_and_font_match_the_home_page(): void
    {
        $config = file_get_contents(base_path('tailwind.config.js'));

        foreach (["navy: '#14294D'", "orange: '#F9B934'", "cream: '#FBF8F3'", "navy: '#14294D',\n                    yellow: '#F9B934'"] as $token) {
            $this->assertStringContainsString($token, $config, $token);
        }
        $this->assertStringContainsString("display: ['\"Plus Jakarta Sans\"', 'system-ui', 'sans-serif']", $config);
        $this->assertStringContainsString("sans: ['\"Plus Jakarta Sans\"', 'system-ui', 'sans-serif']", $config);
        // font-600 / font-700 in the views now generate CSS.
        $this->assertMatchesRegularExpression('/fontWeight: \{\s*400: \'400\',\s*500: \'500\',\s*600: \'600\',\s*700: \'700\',\s*800: \'800\',\s*\}/', $config);
    }

    public function test_fonts_are_self_hosted_without_an_outside_font_service(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertStringNotContainsString('fonts.googleapis', $css);
        $this->assertStringNotContainsString('@import url(', $css);
        $this->assertStringNotContainsString('Fredoka', $css);
        $this->assertStringNotContainsString('Nunito', $css);
        $this->assertStringContainsString("import '@fontsource/plus-jakarta-sans/700.css';", $js);
        $this->assertStringContainsString("import '@fontsource/caveat/600.css';", $js);

        $html = $this->page();
        $this->assertStringNotContainsString('fonts.googleapis', $html);
        $this->assertStringNotContainsString('fonts.bunny', $html);
    }

    public function test_navigation_uses_outline_icons_and_the_brand_logo(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('images/landing/logo-tuturnusa-putih.png', $html);
        // Every nav icon is a decorative SVG inside the aria-hidden span; no emoji left.
        $nav = substr($html, strpos($html, '<nav aria-label="Navigasi utama"'));
        $nav = substr($nav, 0, strpos($nav, '</nav>'));
        $this->assertSame(substr_count($nav, '<a href='), substr_count($nav, '<span aria-hidden="true"><svg'));
        $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2753}]/u', $nav);
        // Active item: yellow bar on the left, like the home page's active menu line.
        $this->assertStringContainsString('shadow-[inset_3px_0_0_#F9B934]', $nav);

        $layout = file_get_contents(resource_path('views/layouts/study.blade.php'));
        foreach (['home', 'chat', 'mic', 'translate', 'cards', 'target', 'keyboard', 'document', 'chart', 'search', 'help'] as $icon) {
            $this->assertStringContainsString("'icon' => '{$icon}'", $layout, $icon);
        }
    }

    public function test_login_page_uses_the_brand_logo(): void
    {
        $this->get('/login')->assertOk()->assertSee('images/landing/logo-tuturnusa.png', false)->assertDontSee('>好</div>', false);
    }

    public function test_cards_get_a_soft_shadow_except_in_high_contrast(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString(".bg-card.rounded-2xl,\n.bg-card.rounded-3xl {\n    box-shadow:", $css);
        $this->assertStringContainsString("html.kontras-tinggi .bg-card {\n    box-shadow: none;", $css);
    }
}
