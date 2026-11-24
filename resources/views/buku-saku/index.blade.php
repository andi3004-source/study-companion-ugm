<div class="max-w-[720px] mx-auto px-4 py-8">
    <p class="mb-4">
        <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-navy font-600 underline">
            <svg class="o-ic w-5 h-5" aria-hidden="true"><use href="#i-arrow-left"/></svg>
            Beranda
        </a>
    </p>
    <h1 class="font-display font-600 text-[2.75rem] text-navy mb-3">Buku Saku</h1>
    <p class="text-xl text-secondary mb-8">Panduan kegiatan sehari-hari dan olahraga mandiri untuk tunanetra.</p>

    <ul class="list-none p-0 m-0 space-y-4">
        @foreach ($books as $book)
            <li>
                <a href="{{ route('buku-saku.book', $book['slug']) }}"
                   class="flex items-center gap-4 rounded-2xl border-2 border-navy bg-white px-5 py-4 min-h-[4rem] no-underline text-navy">
                    <span class="inline-flex items-center justify-center w-11 h-11 rounded-full bg-orange shrink-0" aria-hidden="true">
                        <svg class="o-ic w-6 h-6" aria-hidden="true"><use href="#i-book-open"/></svg>
                    </span>
                    <span class="flex-1">
                        <span class="block font-600 text-xl">{{ $book['judul'] }}</span>
                        <span class="block text-lg text-secondary">{{ $book['ringkasan'] }}</span>
                    </span>
                    <svg class="o-ic w-6 h-6 shrink-0" aria-hidden="true"><use href="#i-chevron-right"/></svg>
                </a>
            </li>
        @endforeach
    </ul>
</div>