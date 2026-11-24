<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class BukuSakuCatalog
{
    private const BOOKS = [
        'buku-saku-mandiri' => 'Buku Saku Mandiri — Panduan Aktivitas Sehari-Hari Tunanetra',
        'buku-saku-senam-dasar' => 'Buku Saku Senam Dasar — Panduan Olahraga Mandiri Tunanetra',
    ];

    public function __construct(
        private ?string $materialsPath = null,
        private ?string $audioPath = null,
    ) {
        $this->materialsPath ??= resource_path('buku-saku');
        $this->audioPath ??= public_path('audio/buku-saku');
    }

    public function books(): array
    {
        $books = [];
        foreach (self::BOOKS as $slug => $judul) {
            $books[$slug] = $this->book($slug);
        }

        return $books;
    }

    public function book(string $slug): array
    {
        abort_unless(isset(self::BOOKS[$slug]), 404);

        return ['slug' => $slug, 'judul' => self::BOOKS[$slug], 'ringkasan' => $slug === 'buku-saku-mandiri' ? 'Panduan aktivitas sehari-hari.' : 'Panduan olahraga mandiri.'];
    }

    public function chapters(string $bookSlug): array
    {
        $this->book($bookSlug);
        $directory = $this->materialsPath.'/'.$bookSlug;
        if (! File::isDirectory($directory)) {
            throw new RuntimeException("Materi Buku Saku tidak ditemukan: {$bookSlug}");
        }

        $manifest = $this->manifest();
        $chapters = [];
        $numbers = [];
        foreach (File::files($directory) as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $slug = $file->getBasename('.md');
            if (! preg_match('/^(\d{2})-[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug, $matches)) {
                throw new RuntimeException("Nama materi Buku Saku tidak valid: {$file->getFilename()}");
            }

            $number = (int) $matches[1];
            if (isset($numbers[$number])) {
                throw new RuntimeException("Nomor materi Buku Saku duplikat: {$file->getFilename()}");
            }
            $numbers[$number] = true;

            $chapter = $this->parse($file->getPathname(), $number);
            $audioKey = $bookSlug.'/'.$matches[1];
            $audio = $manifest[$audioKey] ?? null;
            $expectedFile = $audioKey.'.mp3';
            if (! is_array($audio)
                || ! isset($audio['durasi_detik'], $audio['berkas'])
                || ! is_int($audio['durasi_detik'])
                || $audio['durasi_detik'] <= 0
                || $audio['berkas'] !== $expectedFile
                || ! File::isFile($this->audioPath.'/'.$expectedFile)
                || File::size($this->audioPath.'/'.$expectedFile) === 0) {
                throw new RuntimeException("Audio atau manifest Buku Saku tidak valid: {$audioKey}");
            }

            $chapters[] = $chapter + [
                'slug' => $slug,
                'audioUrl' => '/audio/buku-saku/'.$expectedFile,
                'durationSeconds' => $audio['durasi_detik'],
            ];
        }

        if ($chapters === []) {
            throw new RuntimeException("Materi Buku Saku kosong: {$bookSlug}");
        }

        usort($chapters, fn (array $a, array $b) => $a['urutan'] <=> $b['urutan'] ?: strcmp($a['slug'], $b['slug']));

        return $chapters;
    }

    public function chapter(string $bookSlug, string $chapterSlug): array
    {
        foreach ($this->chapters($bookSlug) as $chapter) {
            if ($chapter['slug'] === $chapterSlug) {
                return $chapter;
            }
        }

        abort(404);
    }

    private function parse(string $path, int $number): array
    {
        $source = File::get($path);
        if (! preg_match('/\A---\R(.*?)\R---\R(.*)\z/s', $source, $parts)) {
            throw new RuntimeException("Front matter Buku Saku tidak valid: {$path}");
        }

        $fields = [];
        foreach (preg_split('/\R/', $parts[1]) as $line) {
            if (! preg_match('/^(judul|bagian|urutan):[ \t]*(.*)$/', $line, $field) || isset($fields[$field[1]])) {
                throw new RuntimeException("Front matter Buku Saku tidak valid: {$path}");
            }
            $fields[$field[1]] = trim($field[2]);
        }

        $body = trim($parts[2]);
        if (count($fields) !== 3
            || $fields['judul'] === ''
            || $fields['bagian'] === ''
            || ! ctype_digit($fields['urutan'])
            || (int) $fields['urutan'] !== $number
            || ! preg_match('/\A##\s+\S/u', $body)
            || preg_match('/^#\s/m', $body)) {
            throw new RuntimeException("Materi Buku Saku tidak valid: {$path}");
        }

        return [
            'judul' => $fields['judul'],
            'bagian' => $fields['bagian'],
            'urutan' => $number,
            'html' => Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
        ];
    }

    private function manifest(): array
    {
        $path = $this->audioPath.'/manifest.json';
        if (! File::isFile($path)) {
            throw new RuntimeException("Manifest Buku Saku tidak ditemukan: {$path}");
        }

        $manifest = json_decode(File::get($path), true);
        if (! is_array($manifest) || array_is_list($manifest)) {
            throw new RuntimeException("Manifest Buku Saku tidak valid: {$path}");
        }

        return $manifest;
    }
}
