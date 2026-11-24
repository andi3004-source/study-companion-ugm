@php
    $bagian = collect($chapters)->groupBy('bagian');
@endphp
<div class="max-w-[720px] mx-auto px-4 py-8">
    <nav class="flex flex-wrap gap-4 mb-6 text-lg" aria-label="Navigasi buku">
        <a href="{{ route('home') }}" class="text-navy font-600 underline">Beranda</a>
        <a href="{{ route('buku-saku') }}" class="text-navy font-600 underline">Semua buku</a>
    </nav>
    <h1 class="font-display font-600 text-[2.75rem] text-navy mb-3">{{ $book['judul'] }}</h1>
    <p class="text-xl text-secondary mb-10">{{ $book['ringkasan'] }}</p>

    @foreach ($bagian as $namaBagian => $daftar)
        <h2 class="font-display font-600 text-[1.625rem] text-navy mb-4">{{ $namaBagian }}</h2>
        <ol class="list-none p-0 m-0 mb-10 space-y-3">
            @foreach ($daftar as $bab)
                <li>
                    <a href="{{ route('buku-saku.chapter', [$book['slug'], $bab['slug']]) }}"
                       class="flex items-center gap-4 rounded-2xl border-2 border-navy bg-white px-4 py-3 min-h-[4rem] no-underline text-navy">
                        <span class="inline-flex items-center justify-center w-11 h-11 rounded-full bg-orange font-600 text-navy shrink-0" aria-hidden="true">{{ $bab['urutan'] }}</span>
                        <span class="font-600 text-xl flex-1">Bab {{ $bab['urutan'] }}. {{ $bab['judul'] }}</span>
                        <svg class="o-ic w-6 h-6 shrink-0" aria-hidden="true"><use href="#i-chevron-right"/></svg>
                    </a>
                </li>
            @endforeach
        </ol>
    @endforeach
</div>