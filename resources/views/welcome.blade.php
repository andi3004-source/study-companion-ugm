{{--
    Public home page for visitors who are not logged in. Static on purpose:
    no AI features, no Livewire, no app.js, and no requests to outside
    services. resources/js/welcome.js only adds the "Tampilan" controls,
    the mobile menu, the example tabs and the speaker button; fonts are
    self-hosted. Sections are components in resources/views/components/landing.
--}}
@php
    // Feature statuses: the one source for the cards below (also checked by WelcomePageTest).
    $fitur = [
        ['nama' => 'Percakapan dengan AI', 'status' => 'Berjalan', 'ikon' => 'chat', 'isi' => 'Latihan percakapan Mandarin atau Inggris sesuai skenario, dengan koreksi singkat dalam bahasa Indonesia.'],
        ['nama' => 'Analisis kesalahan', 'status' => 'Eksperimen', 'ikon' => 'chart', 'isi' => 'Setelah sesi selesai, kesalahan dikelompokkan per jenis dan ditampilkan di halaman Progress.'],
        ['nama' => 'Kartu, Kamus, Ujian, dan Isian', 'status' => 'Berjalan', 'ikon' => 'cards', 'isi' => 'Latihan kosakata HSK dan IELTS dengan jadwal harian.'],
        ['nama' => 'Arti Indonesia untuk kosakata HSK', 'status' => 'Draf', 'ikon' => 'translate', 'isi' => 'Arti yang sudah dikoreksi diberi tanda draf sampai diperiksa penutur.'],
        ['nama' => 'Game Pemburu Kesalahan', 'status' => 'Draf', 'ikon' => 'target', 'isi' => 'Tebak jenis kesalahan dalam kalimat Mandarin. Sebagian soal masih diverifikasi.'],
        ['nama' => 'Cek Pengucapan', 'status' => 'Berjalan', 'ikon' => 'wave', 'isi' => 'Rekam satu kata dan bandingkan dengan target. Hasilnya bisa meleset karena bergantung pada pengenal suara browser.'],
        ['nama' => 'Kata bicara', 'status' => 'Draf', 'ikon' => 'book', 'isi' => 'Cari satu kata dan lihat dalam bahasa Indonesia, Inggris, dan Mandarin. Daftar katanya masih perlu dicek penutur.'],
    ];

    // Legend for every status a feature can have (StatusBadge holds the colours).
    $statusInfo = [
        'Berjalan' => ['titik' => 'bg-[#2E9B57]', 'arti' => 'Fitur sudah tersedia'],
        'Draf' => ['titik' => 'bg-[#E8890C]', 'arti' => 'Dalam tahap pengembangan'],
        'Eksperimen' => ['titik' => 'bg-[#7B55D6]', 'arti' => 'Sedang diuji'],
        'Rencana' => ['titik' => 'bg-[#7A8494]', 'arti' => 'Masih dalam perencanaan'],
    ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TUTURNUSA</title>
    <meta name="description" content="Pelatih kesalahan Mandarin dan Inggris untuk penutur Indonesia, dengan keyboard dan suara.">
    {{-- Apply the saved text size / contrast before the first paint (same 'tampilan' key as the app). --}}
    <script>
        (function () {
            var c = document.documentElement.classList;
            c.add('js');
            try {
                var t = JSON.parse(localStorage.getItem('tampilan') || '{}') || {};
                if (t.ukuran === 'besar') c.add('teks-besar');
                if (t.ukuran === 'sangat-besar') c.add('teks-sangat-besar');
                if (t.kontras) c.add('kontras-tinggi');
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/welcome.css', 'resources/js/welcome.js'])
</head>
<body class="bg-brand-paper text-ink antialiased">
    <a href="#konten" class="skip-link">Lewati ke konten utama</a>

    <x-landing.navbar />

    <main id="konten" tabindex="-1" class="focus:outline-none">
        <x-landing.hero />

        <div class="max-w-6xl mx-auto px-4 space-y-6 pb-6">
            {{-- Masalah --}}
            <section aria-labelledby="judul-masalah" class="rounded-[1.5rem] border border-brand-line bg-white/90 p-4 sm:p-8 grid gap-8 lg:grid-cols-[1fr_1.3fr] items-start" data-kartu>
                <div>
                    <x-landing.section-badge aria-hidden="true">Masalah</x-landing.section-badge>
                    <h2 id="judul-masalah" class="mt-3 text-2xl sm:text-3xl font-extrabold text-brand-navy leading-tight"><span class="sr-only">Masalah: </span>Banyak kesalahan kecil, dampak besar.</h2>
                    <p class="mt-4 text-ink">Banyak daftar kosakata HSK (standar ujian bahasa Mandarin) hanya berisi arti dalam bahasa Inggris. Kami mengaudit data yang kami pakai dan menemukan:</p>
                </div>
                <div>
                    <ul class="grid gap-4 sm:grid-cols-2">
                        <x-landing.stat-card icon="document" value="0 / 4.991" baca="0 dari 4991" source="Sumber: audit internal tim">entri kosakata HSK di data sumber kami punya arti dalam bahasa Indonesia.</x-landing.stat-card>
                        <x-landing.stat-card icon="pie" value="~16%" baca="Sekitar 16 persen" source="Sumber: pemeriksaan sampel oleh tim">sampel entri HSK <x-landing.ucap tampil="1–3" baca="1 sampai 3" /> yang kami periksa ternyata keliru.</x-landing.stat-card>
                    </ul>
                    <p class="mt-4 flex items-start gap-3 rounded-xl bg-brand-sky px-4 py-3 text-sm text-ink">
                        <x-landing.icon name="info" class="w-5 h-5 text-brand-navy" />
                        <span>Angka dari audit internal terhadap data yang dipakai aplikasi ini. Pemeriksaan sampel bisa saja belum menemukan semua kesalahan.</span>
                    </p>
                </div>
            </section>

            {{-- Cara kerja --}}
            <section id="cara-kerja" aria-labelledby="judul-cara-kerja" class="rounded-[1.5rem] border border-brand-line bg-white/90 p-4 sm:p-8 grid gap-8 lg:grid-cols-[1fr_1.9fr] items-start" data-kartu>
                <div>
                    <x-landing.section-badge aria-hidden="true">Cara Kerja</x-landing.section-badge>
                    <h2 id="judul-cara-kerja" class="mt-3 text-2xl sm:text-3xl font-extrabold text-brand-navy leading-tight"><span class="sr-only">Cara kerja: </span>Mudah, terstruktur, efektif.</h2>
                    <p class="mt-4 text-ink">TUTURNUSA membantu kamu menemukan dan memperbaiki kesalahan dengan 3 langkah sederhana.</p>
                </div>
                <ol class="flex flex-col lg:flex-row gap-8 lg:gap-12">
                    <x-landing.step-item number="1" icon="mic" title="Dengarkan / Ketik" baca="Dengarkan atau ketik">Ngobrol dengan AI (kecerdasan buatan) dalam Mandarin atau Inggris, lewat keyboard atau suara.</x-landing.step-item>
                    <x-landing.step-item number="2" icon="brain" title="Analisis">Setelah sesi selesai, AI mengelompokkan kesalahanmu per jenis dan menjelaskannya.</x-landing.step-item>
                    <x-landing.step-item number="3" icon="book" title="Belajar" :last="true">Lihat kesalahan terbanyakmu, lalu latih lewat kartu, isian, dan game.</x-landing.step-item>
                </ol>
            </section>

            {{-- Fitur --}}
            <section id="fitur" aria-labelledby="judul-fitur" class="rounded-[1.5rem] border border-brand-line bg-white/90 p-4 sm:p-8 grid gap-8 lg:grid-cols-[1fr_1.9fr] items-start" data-kartu>
                <div>
                    <x-landing.section-badge aria-hidden="true">Fitur Utama</x-landing.section-badge>
                    <h2 id="judul-fitur" class="mt-3 text-2xl sm:text-3xl font-extrabold text-brand-navy leading-tight"><span class="sr-only">Fitur utama: </span>Lebih dari sekadar koreksi.</h2>
                    <p class="mt-4 text-ink">Status ditulis apa adanya: <strong>Berjalan</strong> bisa dipakai sekarang, <strong>Draf</strong> datanya masih diperiksa, <strong>Eksperimen</strong> hasilnya bisa berubah.</p>
                </div>
                <ul class="grid gap-4 md:grid-cols-2">
                    @foreach ($fitur as $item)
                        <x-landing.feature-card :icon="$item['ikon']" :title="$item['nama']" :status="$item['status']">{{ $item['isi'] }}</x-landing.feature-card>
                    @endforeach
                </ul>
            </section>

            {{-- Coba langsung --}}
            <section id="coba" aria-labelledby="judul-coba" class="rounded-[1.5rem] border border-brand-line bg-brand-sky p-4 sm:p-8 grid gap-8 lg:grid-cols-[1fr_1fr] xl:pr-48 items-center" data-kartu>
                <div>
                    <x-landing.section-badge aria-hidden="true">Coba Langsung</x-landing.section-badge>
                    <h2 id="judul-coba" class="mt-3 text-2xl sm:text-3xl font-extrabold text-brand-navy leading-tight"><span class="sr-only">Coba langsung: </span>Rasakan pengalamannya sekarang.</h2>
                    <p class="mt-4 text-ink">Begini latihan di TUTURNUSA: jawab dengan keyboard atau suara, lalu langsung lihat koreksinya. Contoh berikut tidak memakai AI; masuk untuk mencoba sungguhan.</p>
                    <a href="{{ route('login') }}" data-tombol
                       class="mt-6 inline-flex items-center justify-center gap-2 min-h-[48px] px-6 rounded-xl bg-brand-navy text-white font-bold">Mulai coba sekarang<x-landing.icon name="arrow-right" class="w-5 h-5" /></a>
                </div>
                <x-landing.demo-widget />
            </section>

            {{-- Aksesibilitas + Status --}}
            <div class="grid gap-6 lg:grid-cols-2">
                <section id="aksesibilitas" aria-labelledby="judul-aksesibilitas" class="rounded-[1.5rem] border border-brand-line bg-white/90 p-4 sm:p-8" data-kartu>
                    <x-landing.section-badge aria-hidden="true">Aksesibilitas</x-landing.section-badge>
                    <h2 id="judul-aksesibilitas" class="mt-3 text-2xl sm:text-3xl font-extrabold text-brand-navy leading-tight"><span class="sr-only">Aksesibilitas: </span>Untuk semua orang.</h2>
                    <p class="mt-4 text-ink">Kami berupaya membuat TUTURNUSA mudah diakses oleh semua pengguna, termasuk penyandang disabilitas. Pengujian sejauh ini dilakukan oleh tim (keyboard dan pembaca layar Narator) dan masih berlangsung. Aplikasi ini <strong>belum</strong> diuji dengan pengguna tunanetra.</p>
                    <ul class="mt-6 grid gap-5 sm:grid-cols-2">
                        <x-landing.accessibility-item icon="check-circle" title="WCAG 2.2 AA">Pedoman aksesibilitas web yang kami kejar</x-landing.accessibility-item>
                        <x-landing.accessibility-item icon="zoom" title="Zoom 400%" baca="Zoom 400 persen">Tata letak tetap terbaca</x-landing.accessibility-item>
                        <x-landing.accessibility-item icon="keyboard" title="Keyboard dan pembaca layar">Semua kontrol bisa dijangkau</x-landing.accessibility-item>
                        <x-landing.accessibility-item icon="contrast" title="Mode kontras tinggi">Warna lebih jelas</x-landing.accessibility-item>
                    </ul>
                </section>

                <section id="status" aria-labelledby="judul-status" class="relative overflow-hidden rounded-[1.5rem] border border-brand-line bg-brand-mint p-4 sm:p-8 sm:pr-52" data-kartu>
                    <x-landing.section-badge tone="mint" aria-hidden="true">Status Pengembangan</x-landing.section-badge>
                    <h2 id="judul-status" class="mt-3 text-2xl sm:text-3xl font-extrabold text-brand-navy leading-tight"><span class="sr-only">Status pengembangan: </span>Transparan, bukan janji kosong.</h2>
                    <p class="mt-4 text-ink">Setiap fitur diberi status, supaya kamu tahu mana yang sudah bisa dipakai dan mana yang masih dikembangkan.</p>
                    <dl class="mt-6 grid grid-cols-[auto_1fr] gap-x-6 gap-y-3 text-sm">
                        @foreach ($statusInfo as $status => $info)
                            <dt class="flex items-center gap-2 font-bold text-brand-navy"><span class="w-3 h-3 rounded-full {{ $info['titik'] }}" aria-hidden="true"></span>{{ $status }}</dt>
                            <dd class="text-ink-soft">{{ $info['arti'] }}</dd>
                        @endforeach
                    </dl>
                    {{-- From the mockup; the note "Bersama kita belajar lebih baik" is part of the drawing. --}}
                    <img src="{{ asset('images/landing/ilustrasi-anak-ransel.png') }}" alt="" width="195" height="195"
                         class="mt-6 ml-auto w-44 h-auto sm:absolute sm:mt-0 sm:right-0 sm:bottom-0 sm:w-48">
                </section>
            </div>

            {{-- AI use in development --}}
            <section aria-labelledby="judul-ai" class="rounded-[1.5rem] border border-brand-line bg-white/90 p-4 sm:p-8" data-kartu>
                <h2 id="judul-ai" class="text-2xl font-extrabold text-brand-navy">Penggunaan AI dalam pengerjaan</h2>
                <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        'Claude Code' => 'Membantu menulis, menguji, dan merapikan kode aplikasi.',
                        'Claude' => 'Membantu diskusi rancangan dan penyusunan teks.',
                        'Gemini' => 'Model AI untuk teman ngobrol di aplikasi, dan cadangan untuk analisis kesalahan.',
                        'IBM Langflow dan Astra DB' => 'Alur analisis kesalahan sesi Ngobrol dibangun di IBM Langflow. Astra DB menjadi basis pengetahuan untuk RAG (pola kesalahan dan glosarium).',
                        'IBM Bob' => 'Asisten pengembangan di editor (peran terbatas).',
                        'Peran tim' => 'Tim menentukan arah dan keputusan desain, mengaudit data, meninjau kode, menguji, dan memverifikasi hasil.',
                    ] as $alat => $peran)
                        <div class="rounded-card border border-brand-line bg-white p-5" data-kartu>
                            <dt class="font-bold text-brand-navy">{{ $alat }}</dt>
                            <dd class="mt-1 text-sm text-ink">{{ $peran }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            {{-- FAQ --}}
            <section aria-labelledby="judul-faq" class="rounded-[1.5rem] border border-brand-line bg-white/90 p-4 sm:p-8" data-kartu>
                <h2 id="judul-faq" class="text-2xl font-extrabold text-brand-navy">Pertanyaan umum</h2>
                <div class="mt-6 grid gap-6 lg:grid-cols-3">
                    <div>
                        <h3 class="font-bold text-brand-navy">Data apa yang disimpan?</h3>
                        <p class="mt-1 text-sm text-ink">Nama, email, dan kata sandi akunmu (kata sandi disimpan dalam bentuk hash), progres belajar, isi percakapan Ngobrol beserta hasil analisisnya, catatan, dan jawaban latihan. Isi percakapan dikirim ke layanan AI untuk diproses.</p>
                    </div>
                    <div>
                        <h3 class="font-bold text-brand-navy">Apakah foto disimpan?</h3>
                        <p class="mt-1 text-sm text-ink">Tidak. Foto tidak disimpan.</p>
                    </div>
                    <div>
                        <h3 class="font-bold text-brand-navy">Browser apa yang dibutuhkan untuk suara?</h3>
                        <p class="mt-1 text-sm text-ink">Input dan output suara memakai Web Speech API, jadi gunakan Chrome atau Edge. Fitur lain bisa dipakai di browser modern apa pun.</p>
                    </div>
                </div>
            </section>
        </div>

        <x-landing.cta-banner />
    </main>

    <x-landing.footer />
</body>
</html>
