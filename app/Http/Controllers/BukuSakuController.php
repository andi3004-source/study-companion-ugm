<?php

namespace App\Http\Controllers;

use App\Support\BukuSakuCatalog;
use Illuminate\View\View;

class BukuSakuController extends Controller
{
    public function __construct(private BukuSakuCatalog $catalog) {}

    public function index(): View
    {
        return view('buku-saku.index', ['books' => $this->catalog->books()]);
    }

    public function book(string $buku): View
    {
        return view('buku-saku.book', [
            'book' => $this->catalog->book($buku),
            'chapters' => $this->catalog->chapters($buku),
        ]);
    }

    public function chapter(string $buku, string $bab): View
    {
        $book = $this->catalog->book($buku);
        $chapters = $this->catalog->chapters($buku);
        $position = array_search($bab, array_column($chapters, 'slug'), true);
        abort_if($position === false, 404);
        $chapter = $chapters[$position];

        return view('buku-saku.chapter', [
            'book' => $book,
            'chapters' => $chapters,
            'chapter' => $chapter,
            'previous' => $chapters[$position - 1] ?? null,
            'next' => $chapters[$position + 1] ?? null,
            'audioUrl' => $chapter['audioUrl'],
            'durationSeconds' => $chapter['durationSeconds'],
        ]);
    }
}
