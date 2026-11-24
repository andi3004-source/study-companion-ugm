<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Kontrak fondasi visual layout study (W1-C). */
class StudyLayoutTest extends TestCase
{
    protected function studyHtml(): string
    {
        return $this->get(route('bantuan'))->assertOk()->getContent();
    }

    public function test_body_uses_white_background_not_cream(): void
    {
        $html = $this->studyHtml();
        $this->assertMatchesRegularExpression('/<body[^>]*class="[^"]*bg-white/', $html);
        $this->assertDoesNotMatchRegularExpression('/<body[^>]*bg-cream/', $html);
    }

    public function test_head_sets_light_only_color_scheme_and_theme_color(): void
    {
        $html = $this->studyHtml();
        $head = substr($html, 0, strpos($html, '</head>'));

        $this->assertStringContainsString('name="color-scheme" content="only light"', $head);
        $this->assertStringContainsString('name="theme-color" content="#14294D"', $head);
    }

    public function test_skip_link_main_landmark_and_mode_suara_before_header_brand(): void
    {
        $html = $this->studyHtml();

        $this->assertStringContainsString('<a href="#konten" class="skip-link">Lewati ke konten</a>', $html);
        $this->assertStringContainsString('<main id="konten"', $html);
        $this->assertStringContainsString('id="tombol-mode-suara"', $html);

        $posSuara = strpos($html, 'id="tombol-mode-suara"');
        $posAside = strpos($html, '<aside');
        $this->assertNotFalse($posSuara);
        $this->assertFalse($posAside);
    }

    public function test_layout_uses_escaped_slot_not_blade_unescaped_output(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/study.blade.php'));

        $this->assertStringContainsString('{{ $slot }}', $layout);
        $this->assertDoesNotMatchRegularExpression('/\{!!\s*\$slot/', $layout);
        $this->assertStringNotContainsString('$isiHalaman', $layout);
    }

    public function test_no_kontras_tinggi_in_owned_views_and_assets(): void
    {
        foreach (
            [
                resource_path('views/layouts/study.blade.php'),
                resource_path('views/livewire/bantuan.blade.php'),
                resource_path('css/app.css'),
                resource_path('js/app.js'),
            ] as $path
        ) {
            $contents = file_get_contents($path);
            $this->assertStringNotContainsString('kontras-tinggi', $contents, $path);
        }

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('color-scheme: only light', $css);
        $this->assertStringContainsString('accent-color: #14294D', $css);
        $this->assertStringContainsString('#1D4ED8', $css);
    }
}
