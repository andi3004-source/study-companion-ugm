<?php

namespace Tests\Unit;

use App\Support\BukuSakuCatalog;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class BukuSakuCatalogTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/buku-saku-'.bin2hex(random_bytes(8));
        File::copyDirectory(base_path('tests/Fixtures/buku-saku'), $this->root);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function catalog(): BukuSakuCatalog
    {
        return new BukuSakuCatalog($this->root.'/resources', $this->root.'/public');
    }

    public function test_only_two_fixed_books_and_chapters_are_sorted_numerically(): void
    {
        $this->assertSame(['buku-saku-mandiri', 'buku-saku-senam-dasar'], array_keys($this->catalog()->books()));
        $this->assertSame(['00-pengantar', '01-menyikat-gigi', '02-merapikan-tempat-tidur'], array_column($this->catalog()->chapters('buku-saku-mandiri'), 'slug'));
        $this->assertSame(['00-pengantar', '01-gerakan-pertama', '99-referensi'], array_column($this->catalog()->chapters('buku-saku-senam-dasar'), 'slug'));
    }

    public function test_front_matter_is_parsed_and_markdown_is_safe(): void
    {
        File::put($this->root.'/resources/buku-saku-mandiri/01-menyikat-gigi.md', "---\njudul: Menyikat gigi mandiri\nbagian: Perawatan diri\nurutan: 1\n---\n\n## Alat\n\n<script>alert(1)</script>\n\n[berbahaya](javascript:alert(1)) dan [aman](https://example.org).\n");

        $chapter = $this->catalog()->chapter('buku-saku-mandiri', '01-menyikat-gigi');
        $this->assertSame('Menyikat gigi mandiri', $chapter['judul']);
        $this->assertSame('Perawatan diri', $chapter['bagian']);
        $this->assertSame(1, $chapter['urutan']);
        $this->assertStringContainsString('<h2>Alat</h2>', $chapter['html']);
        $this->assertStringNotContainsString('<script', $chapter['html']);
        $this->assertStringNotContainsString('javascript:', $chapter['html']);
        $this->assertStringContainsString('href="https://example.org"', $chapter['html']);
        $this->assertSame('/audio/buku-saku/buku-saku-mandiri/01.mp3', $chapter['audioUrl']);
        $this->assertSame(42, $chapter['durationSeconds']);
    }

    public function test_invalid_content_fails_explicitly(): void
    {
        $file = $this->root.'/resources/buku-saku-mandiri/01-menyikat-gigi.md';
        $valid = File::get($file);
        foreach ([
            str_replace('urutan: 1', 'urutan: 2', $valid),
            str_replace('urutan: 1', "urutan: 1\nasing: nilai", $valid),
            str_replace('urutan: 1', "urutan: 1\nurutan: 1", $valid),
            str_replace('judul: Menyikat gigi mandiri', 'judul: ', $valid),
            str_replace("---\n\n## Alat dan bahan", "---\n\n", $valid),
        ] as $invalid) {
            File::put($file, $invalid);
            try {
                $this->catalog()->chapters('buku-saku-mandiri');
                $this->fail('Materi rusak diterima');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('01-menyikat-gigi.md', $e->getMessage());
            }
        }
    }

    public function test_duplicate_numbers_and_slugs_fail(): void
    {
        File::copy($this->root.'/resources/buku-saku-mandiri/01-menyikat-gigi.md', $this->root.'/resources/buku-saku-mandiri/01-lain.md');
        $this->expectException(RuntimeException::class);
        $this->catalog()->chapters('buku-saku-mandiri');
    }

    public function test_invalid_manifest_or_missing_audio_fails_explicitly(): void
    {
        $manifest = $this->root.'/public/manifest.json';
        $valid = File::get($manifest);
        foreach ([
            str_replace('"durasi_detik": 42', '"durasi_detik": 0', $valid),
            str_replace('buku-saku-mandiri/01.mp3', 'buku-saku-mandiri/02.mp3', $valid),
        ] as $invalid) {
            File::put($manifest, $invalid);
            try {
                $this->catalog()->chapter('buku-saku-mandiri', '01-menyikat-gigi');
                $this->fail('Manifest rusak diterima');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('buku-saku-mandiri/01', $e->getMessage());
            }
        }
        File::put($manifest, $valid);
        File::delete($this->root.'/public/buku-saku-mandiri/01.mp3');
        $this->expectException(RuntimeException::class);
        $this->catalog()->chapter('buku-saku-mandiri', '01-menyikat-gigi');
    }
}
