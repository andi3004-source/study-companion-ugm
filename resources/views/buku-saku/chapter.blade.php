@php
    $menit = intdiv($durationSeconds, 60);
    $detik = $durationSeconds % 60;
    $durasiTeks = $menit > 0
        ? sprintf('%d menit %d detik', $menit, $detik)
        : sprintf('%d detik', $detik);
    $total = $chapterTotal ?? $chapter['urutan'];
@endphp
<div class="max-w-[800px] mx-auto px-4 py-8">
    <nav class="flex flex-wrap gap-4 mb-6 text-lg" aria-label="Navigasi bab">
        <a href="{{ route('home') }}" class="text-navy font-600 underline">Beranda</a>
        <a href="{{ route('buku-saku.book', $book['slug']) }}" class="text-navy font-600 underline">Daftar isi</a>
    </nav>

    <h1 class="font-display font-600 text-[2.75rem] leading-tight text-navy">Bab {{ $chapter['urutan'] }}. {{ $chapter['judul'] }}</h1>
    <p class="text-lg text-secondary mt-2 mb-8">Bab {{ $chapter['urutan'] }} dari {{ $total }} · {{ $chapter['bagian'] }}</p>

    <section class="rounded-[1.25rem] border-2 border-navy bg-white p-6 mb-10"
             aria-labelledby="audio-narasi-judul"
             x-data="chapterAudio({{ \Illuminate\Support\Js::from($audioUrl) }})">
        <h2 id="audio-narasi-judul" class="font-display font-600 text-2xl text-navy mb-4">Audio narasi · durasi {{ $durasiTeks }}</h2>
        <audio x-ref="pemutar"
               preload="none"
               src="{{ $audioUrl }}"
               aria-label="Audio narasi bab: {{ $chapter['judul'] }}"></audio>
        <div class="flex flex-wrap gap-3 mb-4">
            <button type="button"
                    class="min-h-[3.5rem] min-w-[3.5rem] px-5 rounded-full border-2 border-navy bg-white font-600 text-navy"
                    x-on:click="toggle()"
                    x-bind:aria-pressed="memutar ? 'true' : 'false'"
                    x-text="memutar ? 'Jeda narasi' : 'Putar narasi'"></button>
            <button type="button"
                    class="min-h-[3.5rem] min-w-[3.5rem] px-5 rounded-full border-2 border-navy bg-white font-600 text-navy"
                    x-on:click="mundur()">Mundur 10 detik</button>
            <button type="button"
                    class="min-h-[3.5rem] min-w-[3.5rem] px-5 rounded-full border-2 border-navy bg-white font-600 text-navy"
                    x-on:click="maju()">Maju 10 detik</button>
        </div>
        <p class="text-lg text-ink mb-2" x-text="posisiTeks" aria-hidden="true"></p>
        <p class="text-lg text-secondary">Dengarkan narasi, atau baca teks di bawah dengan pembaca layarmu. Audio tidak diputar sendiri.</p>
        <p id="status-audio-bab" role="status" class="text-lg text-ink mt-2 empty:hidden" x-text="status"></p>
    </section>

    <div class="bab-isi text-[1.375rem] leading-relaxed text-navy max-w-2xl bab-isi">{!! $chapter['html'] !!}</div>

    <nav class="flex flex-wrap gap-4 mt-12 text-lg" aria-label="Navigasi akhir bab">
        <a href="{{ route('buku-saku.book', $book['slug']) }}"
           class="inline-flex items-center min-h-[3.5rem] px-6 rounded-full border-2 border-navy font-600 text-navy no-underline">
            Kembali ke daftar isi
        </a>
        @if ($next)
            <a href="{{ route('buku-saku.chapter', [$book['slug'], $next['slug']]) }}"
               class="inline-flex items-center min-h-[3.5rem] px-6 rounded-full border-2 border-navy font-600 text-navy no-underline">
                Bab {{ ($chapter['urutan'] ?? 0) + 1 }}: {{ $next['judul'] }}
            </a>
        @endif
    </nav>
</div>