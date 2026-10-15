<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'TUTURNUSA' }}</title>
    {{-- Applies saved display settings before first paint (see displaySettings() in app.js). --}}
    <script>
        try {
            var tampilan = JSON.parse(localStorage.getItem('tampilan') || '{}');
            var kelas = document.documentElement.classList;
            if (tampilan.ukuran === 'besar') kelas.add('teks-besar');
            if (tampilan.ukuran === 'sangat-besar') kelas.add('teks-sangat-besar');
            if (tampilan.kontras) kelas.add('kontras-tinggi');
            if (tampilan.pembacaLayar) kelas.add('mode-pembaca-layar');
            if (tampilan.lega) kelas.add('teks-lega');
        } catch (e) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-cream text-ink font-sans antialiased min-h-screen">
    {{-- Skip links: first focusable elements on every page, visible only on focus. Pages add more via @push('skip-links'). --}}
    <a href="#konten" class="skip-link">Lewati ke konten</a>
    @stack('skip-links')

    {{--
        "Mode suara" (resources/js/voice/engine.js): the first focusable control after the skip links.
        A complement to the semantic markup and screen readers, never a replacement. Off by default.
    --}}
    @php
        $voiceRoutes = collect(['dashboard', 'cards.index', 'kamus', 'ujian', 'latihan-isian', 'game', 'game.error-hunter', 'game.listen-pick', 'conversation', 'pronunciation-check', 'kata-bicara', 'progress', 'bantuan'])
            ->when(config('scan.enabled'), fn ($routes) => $routes->push('scan.object'))
            ->mapWithKeys(fn ($name) => [$name => route($name)]);
    @endphp
    <div x-data="voiceModeBar({{ \Illuminate\Support\Js::from(route('voice.command')) }}, {{ \Illuminate\Support\Js::from($voiceRoutes) }})"
         class="bg-card border-b border-line-card px-4 py-2 flex flex-wrap items-center gap-3">
        <button type="button" id="tombol-mode-suara" x-ref="tombol" x-on:click="toggle()"
                aria-pressed="false" x-bind:aria-pressed="aktif ? 'true' : 'false'"
                x-bind:aria-disabled="didukung ? 'false' : 'true'" aria-describedby="mode-suara-catatan"
                class="min-h-[44px] px-5 rounded-xl bg-navy text-white font-700 aria-disabled:opacity-60">
            <span aria-hidden="true">🎙️</span> Mode suara
        </button>
        <button type="button" x-show="aktif" x-cloak x-on:click="berhenti()"
                class="min-h-[44px] px-5 rounded-xl border-2 border-red-700 text-red-700 font-700">Berhenti</button>
        <p id="mode-suara-catatan" class="text-sm text-ink-soft">
            <span x-show="!didukung">Mode suara membutuhkan Chrome atau Edge</span>
            <span x-show="didukung">Pelengkap pembaca layar; mati secara bawaan.</span>
        </p>
        <p id="mode-suara-status" role="status" class="w-full text-sm text-ink empty:hidden"></p>

        {{-- One-time consent before the first microphone use; only the yes/no flag is stored. --}}
        <div x-show="mintaSetuju" x-cloak role="group" aria-labelledby="mode-suara-setuju-judul" class="w-full rounded-xl border-2 border-navy p-4 space-y-3">
            <p id="mode-suara-setuju-judul" class="font-700 text-ink">Sebelum memakai Mode suara</p>
            <p class="text-ink">Mode suara memakai pengenalan suara bawaan browser (Chrome atau Edge). Suaramu dikirim ke penyedia browser untuk diubah menjadi teks. Aplikasi ini tidak menyimpan suara.</p>
            <div class="flex flex-wrap gap-2">
                <button type="button" x-ref="setuju" x-on:click="setuju()" class="min-h-[44px] px-5 rounded-xl bg-navy text-white font-700">Setuju dan aktifkan</button>
                <button type="button" x-on:click="batal()" class="min-h-[44px] px-5 rounded-xl border border-line-card text-ink font-700">Batal</button>
            </div>
        </div>
    </div>

    <div class="sidebar:flex min-h-screen">
        {{-- Desktop sidebar --}}
        <aside class="hidden sidebar:flex sidebar:flex-col w-[252px] shrink-0 bg-navy text-white px-5 py-6">
            <div class="flex items-center gap-2 mb-8 px-2">
                <img src="{{ asset('images/landing/logo-tuturnusa-putih.png') }}" alt="" width="286" height="185" class="w-12 h-auto">
                <span class="font-display font-600 text-lg">TUTURNUSA</span>
            </div>

            @php
                // Single source for the sidebar groups, the mobile bottom bar and its "Lainnya" panel.
                $navGroups = [
                    'belajar' => ['judul' => 'Belajar', 'items' => [
                        ['route' => 'dashboard', 'label' => 'Hari Ini', 'icon' => 'home'],
                        ['route' => 'conversation', 'label' => 'Ngobrol', 'icon' => 'chat'],
                        ['route' => 'pronunciation-check', 'label' => 'Ucapan', 'icon' => 'mic'],
                        ['route' => 'kata-bicara', 'label' => 'Kata bicara', 'icon' => 'translate'],
                        ...(config('scan.enabled') ? [['route' => 'scan.object', 'label' => 'Raba lalu dengar', 'icon' => 'camera']] : []),
                    ]],
                    'latihan' => ['judul' => 'Latihan', 'items' => [
                        ['route' => 'cards.index', 'label' => 'Kartu', 'icon' => 'cards'],
                        ['route' => 'game', 'aktif' => 'game*', 'label' => 'Game', 'icon' => 'target'],
                        ['route' => 'latihan-isian', 'label' => 'Isian', 'icon' => 'keyboard'],
                        ['route' => 'ujian', 'label' => 'Ujian', 'icon' => 'document'],
                    ]],
                    'lainnya' => ['judul' => 'Lainnya', 'items' => [
                        ['route' => 'progress', 'label' => 'Progress', 'icon' => 'chart'],
                        ['route' => 'kamus', 'label' => 'Kamus', 'icon' => 'search'],
                        ['route' => 'bantuan', 'label' => 'Bantuan', 'icon' => 'help'],
                    ]],
                ];
                $navAktif = fn (array $item) => request()->routeIs($item['aktif'] ?? $item['route'].'*');
                $navItems = collect($navGroups)->pluck('items')->flatten(1)->all();
            @endphp
            <nav aria-label="Navigasi utama" class="flex flex-col gap-5">
                @foreach ($navGroups as $key => $group)
                    @php $groupAktif = collect($group['items'])->contains($navAktif); @endphp
                    @if ($key === 'belajar')
                        {{-- Always open: a plain heading + list. --}}
                        <div>
                            <h2 id="nav-{{ $key }}" class="px-3 mb-2 text-xs font-700 uppercase tracking-wide text-white/80">{{ $group['judul'] }}</h2>
                            <ul aria-labelledby="nav-{{ $key }}" class="flex flex-col gap-1.5">
                                @foreach ($group['items'] as $item)
                                    <li>
                                        <a href="{{ route($item['route']) }}"
                                           @if ($navAktif($item)) aria-current="page" @endif
                                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-600 text-sm transition
                                                  {{ $navAktif($item) ? 'bg-white/10 text-white shadow-[inset_3px_0_0_#F9B934]' : 'text-white/80 hover:bg-white/5 hover:text-white' }}">
                                            <span aria-hidden="true"><x-landing.icon :name="$item['icon']" class="w-5 h-5" /></span>
                                            <span>{{ $item['label'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        {{-- Native disclosure: collapsed by default, open when the current page is inside. --}}
                        <details @if ($groupAktif) open @endif>
                            <summary id="nav-{{ $key }}" class="flex items-center min-h-[44px] px-3 rounded-xl cursor-pointer text-xs font-700 uppercase tracking-wide text-white/80 hover:text-white">{{ $group['judul'] }}</summary>
                            <ul aria-labelledby="nav-{{ $key }}" class="flex flex-col gap-1.5 mt-1">
                                @foreach ($group['items'] as $item)
                                    <li>
                                        <a href="{{ route($item['route']) }}"
                                           @if ($navAktif($item)) aria-current="page" @endif
                                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-600 text-sm transition
                                                  {{ $navAktif($item) ? 'bg-white/10 text-white shadow-[inset_3px_0_0_#F9B934]' : 'text-white/80 hover:bg-white/5 hover:text-white' }}">
                                            <span aria-hidden="true"><x-landing.icon :name="$item['icon']" class="w-5 h-5" /></span>
                                            <span>{{ $item['label'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                @endforeach
            </nav>

            <div class="mt-auto pt-6">
                <div class="rounded-2xl bg-navy-deep p-4 mb-4">
                    <p class="font-hand text-2xl text-orange leading-none">Keep going!</p>
                    <p class="text-xs text-white/80 mt-1">Hari {{ auth()->user()->dayNumber() ?? '-' }} sejak mulai</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-xs text-white/80 hover:text-white underline px-3">Keluar</button>
                </form>
            </div>
        </aside>

        {{-- Main content --}}
        <div class="flex-1 min-w-0">
            {{-- Always rendered (the brand only shows on mobile, where the sidebar is hidden) so every page has a header landmark. --}}
            <header class="flex items-center justify-between gap-3 px-4 py-3 bg-navy text-white sticky top-0 z-30 sidebar:static sidebar:bg-transparent sidebar:text-ink sidebar:px-8">
                <span class="font-display font-600 sidebar:hidden">TUTURNUSA</span>
                <div class="flex items-center gap-3 ml-auto">
                    <span class="text-xs text-white/80 sidebar:text-ink-soft">Hari {{ auth()->user()->dayNumber() ?? '-' }}</span>

                    {{-- Native disclosure: opens with Enter/Space, every control inside is a plain form control. --}}
                    <details class="relative" x-data="displaySettings()" x-on:keydown.escape="$el.open = false; $el.querySelector('summary').focus()">
                        <summary class="cursor-pointer list-none flex items-center rounded-xl px-3 text-sm font-700 bg-white/10 sidebar:bg-card sidebar:border sidebar:border-line-card">Tampilan</summary>
                        <div class="absolute right-0 mt-2 w-72 max-w-[calc(100vw-2rem)] bg-card text-ink rounded-2xl border border-line-card shadow-lg p-4 space-y-4 z-50">
                            <fieldset>
                                <legend class="text-sm font-700 mb-2">Ukuran teks</legend>
                                <div class="space-y-1">
                                    @foreach (['normal' => 'Normal', 'besar' => 'Besar', 'sangat-besar' => 'Sangat besar'] as $value => $label)
                                        <label class="flex items-center gap-2 text-sm min-h-[44px]">
                                            <input type="radio" name="ukuran-teks" value="{{ $value }}" x-model="ukuran" x-on:change="simpan()">
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <label class="flex items-center gap-2 text-sm min-h-[44px]">
                                <input type="checkbox" x-model="kontras" x-on:change="simpan()">
                                Mode kontras tinggi
                            </label>
                            <label class="flex items-center gap-2 text-sm min-h-[44px]">
                                <input type="checkbox" x-model="lega" x-on:change="simpan()">
                                Jarak baris dan huruf lebih lega
                            </label>
                            <div>
                                <label class="flex items-center gap-2 text-sm min-h-[44px]">
                                    <input type="checkbox" x-model="pembacaLayar" x-on:change="simpan()" aria-describedby="petunjuk-pembaca-layar">
                                    Mode pembaca layar
                                </label>
                                <p id="petunjuk-pembaca-layar" class="text-xs text-ink-soft">Suara tidak diputar otomatis; suara hanya diputar saat kamu menekan tombol putar.</p>
                            </div>
                        </div>
                    </details>
                </div>
            </header>

            <main id="konten" tabindex="-1" class="pb-8 focus:outline-none">
                {{ $slot }}
            </main>

            <footer class="px-4 sidebar:px-8 pb-28 sidebar:pb-8 text-sm text-ink-soft">
                <p>Belajar bahasa dengan keyboard dan suara. Dirancang agar mudah diakses.</p>
                <p class="mt-1"><a href="{{ route('bantuan') }}" class="underline">Bantuan dan pernyataan aksesibilitas</a></p>
            </footer>
        </div>
    </div>

    {{--
        Mobile bottom tab bar: 8 items don't fit a bottom tab bar (it was
        designed for 4-5), so only 4 main ones show directly; the rest live
        behind a "Lainnya" sheet, grouped under the same headings as the
        sidebar. Both are derived from the same $navGroups the desktop
        sidebar uses above — never a separately maintained list — so
        icons/labels/routes can't drift between the two.
    --}}
    @php
        $mobileMainRoutes = ['dashboard', 'cards.index', 'kamus', 'conversation'];
        $mobileMainItems = collect($navItems)
            ->sortBy(fn ($item) => array_search($item['route'], $mobileMainRoutes))
            ->filter(fn ($item) => in_array($item['route'], $mobileMainRoutes, true))
            ->values();
        $mobileMoreItems = collect($navItems)
            ->reject(fn ($item) => in_array($item['route'], $mobileMainRoutes, true))
            ->values();
        $mobileMoreActive = $mobileMoreItems->contains(fn ($item) => request()->routeIs($item['aktif'] ?? $item['route'].'*'));
    @endphp
    <nav aria-label="Navigasi bawah" class="sidebar:hidden fixed bottom-0 inset-x-0 bg-card border-t border-line-card flex justify-around py-2 z-40" x-data="{ moreOpen: false }">
        @foreach ($mobileMainItems as $item)
            <a href="{{ route($item['route']) }}"
               @if (request()->routeIs($item['aktif'] ?? $item['route'].'*')) aria-current="page" @endif
               class="flex flex-col items-center justify-center gap-0.5 px-2 py-1 min-h-[44px] min-w-[44px] text-xs font-600
                      {{ request()->routeIs($item['aktif'] ?? $item['route'].'*') ? 'text-mandarin' : 'text-ink-faint' }}">
                <span class="leading-none" aria-hidden="true"><x-landing.icon :name="$item['icon']" class="w-6 h-6" /></span>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach

        <button type="button" x-on:click="moreOpen = true"
                class="flex flex-col items-center justify-center gap-0.5 px-2 py-1 min-h-[44px] min-w-[44px] text-xs font-600
                       {{ $mobileMoreActive ? 'text-mandarin' : 'text-ink-faint' }}">
            <span class="leading-none" aria-hidden="true"><x-landing.icon name="more" class="w-6 h-6" /></span>
            <span>Lainnya</span>
        </button>

        <template x-if="moreOpen">
            <div class="fixed inset-0 bg-black/40 z-50 flex items-end" x-on:click.self="moreOpen = false"
                 x-data="modalDialog()" x-on:keydown.escape="moreOpen = false">
                <div class="bg-card rounded-t-3xl w-full p-4 pb-6" role="dialog" aria-modal="true" aria-labelledby="judul-lainnya">
                    <div class="flex items-center justify-between mb-3">
                        <h2 id="judul-lainnya" class="font-display font-600 text-base text-ink">Lainnya</h2>
                        <button type="button" x-on:click="moreOpen = false" aria-label="Tutup" data-autofocus class="text-ink-soft text-xl leading-none"><span aria-hidden="true">✕</span></button>
                    </div>
                    <div class="space-y-4 max-h-[70vh] overflow-y-auto">
                        @foreach ($navGroups as $key => $group)
                            @php $groupMore = collect($group['items'])->reject(fn ($item) => in_array($item['route'], $mobileMainRoutes, true)); @endphp
                            @if ($groupMore->isNotEmpty())
                                <div>
                                    <h3 id="lainnya-{{ $key }}" class="mb-2 text-xs font-700 uppercase tracking-wide text-ink-soft">{{ $group['judul'] }}</h3>
                                    <ul aria-labelledby="lainnya-{{ $key }}" class="grid grid-cols-3 gap-2">
                                        @foreach ($groupMore as $item)
                                            <li>
                                                <a href="{{ route($item['route']) }}"
                                                   @if ($navAktif($item)) aria-current="page" @endif
                                                   class="flex flex-col items-center justify-center gap-1 px-2 py-3 min-h-[44px] rounded-xl text-xs font-600 text-center
                                                          {{ $navAktif($item) ? 'bg-mandarin-soft text-mandarin' : 'text-ink-soft hover:bg-card-alt' }}">
                                                    <span class="leading-none" aria-hidden="true"><x-landing.icon :name="$item['icon']" class="w-6 h-6" /></span>
                                                    <span>{{ $item['label'] }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </template>
    </nav>

    @livewireScripts
    @stack('scripts')
</body>
</html>
