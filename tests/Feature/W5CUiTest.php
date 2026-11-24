<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Kontrak W5-C: [hidden], privasi mikrofon fitur, logo HD. */
class W5CUiTest extends TestCase
{
    private const PRIVASI_SNIPPET = 'dihapus dalam kira-kira 24 jam';

    private const PRIVASI_BUTTON = 'Saya mengerti, nyalakan mikrofon';

    private function jsApp(): string
    {
        return preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m'], '', file_get_contents(resource_path('js/app.js')));
    }

    private function jsMicConsent(): string
    {
        $path = resource_path('js/microphone-consent.js');

        return preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m'], '', file_get_contents($path));
    }

    public function test_hidden_attribute_is_enforced_over_display_utilities(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertMatchesRegularExpression('/\[hidden\]\s*\{\s*display:\s*none\s*!important;/', $css);
    }

    public function test_header_uses_hd_logo_with_accessible_home_link(): void
    {
        $html = $this->get(route('bantuan'))->assertOk()->getContent();

        $this->assertStringContainsString('id="logo-beranda"', $html);
        $this->assertStringContainsString('aria-label="Tuturnusa, beranda"', $html);
        $this->assertStringContainsString(asset('images/logo-tuturnusa.svg'), $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $logo = substr($html, strpos($html, 'id="logo-beranda"'), 400);
        $this->assertStringNotContainsString('#i-book', $logo);
    }

    public function test_head_includes_favicon(): void
    {
        $html = $this->get(route('bantuan'))->assertOk()->getContent();
        $head = substr($html, 0, strpos($html, '</head>'));

        $this->assertMatchesRegularExpression('/rel="icon"[^>]*favicon/i', $head);
        $this->assertFileExists(public_path('favicon.ico'));
    }

    public function test_pwa_icons_are_regenerated_from_brand_sources(): void
    {
        $source = base_path('docs/orchestra/brand/logo-tuturnusa-1024.png');
        $maskable = base_path('docs/orchestra/brand/logo-tuturnusa-maskable-1024.png');

        foreach (['icon-192.png' => 192, 'icon-512.png' => 512] as $file => $size) {
            $path = public_path("icons/{$file}");
            $this->assertFileExists($path);
            $this->assertGreaterThanOrEqual(filemtime($source), filemtime($path), "{$file} harus dibuat ulang dari logo HD");
            $info = getimagesize($path);
            $this->assertSame($size, $info[0]);
            $this->assertSame($size, $info[1]);
        }

        $maskPath = public_path('icons/icon-maskable-512.png');
        $this->assertFileExists($maskPath);
        $this->assertGreaterThanOrEqual(filemtime($maskable), filemtime($maskPath));
    }

    public function test_microphone_privacy_notice_on_ngobrol_and_ucapan(): void
    {
        $partial = resource_path('views/components/microphone-privasi.blade.php');
        $this->assertFileExists($partial);

        foreach (['livewire/conversation/chat.blade.php', 'livewire/pronunciation-check.blade.php'] as $view) {
            $contents = file_get_contents(resource_path("views/{$view}"));
            $this->assertStringContainsString("@include('components.microphone-privasi'", $contents, $view);
        }

        $partial = file_get_contents(resource_path('views/components/microphone-privasi.blade.php'));
        $this->assertStringContainsString(self::PRIVASI_BUTTON, $partial);
        $this->assertStringContainsString(self::PRIVASI_SNIPPET, $partial);
        $this->assertStringContainsString('<dialog', $partial);
        $this->assertStringContainsString('role="status"', $partial);
        $this->assertStringContainsString('x-ref="setujuMikrofon"', $partial);
    }

    public function test_feature_microphone_consent_is_gated_and_stored_in_local_storage(): void
    {
        $js = $this->jsMicConsent().$this->jsApp();

        $this->assertStringContainsString("localStorage.setItem(MIKROFON_PRIVASI_KEY, '1')", $js);
        $this->assertStringContainsString('mintaSetujuMikrofon', $js);
        $this->assertStringContainsString('sudahSetujuMikrofon()', $js);
        $this->assertStringContainsString('getUserMedia', $js);
        $this->assertStringContainsString('recognition.start()', $js);
        $this->assertMatchesRegularExpression('/mintaSetujuMikrofon\(\(\) => this\.startListening\(\)\)/', $js);
        $this->assertMatchesRegularExpression('/mintaSetujuMikrofon\(\(\) => this\.startRecording\(\)\)/', $js);
    }
}
