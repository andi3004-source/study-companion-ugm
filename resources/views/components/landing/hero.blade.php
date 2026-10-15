{{--
    Hero. The photo comes from the reference mockup, where the chat bubble (我很高兴。 / Wǒ hěn gāoxìng)
    and the note "Belajar jadi lebih mudah!" are already part of the image, so they are described
    in the alt text instead of being drawn again on top.
--}}
<section id="beranda" aria-labelledby="judul-utama" class="max-w-6xl mx-auto px-4 pt-10 pb-12 lg:pt-14 grid gap-10 lg:grid-cols-[1fr_1.1fr] items-center">
    <div>
        <x-landing.section-badge><x-landing.ucap tampil="Pelatih Bahasa Mandarin & Inggris" baca="Pelatih bahasa Mandarin dan Inggris" /></x-landing.section-badge>
        <h1 id="judul-utama" class="mt-4 text-[2rem] sm:text-5xl font-extrabold text-brand-navy leading-[1.1] tracking-tight">
            Belajar bahasa dengan keyboard dan suara.
        </h1>
        <p class="mt-5 text-lg text-ink max-w-xl">
            TUTURNUSA adalah pelatih kesalahan Mandarin dan Inggris untuk penutur Indonesia, dirancang agar mudah diakses, inklusif, dan ramah untuk semua.
        </p>
        <div class="mt-7 flex flex-col sm:flex-row gap-3">
            <a href="{{ route('login') }}" data-tombol
               class="inline-flex items-center justify-center gap-2 min-h-[48px] px-6 rounded-xl bg-brand-yellow text-brand-navy font-bold shadow-card">Masuk atau daftar<x-landing.icon name="arrow-right" class="w-5 h-5" /></a>
            <a href="#cara-kerja" data-tombol
               class="inline-flex items-center justify-center min-h-[48px] px-6 rounded-xl border-2 border-brand-navy bg-white text-brand-navy font-bold">Lihat cara kerja</a>
        </div>
        <p class="mt-5 flex items-center gap-2 text-sm text-ink">
            <x-landing.icon name="shield-check" class="w-5 h-5 text-brand-navy" />
            Draf yang jujur: tiap fitur kami beri status.
        </p>
    </div>

    <div>
        <img src="{{ asset('images/landing/hero-belajar.jpg') }}" width="596" height="338"
             alt="Seorang pelajar berheadphone tersenyum sambil mengetik di laptop. Balon obrolan menampilkan kalimat Mandarin wǒ hěn gāoxìng, artinya saya senang, dengan tanda benar. Catatan tangan: Belajar jadi lebih mudah!"
             class="w-full h-auto rounded-[2rem] object-cover">
    </div>
</section>
