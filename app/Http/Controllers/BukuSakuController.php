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

        $unitLabel = $buku === 'buku-saku-mandiri' ? 'Bab' : 'Gerakan';
        $numberedTotal = collect($chapters)->filter(fn (array $c) => $c['urutan'] >= 1 && $c['urutan'] <= 98)->count();
        $showPosition = $chapter['urutan'] >= 1 && $chapter['urutan'] <= 98;

        return $this->page('buku-saku.chapter', [
            'book' => $book,
            'chapters' => $chapters,
            'chapter' => $chapter,
            'previous' => $chapters[$position - 1] ?? null,
            'next' => $chapters[$position + 1] ?? null,
            'audioUrl' => $chapter['audioUrl'],
            'durationSeconds' => $chapter['durationSeconds'],
            'unitLabel' => $unitLabel,
            'numberedTotal' => $numberedTotal,
            'showPosition' => $showPosition,
        ]);
    }
}
