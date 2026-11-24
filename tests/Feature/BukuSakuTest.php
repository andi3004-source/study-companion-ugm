<?php

namespace Tests\Feature;

use App\Support\BukuSakuCatalog;
use Tests\TestCase;

class BukuSakuTest extends TestCase
{
    public function test_real_books_and_chapters_render(): void
    {
        $this->get(route('buku-saku'))->assertOk()->assertSee('Buku Saku Mandiri')->assertSee('Buku Saku Senam Dasar');
        $catalog = app(BukuSakuCatalog::class);
        foreach (['buku-saku-mandiri' => 16, 'buku-saku-senam-dasar' => 14] as $slug => $count) {
            $chapters = $catalog->chapters($slug);
            $this->assertCount($count, $chapters);
            $this->get(route('buku-saku.book', $slug))->assertOk()->assertSee('<h1', false);
            $chapter = $chapters[1];
            $html = $this->get(route('buku-saku.chapter', [$slug, $chapter['slug']]))->assertOk()->getContent();
            $this->assertStringContainsString('<h1', $html);
            $this->assertStringContainsString('<ol>', $html);
            $this->assertStringContainsString('preload="none"', $html);
            $this->assertStringNotContainsString('autoplay', $html);
        }
    }

    public function test_unknown_and_traversal_slugs_are_rejected(): void
    {
        foreach (['asing', '%2e%2e', '%252e%252e'] as $slug) {
            $this->get('/buku-saku/'.$slug)->assertNotFound();
            $this->get('/buku-saku/buku-saku-mandiri/'.$slug)->assertNotFound();
        }
    }
}
