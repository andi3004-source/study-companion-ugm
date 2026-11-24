<div class="max-w-[720px] mx-auto px-4 py-8 md:py-12">
    <h1 class="font-display font-600 text-[2.125rem] md:text-[3rem] leading-tight text-navy mb-3">Mau belajar apa hari ini?</h1>
    <p class="text-xl text-secondary mb-10">Pilih salah satu. Kamu bisa memakai keyboard, pembaca layar, atau Mode suara di atas.</p>

    <ul class="daftar-kartu-beranda flex flex-col gap-5 md:gap-8 list-none p-0 m-0">
        <li class="kartu-fitur-item">
            <a href="{{ route('conversation') }}" class="kartu-fitur block rounded-3xl border-2 border-navy bg-white p-8 min-h-[10rem] md:min-h-[18.75rem] flex flex-col no-underline text-ink hover:border-navy focus-visible:outline-none">
                <span class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-orange text-navy mb-4" aria-hidden="true">
                    <svg class="o-ic w-8 h-8" aria-hidden="true"><use href="#i-message-circle"/></svg>
                </span>
                <h2 class="font-display font-600 text-[1.875rem] text-navy mb-2">Ngobrol</h2>
                <p class="text-lg text-secondary flex-1">Bercakap dengan teman AI. Ketik atau bicara.</p>
                <span class="font-600 text-lg text-navy mt-6">Buka <span aria-hidden="true">›</span></span>
            </a>
        </li>
        <li class="kartu-fitur-item">
            <a href="{{ route('pronunciation-check') }}" class="kartu-fitur block rounded-3xl border-2 border-navy bg-white p-8 min-h-[10rem] md:min-h-[18.75rem] flex flex-col no-underline text-ink hover:border-navy focus-visible:outline-none">
                <span class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-orange text-navy mb-4" aria-hidden="true">
                    <svg class="o-ic w-8 h-8" aria-hidden="true"><use href="#i-volume-2"/></svg>
                </span>
                <h2 class="font-display font-600 text-[1.875rem] text-navy mb-2">Ucapan</h2>
                <p class="text-lg text-secondary flex-1">Ucapkan satu kata. Dengarkan apakah sudah tepat.</p>
                <span class="font-600 text-lg text-navy mt-6">Buka <span aria-hidden="true">›</span></span>
            </a>
        </li>
        <li class="kartu-fitur-item">
            <a href="{{ route('buku-saku') }}" class="kartu-fitur block rounded-3xl border-2 border-navy bg-white p-8 min-h-[10rem] md:min-h-[18.75rem] flex flex-col no-underline text-ink hover:border-navy focus-visible:outline-none">
                <span class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-orange text-navy mb-4" aria-hidden="true">
                    <svg class="o-ic w-8 h-8" aria-hidden="true"><use href="#i-book-open"/></svg>
                </span>
                <h2 class="font-display font-600 text-[1.875rem] text-navy mb-2">Buku Saku</h2>
                <p class="text-lg text-secondary flex-1">Dengarkan panduan kegiatan sehari-hari dan senam.</p>
                <span class="font-600 text-lg text-navy mt-6">Buka <span aria-hidden="true">›</span></span>
            </a>
        </li>
    </ul>

    <section id="pasang-aplikasi" aria-labelledby="pasang-aplikasi-judul"
             class="mt-10 md:mt-12 rounded-[1.25rem] border-2 border-dashed border-navy bg-white p-6 md:p-8 space-y-4">
        <div class="flex gap-4 items-start">
            <span class="inline-flex shrink-0 items-center justify-center w-12 h-12 rounded-xl bg-navy text-white" aria-hidden="true">
                <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/>
                </svg>
            </span>
            <div class="space-y-3 min-w-0">
                <h2 id="pasang-aplikasi-judul" class="font-display font-600 text-2xl text-navy">Pasang Tuturnusa di perangkat ini</h2>
                <p class="text-lg text-secondary">Jadi aplikasi di layar utama. Buku Saku tetap bisa didengar tanpa internet.</p>
                <h3 class="sr-only">Tindakan pemasangan</h3>
                <div class="flex flex-col gap-3">
                    <button type="button" id="tombol-pasang-aplikasi" hidden
                            class="w-full md:w-auto min-h-[3.5rem] px-8 rounded-full bg-navy text-white font-700 text-lg inline-flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 15V3"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/>
                        </svg>
                        Pasang aplikasi
                    </button>
                    <p class="text-lg">
                        <a href="{{ route('bantuan') }}#pasang" class="text-navy font-600 underline">Cara memasang untuk pengajar</a>
                    </p>
                </div>
            </div>
        </div>
    </section>
</div>