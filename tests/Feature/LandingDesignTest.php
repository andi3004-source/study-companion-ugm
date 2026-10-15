<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Redesigned home page (components/landing): assets, accessibility hooks, progressive enhancement. */
class LandingDesignTest extends TestCase
{
    private function html(): string
    {
        return $this->get('/')->assertOk()->getContent();
    }

    public function test_landmarks_skip_link_and_section_anchors(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('<a href="#konten" class="skip-link">Lewati ke konten utama</a>', $html);
        $this->assertStringContainsString('<header', $html);
        $this->assertStringContainsString('<nav aria-label="Bagian halaman"', $html);
        $this->assertStringContainsString('<main id="konten"', $html);
        $this->assertStringContainsString('<footer', $html);

        foreach (['beranda', 'cara-kerja', 'coba', 'fitur', 'aksesibilitas', 'status'] as $id) {
            $this->assertStringContainsString('href="#'.$id.'"', $html, $id);
            $this->assertStringContainsString('id="'.$id.'"', $html, $id);
        }
        $this->assertSame(1, substr_count($html, 'aria-current="location"'));
    }

    public function test_images_exist_and_have_meaningful_or_empty_alt(): void
    {
        $html = $this->html();

        foreach (['hero-belajar.jpg', 'logo-tuturnusa.png', 'logo-tuturnusa-putih.png', 'ilustrasi-anak-ransel.png'] as $file) {
            $this->assertFileExists(public_path('images/landing/'.$file));
            $this->assertStringContainsString('images/landing/'.$file, $html);
        }

        $this->assertMatchesRegularExpression('#<img src="[^"]*hero-belajar\.jpg"[^>]*alt="Seorang pelajar[^"]+wǒ hěn gāoxìng#u', $html);
        $this->assertMatchesRegularExpression('#<img src="[^"]*ilustrasi-anak-ransel\.png" alt=""#', $html);
        $this->assertDoesNotMatchRegularExpression('#<img(?![^>]*\balt=)[^>]*>#', $html);
    }

    public function test_mandarin_text_is_marked_and_example_is_labelled_as_example(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('<span lang="zh-Hans" class="flex-1 text-xl text-ink">我很高兴</span>', $html);
        $this->assertStringContainsString('<span lang="zh-Hans">我很高兴。</span>', $html);
        $this->assertStringContainsString('Contoh berikut tidak memakai AI', $html);
        $this->assertStringContainsString('role="tablist" aria-label="Bahasa contoh"', $html);
    }

    public function test_js_only_controls_are_hidden_without_javascript(): void
    {
        $html = $this->html();

        // Tampilan controls, the menu button, the tabs and the speaker button start hidden.
        $this->assertMatchesRegularExpression('#<button type="button" data-menu-toggle data-js-only hidden#', $html);
        $this->assertSame(3, substr_count($html, 'data-js-only hidden')); // menu button, Tampilan, example tabs
        $this->assertSame(2, substr_count($html, 'data-js-only="speech" hidden')); // one speaker per example
        foreach (['aria-label="Ukuran teks normal"', 'aria-label="Perbesar teks"', 'aria-label="Mode kontras tinggi"', 'aria-label="Buka menu"', 'aria-controls="menu-utama"'] as $attr) {
            $this->assertStringContainsString($attr, $html);
        }
    }

    public function test_display_settings_share_the_app_key_and_classes(): void
    {
        $js = file_get_contents(resource_path('js/welcome.js'));
        $css = file_get_contents(resource_path('css/welcome.css'));

        $this->assertStringContainsString("const KEY = 'tampilan';", $js);
        foreach (['teks-besar', 'teks-sangat-besar', 'kontras-tinggi'] as $class) {
            $this->assertStringContainsString("'{$class}'", $js);
            $this->assertStringContainsString("html.{$class}", $css);
        }
        $this->assertStringContainsString('prefers-reduced-motion', $css);
        $this->assertStringContainsString("localStorage.getItem('tampilan')", $this->html());
    }

    public function test_fonts_are_self_hosted(): void
    {
        $js = file_get_contents(resource_path('js/welcome.js'));

        $this->assertStringContainsString("import '@fontsource/plus-jakarta-sans/400.css';", $js);
        $this->assertStringContainsString("import '@fontsource/caveat/600.css';", $js);
        $this->assertDoesNotMatchRegularExpression('#https?://#', $js);
    }

    public function test_every_status_has_a_legend_entry_and_a_badge_colour(): void
    {
        $html = $this->html();
        $badge = file_get_contents(resource_path('views/components/landing/status-badge.blade.php'));

        foreach (['Berjalan' => 'Fitur sudah tersedia', 'Draf' => 'Dalam tahap pengembangan', 'Eksperimen' => 'Sedang diuji', 'Rencana' => 'Masih dalam perencanaan'] as $status => $meaning) {
            $this->assertMatchesRegularExpression('#<dt[^>]*>.*?</span>'.$status.'</dt>\s*<dd[^>]*>'.$meaning.'</dd>#s', $html, $status);
            $this->assertStringContainsString("'{$status}' =>", $badge);
        }
    }

    public function test_footer_links_only_to_pages_that_exist(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('&copy; '.now()->year.' TUTURNUSA. Dibangun untuk pembelajar Indonesia.', $html);
        $this->assertStringContainsString('href="'.route('bantuan').'"', $html);
        foreach (['Kebijakan Privasi', '>Kontak<', '>Tentang<'] as $missing) {
            $this->assertStringNotContainsString($missing, $html);
        }
    }

    public function test_closed_registration_hides_daftar_everywhere_on_the_page(): void
    {
        config(['registration.enabled' => false]);

        $html = $this->html();

        $this->assertStringNotContainsString('href="'.route('register').'"', $html);
        $this->assertStringContainsString('href="'.route('login').'"', $html);
    }
}
