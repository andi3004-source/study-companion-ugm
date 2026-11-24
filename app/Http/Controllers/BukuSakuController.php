<?php

namespace App\Http\Controllers;

use App\Support\BukuSakuCatalog;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

class BukuSakuController extends Controller
{
    public function __construct(private BukuSakuCatalog $catalog) {}

    private function page(string $name, array $data): View
    {
        return view('layouts.study', [
            'navRingkas' => true,
            'slot' => new HtmlString(view($name, $data)->render()),
        ]);
    }

    public function index(): View
    {
        return $this->page('buku-saku.index', ['books' => $this->catalog->books()]);
    }

    public function book(string $buku): View
    {
        return $this->page('buku-saku.book', [
            'book' => $this->catalog->book($buku),
            'chapters' => $this->catalog->chapters($buku),
        ]);
    }

    public function chapter(string $buku, string $bab): View
    {
        $book = $this->catalog->book($buku);
        $chapters = $this->catalog->chapters($buku);
        $chapter = $this->catalog->chapter($buku, $bab);
        $position = array_search($bab, array_column($chapters, 'slug'), true);

        return $this->page('buku-saku.chapter', [
            'book' => $book,
            'chapters' => $chapters,
            'chapter' => $chapter,
            'previous' => $chapters[$position - 1] ?? null,
            'next' => $chapters[$position + 1] ?? null,
            'audioUrl' => $chapter['audioUrl'],
            'durationSeconds' => $chapter['durationSeconds'],
            'chapterTotal' => $buku === 'buku-saku-mandiri' ? 15 : 12,
        ]);
    }
}
