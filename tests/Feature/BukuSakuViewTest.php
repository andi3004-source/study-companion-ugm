<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Tests\TestCase;

/** Kontrak template Buku Saku (data sintetis; rute hanya untuk tes). */
class BukuSakuViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test-beranda', fn () => view('layouts.study', [
            'title' => 'Tuturnusa',
            'slot' => new HtmlString(view('home')->render()),
        ]))->name('home');

        $wrap = fn (string $view, array $data, string $title) => view('layouts.study', [
            'title' => $title,
            'slot' => new HtmlString(view($view, $data)->render()),
        ]);

        Route::get('/_test-buku-saku', fn () => $wrap('buku-saku.index', [
            'books' => [
                ['slug' => 'buku-saku-mandiri', 'judul' => 'Buku Saku Mandiri', 'ringkasan' => 'Panduan aktivitas.'],
                ['slug' => 'buku-saku-senam-dasar', 'judul' => 'Senam Dasar', 'ringkasan' => 'Olahraga mandiri.'],
            ],
        ], 'Buku Saku'))->name('buku-saku');

        Route::name('buku-saku.')->group(function () use ($wrap) {
            Route::get('/_test-buku-saku/{buku}', fn (string $buku) => $wrap('buku-saku.book', [
                'book' => ['slug' => $buku, 'judul' => 'Buku Saku Mandiri', 'ringkasan' => 'Panduan aktivitas.'],
                'chapters' => [
                    ['slug' => '01', 'judul' => 'Bab 1. Cara Menyikat Gigi Mandiri', 'bagian' => 'Kebersihan', 'urutan' => 1],
                    ['slug' => '02', 'judul' => 'Bab 2. Botol sabun', 'bagian' => 'Kebersihan', 'urutan' => 2],
                ],
            ], 'Daftar isi'))->name('book');

            Route::get('/_test-buku-saku/{buku}/{bab}', fn (string $buku, string $bab) => $wrap('buku-saku.chapter', [
                'book' => ['slug' => $buku, 'judul' => 'Buku Saku Mandiri'],
                'chapter' => [
                    'slug' => $bab,
                    'judul' => 'Bab 1. Cara Menyikat Gigi Mandiri',
                    'bagian' => 'Bagian 1: Kebersihan',
                    'urutan' => 1,
                    'html' => '<h2>Alat dan bahan</h2><p>Sikat gigi.</p><h2>Langkah-langkah</h2><ol><li>Basahi.</li></ol>',
                ],
                'previous' => null,
                'next' => ['slug' => '02', 'judul' => 'Bab 2. Botol sabun'],
                'audioUrl' => '/audio/buku-saku/buku-saku-mandiri/01.mp3',
                'durationSeconds' => 192,
                'unitLabel' => 'Bab',
                'numberedTotal' => 15,
                'showPosition' => true,
            ], 'Bab 1'))->name('chapter');

        });

        Route::getRoutes()->refreshNameLookups();
    }

    public function test_index_has_one_h1_and_lists_books(): void
    {
        $html = $this->get('/_test-buku-saku')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('Buku Saku', $html);
        $this->assertStringContainsString('buku-saku-mandiri', $html);
    }

    public function test_chapter_shows_judul_once_and_position_for_numbered_bab(): void
    {
        $html = $this->get('/_test-buku-saku/buku-saku-mandiri/01')->assertOk()->getContent();

        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Bab 1. Cara Menyikat Gigi Mandiri', $html);
        $this->assertStringNotContainsString('Bab 1. Bab 1.', $html);
        $this->assertStringContainsString('Bab 1 dari 15', $html);
        $this->assertStringContainsString('text-[3rem]', $html);
        $this->assertStringContainsString('text-[1.375rem]', $html);
        $this->assertStringContainsString('max-w-[800px]', $html);
        $this->assertStringContainsString('Bab 2. Botol sabun', $html);
        $this->assertStringNotContainsString('Bab 2: Bab', $html);
    }

    public function test_chapter_page_matches_qa_d1_audio_and_heading_contract(): void
    {
        $html = $this->get('/_test-buku-saku/buku-saku-mandiri/01')->assertOk()->getContent();

        $this->assertStringContainsString('Audio narasi', $html);
        $this->assertStringContainsString('<audio', $html);
        $this->assertStringContainsString('preload="none"', $html);
        $this->assertStringNotContainsString('<audio controls', $html);
        $this->assertStringContainsString('Putar narasi', $html);
        $this->assertStringContainsString('Mundur 10 detik', $html);
        $this->assertStringContainsString('Maju 10 detik', $html);
        $this->assertStringContainsString('aria-pressed', $html);
        $this->assertStringContainsString('Kembali ke daftar isi', $html);
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('aria-label=', $html);
        $this->assertStringNotContainsString('autoplay', $html);
    }

    public function test_pengantar_has_no_position_count(): void
    {
        $html = $this->get('/buku-saku/buku-saku-mandiri/00-pengantar')->assertOk()->getContent();

        $this->assertStringContainsString('Pengantar Buku Saku Mandiri', $html);
        $this->assertStringNotContainsString('dari 15', $html);
        $this->assertStringNotContainsString('Bab 0', $html);
    }

    public function test_real_mandiri_chapter_01_via_route(): void
    {
        $html = $this->get('/buku-saku/buku-saku-mandiri/01-cara-menyikat-gigi-mandiri')->assertOk()->getContent();

        $this->assertStringContainsString('Bab 1. Cara Menyikat Gigi Mandiri', $html);
        $this->assertStringContainsString('Bab 1 dari 15', $html);
        $this->assertStringNotContainsString('Bab 1. Bab', $html);
    }

    public function test_real_senam_chapter_shows_gerakan_position(): void
    {
        $html = $this->get('/buku-saku/buku-saku-senam-dasar/08-duduk-ke-berdiri-mandiri')->assertOk()->getContent();

        $this->assertStringContainsString('Gerakan 8.', $html);
        $this->assertStringContainsString('Gerakan 8 dari 12', $html);
    }
}
