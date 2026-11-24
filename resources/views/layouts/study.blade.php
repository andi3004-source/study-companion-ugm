<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="only light">
    <meta name="theme-color" content="#14294D">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    <title>{{ $title ?? 'Tuturnusa' }}</title>
    <script>
        try {
            var tampilan = JSON.parse(localStorage.getItem('tampilan') || '{}');
            var kelas = document.documentElement.classList;
            if (tampilan.ukuran === 'besar') kelas.add('teks-besar');
            if (tampilan.ukuran === 'sangat-besar') kelas.add('teks-sangat-besar');
            if (tampilan.pembacaLayar) kelas.add('mode-pembaca-layar');
            if (tampilan.lega) kelas.add('teks-lega');
        } catch (e) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-white text-ink font-sans antialiased min-h-screen">
    @include('components.voice-icons')
    <a href="#konten" class="skip-link">Lewati ke konten</a>
    @stack('skip-links')

    @php
        $voiceRoutes = collect(['home', 'conversation', 'pronunciation-check', 'buku-saku', 'bantuan'])
            ->mapWithKeys(fn ($name) => [$name => route($name)]);
    @endphp
    <header class="px-4 border-b-2 border-navy bg-white"
            x-data="voiceModeBar({{ \Illuminate\Support\Js::from(route('voice.command')) }}, {{ \Illuminate\Support\Js::from($voiceRoutes) }})">
        <div class="flex flex-row-reverse flex-wrap items-center justify-between gap-x-4 gap-y-2 py-3 max-w-[75rem] mx-auto w-full">
            <div class="flex flex-col items-end gap-1 min-w-0">
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button type="button" id="tombol-mode-suara" x-ref="tombol" x-on:click="toggle()"
                            aria-pressed="false" x-bind:aria-pressed="aktif ? 'true' : 'false'"
                            x-bind:aria-disabled="didukung ? 'false' : 'true'" aria-describedby="mode-suara-catatan"
                            class="min-h-[3.5rem] min-w-[3.5rem] px-5 rounded-full border-2 border-navy bg-white text-navy font-600 aria-disabled:opacity-60 inline-flex items-center gap-1">
                        <svg class="o-ic w-5 h-5 shrink-0" aria-hidden="true"><use href="#i-mic"/></svg>
                        Mode suara: <span x-text="aktif ? 'nyala' : 'mati'"></span>
                    </button>
                    <button type="button" x-show="aktif" x-cloak x-on:click="berhenti()"
                            class="min-h-[3.5rem] px-5 rounded-full border-2 border-red-700 text-red-700 font-700">Berhenti</button>
                </div>
                <p id="mode-suara-catatan" class="text-xs text-secondary text-right max-w-xs">
                    <span x-show="!didukung">Mode suara membutuhkan Chrome atau Edge</span>
                    <span x-show="didukung" x-cloak>Tekan lagi untuk satu perintah. Mati secara bawaan.</span>
                </p>
                <p id="mode-suara-status" role="status" class="text-xs text-ink empty:hidden text-right max-w-xs"></p>
            </div>
            <a id="logo-beranda" href="{{ route('home') }}" aria-label="Tuturnusa, beranda"
               class="inline-flex items-center gap-3 no-underline text-navy font-display font-600 text-2xl shrink-0">
                <span class="inline-flex items-center justify-center w-12 h-12 rounded-[12px] bg-navy shrink-0" aria-hidden="true">
                    <svg class="o-ic w-7 h-7 text-orange" aria-hidden="true"><use href="#i-book"/></svg>
                </span>
                TUTURNUSA
            </a>
        </div>

        <div x-show="mintaSetuju" x-cloak role="group" aria-labelledby="mode-suara-setuju-judul"
             class="max-w-[75rem] mx-auto w-full rounded-xl border-2 border-navy p-4 space-y-3 mb-4">
            <p id="mode-suara-setuju-judul" class="font-700 text-ink">Sebelum memakai Mode suara</p>
            <p class="text-ink">Mode suara memakai pengenalan suara bawaan browser (Chrome atau Edge). Suaramu dikirim ke penyedia browser untuk diubah menjadi teks. Aplikasi ini tidak menyimpan suara.</p>
            <div class="flex flex-wrap gap-2">
                <button type="button" x-ref="setuju" x-on:click="setuju()" class="min-h-[44px] px-5 rounded-xl bg-navy text-white font-700">Setuju dan aktifkan</button>
                <button type="button" x-on:click="batal()" class="min-h-[44px] px-5 rounded-xl border border-line-card text-ink font-700">Batal</button>
            </div>
        </div>
    </header>

    <main id="konten" tabindex="-1" class="pb-8 focus:outline-none">
        {{ $slot }}
    </main>

    <footer class="px-4 pb-8 text-lg text-ink-soft border-t border-line pt-6 space-y-2">
        <p class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <svg class="o-ic w-5 h-5 shrink-0" aria-hidden="true"><use href="#i-circle-help"/></svg>
            <a href="{{ route('bantuan') }}" class="underline text-navy">Bantuan dan aksesibilitas</a>
            <a href="{{ route('bantuan') }}#tampilan" class="underline text-navy">Ukuran teks</a>
        </p>
    </footer>
    @livewireScripts
</body>
</html>