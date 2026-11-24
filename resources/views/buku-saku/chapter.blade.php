@php
    $menit = intdiv($durationSeconds, 60);
    $detik = $durationSeconds % 60;
    $durasiTeks = $menit > 0
        ? sprintf('%d menit %d detik', $menit, $detik)
        : sprintf('%d detik', $detik);
    $durasiJam = sprintf('%d:%02d', $menit, $detik);
@endphp
<div class="max-w-[800px] mx-auto px-4 py-8">
    <nav class="flex flex-wrap gap-4 mb-6 text-lg" aria-label="Navigasi bab">
        <a href="{{ route('home') }}" class="text-navy font-600 underline">Beranda</a>
        <a href="{{ route('buku-saku.book', $book['slug']) }}" class="text-navy font-600 underline">Daftar isi</a>
    </nav>

    <h1 class="font-display font-600 text-[3rem] leading-tight text-navy">{{ $chapter['judul'] }}</h1>
    @if ($showPosition ?? false)
        <p class="text-lg text-secondary mt-2 mb-8">{{ $unitLabel ?? 'Bab' }} {{ $chapter['urutan'] }} dari {{ $numberedTotal }} · {{ $chapter['bagian'] }}</p>
    @else
        <p class="text-lg text-secondary mt-2 mb-8">{{ $chapter['bagian'] }}</p>
    @endif

    <section class="rounded-[1.25rem] border-2 border-navy bg-white p-6 mb-10"
             aria-labelledby="audio-narasi-judul"
             x-data="chapterAudio({{ \Illuminate\Support\Js::from($audioUrl) }}, {{ \Illuminate\Support\Js::from($durasiJam) }})">
        <div class="mb-4">
            <h2 id="audio-narasi-judul" class="font-display font-600 text-xl text-navy">Audio narasi</h2>
            <p class="text-base text-secondary mt-1">Durasi {{ $durasiTeks }}</p>
        </div>
        <audio x-ref="pemutar"
               preload="none"
               src="{{ $audioUrl }}"
               aria-label="Audio narasi bab: {{ $chapter['judul'] }}"></audio>
        <div class="flex flex-wrap gap-3 mb-4">
            <button type="button"
                    class="min-h-[3.5rem] min-w-[3.5rem] px-5 rounded-full border-2 border-navy bg-navy text-white font-600 inline-flex items-center justify-center gap-2"
                    x-on:click="toggle()"
                    x-bind:aria-pressed="memutar ? 'true' : 'false'">
                <svg class="o-ic w-5 h-5 shrink-0" aria-hidden="true" x-show="!memutar"><use href="#i-play"/></svg>
                <svg class="o-ic w-5 h-5 shrink-0" aria-hidden="true" x-show="memutar" x-cloak><use href="#i-pause"/></svg>
                <span x-text="memutar ? 'Jeda narasi' : 'Putar narasi'"></span>
            </button>
            <button type="button"
                    class="min-h-[3.5rem] min-w-[3.5rem] px-5 rounded-full border-2 border-navy bg-white font-600 text-navy inline-flex items-center justify-center gap-2"
                    x-on:click="mundur()">
                <svg class="o-ic w-5 h-5 shrink-0" aria-hidden="true"><use href="#i-rotate-ccw"/></svg>
                Mundur 10 detik
            </button>
            <button type="button"
                    class="min-h-[3.5rem] min-w-[3.5rem] px-5 rounded-full border-2 border-navy bg-white font-600 text-navy inline-flex items-center justify-center gap-2"
                    x-on:click="maju()">
                <svg class="o-ic w-5 h-5 shrink-0" aria-hidden="true"><use href="#i-rotate-cw"/></svg>
                Maju 10 detik
            </button>
        </div>
        <p class="text-lg text-ink mb-2" x-text="posisiTeks"></p>
        <div class="h-2 rounded-full bg-line-card overflow-hidden mb-4" aria-hidden="true">
            <div class="h-full bg-navy rounded-full transition-[width] duration-150" x-bind:style="'width: ' + progres + '%'"></div>
        </div>
        <p class="text-lg text-secondary">Dengarkan narasi, atau baca teks di bawah dengan pembaca layarmu. Audio tidak diputar sendiri.</p>
        <p id="status-audio-bab" role="status" class="text-lg text-ink mt-2 empty:hidden" x-text="status"></p>
    </section>

    <div class="bab-isi text-[1.375rem] leading-relaxed text-navy">{!! $chapter['html'] !!}</div>

    <nav class="flex flex-wrap gap-4 mt-12 text-lg" aria-label="Navigasi akhir bab">
        <a href="{{ route('buku-saku.book', $book['slug']) }}"
           class="inline-flex items-center min-h-[3.5rem] px-6 rounded-full border-2 border-navy font-600 text-navy no-underline">
            Kembali ke daftar isi
        </a>
        @if ($next)
            <a href="{{ route('buku-saku.chapter', [$book['slug'], $next['slug']]) }}"
               class="inline-flex items-center min-h-[3.5rem] px-6 rounded-full border-2 border-navy font-600 text-navy no-underline">
                {{ $next['judul'] }}
            </a>
        @endif
    </nav>
</div>