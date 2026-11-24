<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Header "Tampilan" panel. The settings are applied client-side (localStorage
 * -> classes on <html>), so this checks the server-rendered contract: the
 * controls exist and the head script maps each setting to its <html> class.
 */
class DisplaySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function page(): string
    {
        $user = User::factory()->create([
            'start_date' => now()->subDays(2)->toDateString(),
            'track' => 'both',
            'onboarded' => true,
        ]);

        return $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();
    }

    public function test_header_has_keyboard_reachable_display_controls(): void
    {
        $html = $this->page();
        $header = substr($html, strpos($html, '<header'), strpos($html, '</header>') - strpos($html, '<header'));

        $this->assertStringContainsString('<summary', $header);
        $this->assertStringContainsString('Tampilan</summary>', $header);
        $this->assertStringContainsString('<legend class="text-sm font-700 mb-2">Ukuran teks</legend>', $header);
        $this->assertSame(3, substr_count($header, 'type="radio" name="ukuran-teks"'));
        foreach (['normal', 'besar', 'sangat-besar'] as $value) {
            $this->assertStringContainsString('value="'.$value.'"', $header);
        }
        $this->assertStringContainsString('Mode pembaca layar', $header);
        $this->assertStringNotContainsString('Mode kontras tinggi', $header);
        $this->assertStringContainsString('x-data="displaySettings()"', $header);
    }

    public function test_head_script_applies_saved_settings_as_html_classes(): void
    {
        $html = $this->page();
        $head = substr($html, 0, strpos($html, '</head>'));

        $this->assertStringContainsString("localStorage.getItem('tampilan')", $head);
        foreach (['teks-besar', 'teks-sangat-besar', 'mode-pembaca-layar'] as $class) {
            $this->assertStringContainsString("kelas.add('{$class}')", $head);
        }
        $this->assertStringNotContainsString('kontras-tinggi', $head);
    }

    public function test_css_and_js_define_the_display_classes(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        foreach (['html.teks-besar', 'html.teks-sangat-besar', 'color-scheme: only light', ':focus-visible', 'prefers-reduced-motion', 'min-height: 3.5rem'] as $rule) {
            $this->assertStringContainsString($rule, $css);
        }

        $js = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString("classList.contains('mode-pembaca-layar')", $js);
        $this->assertStringContainsString('window.screenReaderMode()', $js);
    }
}
