{{--
    Sticky header: logo, section menu (anchor links), "Tampilan" controls, Masuk / Daftar.
    Mobile: the menu collapses behind a button (welcome.js). Without JS it simply stays open.
--}}
@php
    $menu = [
        ['href' => '#beranda', 'label' => 'Beranda'],
        ['href' => '#cara-kerja', 'label' => 'Cara kerja'],
        ['href' => '#coba', 'label' => 'Coba'],
        ['href' => '#fitur', 'label' => 'Fitur'],
        ['href' => '#aksesibilitas', 'label' => 'Aksesibilitas'],
        ['href' => '#status', 'label' => 'Status'],
    ];
@endphp
<header class="sticky top-0 z-40 bg-brand-paper/95 backdrop-blur border-b border-brand-line">
    <div class="max-w-6xl mx-auto px-4 py-3 flex flex-wrap items-center gap-x-6 gap-y-2">
        <a href="/" class="flex items-center gap-2 min-h-[44px] text-brand-navy font-extrabold leading-tight" data-tombol>
            <img src="{{ asset('images/landing/logo-tuturnusa.png') }}" alt="" width="286" height="185" class="w-14 h-auto">
            <span>TUTURNUSA<span class="sr-only">, beranda</span></span>
        </a>

        <button type="button" data-menu-toggle data-js-only hidden aria-controls="menu-utama" aria-expanded="false" aria-label="Buka menu"
                class="lg:hidden ml-auto min-h-[44px] min-w-[44px] rounded-xl border-2 border-brand-navy text-brand-navy">
            <x-landing.icon name="menu" class="w-6 h-6 mx-auto" />
        </button>

        <div id="menu-utama" data-menu class="w-full lg:w-auto lg:flex-1 flex flex-col lg:flex-row lg:items-center gap-4 lg:gap-6">
            <nav aria-label="Bagian halaman" class="lg:flex-1">
                <ul class="flex flex-col lg:flex-row lg:justify-center gap-1 lg:gap-5">
                    @foreach ($menu as $i => $item)
                        <li>
                            <a href="{{ $item['href'] }}" @if ($i === 0) aria-current="location" @endif
                               class="inline-flex items-center min-h-[44px] whitespace-nowrap text-sm font-semibold text-brand-navy border-b-[3px] {{ $i === 0 ? 'border-brand-yellow font-bold' : 'border-transparent hover:border-brand-line' }}">{{ $item['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="flex flex-wrap items-center gap-3">
                {{-- Text size and contrast: same setting as the "Tampilan" menu inside the app. --}}
                <div data-js-only hidden>
                    <div role="group" aria-labelledby="label-tampilan" class="flex items-center gap-1">
                        <span id="label-tampilan" class="text-sm text-ink-soft mr-1">Tampilan</span>
                        <button type="button" data-ukuran="normal" aria-pressed="true" aria-label="Ukuran teks normal"
                                class="min-h-[44px] min-w-[44px] rounded-lg text-sm font-bold text-brand-navy aria-pressed:bg-brand-sky-icon">A</button>
                        <button type="button" data-ukuran="lebih" aria-pressed="false" aria-label="Perbesar teks"
                                class="min-h-[44px] min-w-[44px] rounded-lg text-base font-bold text-brand-navy aria-pressed:bg-brand-sky-icon">A+</button>
                        <button type="button" data-kontras aria-pressed="false" aria-label="Mode kontras tinggi"
                                class="min-h-[44px] min-w-[44px] rounded-lg text-brand-navy flex items-center justify-center aria-pressed:bg-brand-sky-icon">
                            <x-landing.icon name="contrast" class="w-5 h-5" />
                        </button>
                    </div>
                    <p id="tampilan-status" role="status" class="sr-only"></p>
                </div>

                <a href="{{ route('login') }}" data-tombol
                   class="inline-flex items-center justify-center min-h-[44px] px-5 rounded-xl border-2 border-brand-navy bg-white text-brand-navy text-sm font-bold">Masuk</a>
                @if (config('registration.enabled'))
                    <a href="{{ route('register') }}" data-tombol
                       class="inline-flex items-center justify-center min-h-[44px] px-5 rounded-xl bg-brand-yellow text-brand-navy text-sm font-bold">Daftar</a>
                @endif
            </div>
        </div>
    </div>
</header>
