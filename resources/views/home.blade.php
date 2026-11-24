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
</div>