<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Tests\TestCase;

/** Kontrak tampilan beranda baru (belum diaktifkan di rute produksi pada W1). */
class BerandaViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test-beranda', fn () => view('layouts.study', [
            'title' => 'Tuturnusa',
            'navRingkas' => true,
            'slot' => new HtmlString(view('home')->render()),
        ]))->name('home');

        Route::get('/_test-buku-saku', fn () => '#')->name('buku-saku');

        Route::getRoutes()->refreshNameLookups();
    }

    protected function homeHtml(): string
    {
        return $this->get('/_test-beranda')->assertOk()->getContent();
    }

    public function test_single_h1_and_three_card_links_in_list(): void
    {
        $html = $this->homeHtml();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('Mau belajar apa hari ini?', $html);
        $this->assertSame(3, substr_count($html, 'class="kartu-fitur block'));
        $this->assertMatchesRegularExpression('/<ul[^>]*class="[^"]*daftar-kartu-beranda/', $html);
        $this->assertSame(3, substr_count($html, '<li class="kartu-fitur-item">'));
    }

    public function test_each_card_is_one_link_with_heading_and_description(): void
    {
        $html = $this->homeHtml();

        foreach (['Ngobrol', 'Ucapan', 'Buku Saku'] as $judul) {
            $this->assertStringContainsString($judul, $html);
        }
        $this->assertStringContainsString(route('conversation'), $html);
        $this->assertStringContainsString(route('pronunciation-check'), $html);
        $this->assertStringContainsString(route('buku-saku', absolute: false), $html);
    }

    public function test_mode_suara_before_logo_in_dom_order(): void
    {
        $html = $this->homeHtml();
        $posSuara = strpos($html, 'id="tombol-mode-suara"');
        $posLogo = strpos($html, 'logo-beranda');
        $this->assertNotFalse($posSuara);
        $this->assertNotFalse($posLogo);
        $this->assertLessThan($posLogo, $posSuara);
    }
}
