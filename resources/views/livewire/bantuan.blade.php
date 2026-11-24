<div data-voice-page="bantuan" class="max-w-3xl mx-auto px-4 sidebar:px-8 py-6 sidebar:py-8 space-y-6">
    <h1 class="font-display font-600 text-2xl text-ink">Bantuan</h1>
    <p class="text-ink">Belajar bahasa dengan keyboard dan suara. Dirancang agar mudah diakses.</p>

    <section aria-labelledby="bantuan-keyboard" class="bg-white rounded-2xl border-2 border-navy p-5 space-y-3">
        <h2 id="bantuan-keyboard" class="font-display font-600 text-lg text-ink">Memakai dengan keyboard dan pembaca layar</h2>
        <p class="text-ink">Setiap halaman diawali tautan <strong>Lewati ke konten</strong>. Di halaman percakapan ada juga <strong>Lewati ke kolom tulis</strong>. Tautan ini muncul saat kamu menekan Tab pertama kali.</p>
        <p class="text-ink">Halaman punya bagian header, navigasi, konten utama, dan footer, sehingga pembaca layar bisa melompat antarbagian. Di percakapan, pesan baru dibacakan otomatis dengan nama pengirimnya (<em>Kamu</em> atau <em>Teman bicara</em>), dan status seperti "Teman sedang membalas" atau "Analisis selesai" diumumkan tanpa memindahkan fokus.</p>
        <p class="text-ink">Tidak ada pintasan satu tombol; semua tindakan memakai tombol biasa yang bisa dijangkau dengan Tab.</p>
    </section>

    <section aria-labelledby="bantuan-tombol" class="bg-white rounded-2xl border-2 border-navy p-5 space-y-3">
        <h2 id="bantuan-tombol" class="font-display font-600 text-lg text-ink">Tombol standar</h2>
        <table class="w-full text-left text-ink">
            <caption class="sr-only">Tombol keyboard dan fungsinya</caption>
            <thead>
                <tr class="border-b border-line-card">
                    <th scope="col" class="py-2 pr-4">Tombol</th>
                    <th scope="col" class="py-2">Fungsi</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-line-card"><th scope="row" class="py-2 pr-4"><kbd>Tab</kbd></th><td class="py-2">Pindah ke tautan, tombol, atau kolom berikutnya.</td></tr>
                <tr class="border-b border-line-card"><th scope="row" class="py-2 pr-4"><kbd>Shift</kbd>+<kbd>Tab</kbd></th><td class="py-2">Kembali ke elemen sebelumnya.</td></tr>
                <tr class="border-b border-line-card"><th scope="row" class="py-2 pr-4"><kbd>Enter</kbd></th><td class="py-2">Menjalankan tombol atau tautan. Di kolom tulis percakapan, Enter mengirim pesan (tidak mengirim saat papan ketik Mandarin/IME masih menyusun huruf).</td></tr>
                <tr class="border-b border-line-card"><th scope="row" class="py-2 pr-4"><kbd>Shift</kbd>+<kbd>Enter</kbd></th><td class="py-2">Baris baru di kolom tulis percakapan.</td></tr>
                <tr><th scope="row" class="py-2 pr-4"><kbd>Escape</kbd></th><td class="py-2">Menutup panel Tampilan dan mengembalikan fokus ke tombolnya.</td></tr>
            </tbody>
        </table>
    </section>

    <section aria-labelledby="bantuan-suara" class="bg-white rounded-2xl border-2 border-navy p-5 space-y-3">
        <h2 id="bantuan-suara" class="font-display font-600 text-lg text-ink">Memakai input suara</h2>
        <p class="text-ink">Input suara memakai Web Speech API dan hanya berjalan di <strong>Chrome atau Edge</strong>. Izinkan mikrofon saat browser meminta.</p>
        <ol class="list-decimal pl-6 text-ink space-y-1">
            <li>Di percakapan, tekan tombol <strong>Mulai bicara</strong>.</li>
            <li>Bicaralah. Tombol berubah menjadi <strong>Berhenti</strong> (berstatus ditekan).</li>
            <li>Tekan <strong>Berhenti</strong>; apa yang terdengar langsung dikirim apa adanya.</li>
        </ol>
        <p class="text-ink">Setiap balasan punya tombol <strong>Dengarkan</strong> untuk memutar suaranya.</p>
    </section>

    <section aria-labelledby="bantuan-mode-suara" class="bg-white rounded-2xl border-2 border-navy p-5 space-y-3">
        <h2 id="bantuan-mode-suara" class="font-display font-600 text-lg text-ink">Mode suara</h2>
        <p class="text-ink">Mode suara adalah pelengkap untuk memakai aplikasi dengan perintah lisan. Pembaca layar dan keyboard tetap jalur utama. Mode ini mati secara bawaan; nyalakan dengan tombol <strong>Mode suara</strong> di bagian paling atas halaman, dan matikan dengan tombol <strong>Berhenti</strong> atau perintah "berhenti". Mode suara dan Mode pembaca layar tidak bisa aktif bersamaan.</p>
        <h3 class="font-700 text-ink">Perintah</h3>
        <ul class="list-disc pl-6 text-ink space-y-1">
            <li>Nama halaman, boleh dengan kata seperti "buka", "masuk ke", "mau", "tolong", atau "dong": hari ini, kartu (flashcard), kamus, ujian (kuis), isian, game (gim, permainan, main game), pemburu kesalahan, dengar dan pilih, ngobrol (obrolan, percakapan, chat), ucapan (pengucapan), kata bicara (kamus bicara), progress (kemajuan), bantuan (dan raba lalu dengar, kamera, potret bila fitur itu aktif). Contoh: "masuk ke game", "tes obrolan".</li>
            <li>"Mulai" (atau "main", "ayo mulai"): di halaman Game membuka satu-satunya game, atau membacakan pilihan bernomor; "mulai pemburu kesalahan" atau "main dengar dan pilih" langsung membuka game itu. Di pemilihan skenario Ngobrol, skenario dibacakan bernomor. Di dalam game, membacakan soal atau menekan "Main lagi".</li>
            <li>"Baca halaman", "daftar tindakan", lalu "nomor satu" sampai "nomor dua belas" (juga "pilih satu", "jawab tiga", "opsi empat", "yang pertama", atau "pilihan a" sampai "d"); "lanjut" untuk daftar berikutnya.</li>
            <li>"Kembali", "ulangi", "perintah" (daftar perintah untuk halaman ini), "berhenti"; "ya" atau "tidak" untuk konfirmasi.</li>
            <li>Di kolom isian: "isi" untuk mulai mendikte dan "selesai isi" untuk berhenti. Teks tidak dikirim otomatis.</li>
            <li>Kata bicara: "cari" lalu kata, "dengarkan inggris". Game: "satu" sampai "empat", "ulangi", "lanjut", "penjelasan". Kartu: "balik", "berikutnya", "dengarkan". Ngobrol: ucapkan pesanmu; "selesai" untuk menyelesaikan sesi.</li>
            <li>Kamus: "cari" lalu kata; hasil dibacakan tiga-tiga, "lanjut" untuk berikutnya, "nomor dua" untuk mendengar satu hasil; "bahasa mandarin", "bahasa inggris", "level HSK satu".</li>
            <li>Ujian: sebut "mandarin", "inggris", atau "tes hari ini" untuk mulai. Saat mengerjakan: "satu" sampai "empat" untuk menjawab, "lanjut", "sebelumnya", "soal lima", "belum dijawab", lalu "kumpulkan" (selalu ditanya dulu). Jawaban tidak bisa diubah setelah dikumpulkan; "coba lagi" untuk ujian baru.</li>
            <li>Latihan Isian: "isi" lalu pinyinnya (misalnya "isi ni hao"), atau "isi hanzi" lalu ucapkan jawabannya dalam bahasa Mandarin; "lanjut", "soal tiga", "analisis", "reset" (ditanya dulu), "mode hanzi", "jumlah sepuluh", "urutan acak".</li>
            <li>Cek Ucapan: "cari" lalu kata, "nomor satu" untuk memilih, "dengarkan" untuk contoh, "rekam" (setelah bunyi, ucapkan katanya; sekitar tiga detik), "coba lagi", "ganti kata".</li>
            <li>Bantuan: "baca halaman" untuk daftar bagian, "nomor tiga" untuk satu bagian. Halaman awal setelah daftar: "tanggal hari ini", "jalur mandarin", "mulai belajar".</li>
            <li>Bila perintah tidak dikenali, aplikasi mengatakan apa yang terdengar ("Saya dengar: …") dan perintah yang bisa dipakai di halaman itu. Satu huruf yang salah dengar masih dikenali; bila ada dua kemungkinan, aplikasi bertanya "satu atau dua".</li>
        </ul>
        <h3 class="font-700 text-ink">Batas</h3>
        <ul class="list-disc pl-6 text-ink space-y-1">
            <li>Hanya berjalan di Chrome atau Edge dan butuh internet.</li>
            <li>Suaramu diproses oleh penyedia pengenalan suara browser. Aplikasi ini hanya menerima teksnya untuk dikenali sebagai perintah dan tidak menyimpan suara maupun teks itu.</li>
            <li>Akurasi pengenalan bervariasi. Suara Mandarin harus terpasang di perangkat agar teks Mandarin terbaca benar.</li>
            <li>Setelah pindah halaman, mikrofon perlu satu ketukan atau Enter pada tombol Mode suara untuk aktif lagi.</li>
            <li>Belum diuji dengan pengguna tunanetra.</li>
        </ul>
    </section>

    <section aria-labelledby="bantuan-tampilan" class="bg-white rounded-2xl border-2 border-navy p-5 space-y-3">
        <h2 id="bantuan-tampilan" class="font-display font-600 text-lg text-ink">Mengatur tampilan</h2>
        <p class="text-ink">Buka tombol <strong>Tampilan</strong> di header. Pilihan tersimpan di browser ini:</p>
        <ul class="list-disc pl-6 text-ink space-y-1">
            <li><strong>Ukuran teks</strong>: Normal, Besar, atau Sangat besar.</li>
            <li><strong>Mode pembaca layar</strong>: balasan tidak diputar otomatis; suara hanya diputar saat kamu menekan tombol putar.</li>
            <li><strong>Kontras di perangkat</strong>: gunakan setelan kontras atau inversi warna di sistem operasi (Windows <em>forced colors</em>, Smart Invert iOS, dll.). Aplikasi tidak lagi memiliki mode kontras tinggi sendiri.</li>
        </ul>
        <p class="text-ink">Animasi dikurangi otomatis bila sistem operasimu diatur untuk mengurangi gerakan.</p>
    </section>

    <section id="pasang" aria-labelledby="bantuan-pasang" class="bg-white rounded-2xl border-2 border-navy p-5 space-y-3">
        <h2 id="bantuan-pasang" class="font-display font-600 text-lg text-ink">Memasang Tuturnusa sebagai aplikasi</h2>
        <p class="text-ink">Langkah-langkah berikut bisa dibacakan kepada siswa atau pengajar. Setelah dipasang, Buku Saku tetap bisa dipakai tanpa internet untuk halaman dan audio yang sudah pernah dibuka.</p>
        <h3 class="font-700 text-ink">iPhone atau iPad (Safari)</h3>
        <ol class="list-decimal pl-6 text-ink space-y-1">
            <li>Buka tuturnusa di Safari.</li>
            <li>Ketuk ikon <strong>Bagikan</strong>.</li>
            <li>Pilih <strong>Tambahkan ke Layar Utama</strong>, lalu ketuk <strong>Tambah</strong>.</li>
        </ol>
        <h3 class="font-700 text-ink">Android (Chrome atau Edge)</h3>
        <ol class="list-decimal pl-6 text-ink space-y-1">
            <li>Buka beranda Tuturnusa.</li>
            <li>Jika browser menawarkan, ketuk <strong>Pasang aplikasi</strong> atau <strong>Tambahkan ke layar utama</strong>.</li>
            <li>Jika tidak muncul, buka menu browser (titik tiga) lalu pilih <strong>Pasang aplikasi</strong> atau <strong>Tambahkan ke layar utama</strong>.</li>
        </ol>
        <h3 class="font-700 text-ink">Windows atau macOS (Chrome atau Edge)</h3>
        <ol class="list-decimal pl-6 text-ink space-y-1">
            <li>Buka beranda Tuturnusa.</li>
            <li>Di bilah alamat, ketuk ikon pemasangan atau buka menu lalu pilih <strong>Pasang Tuturnusa</strong>.</li>
            <li>Konfirmasi pemasangan. Aplikasi akan terbuka di jendela sendiri.</li>
        </ol>
        <p class="text-ink">Ngobrol dan Ucapan tetap membutuhkan koneksi internet.</p>
    </section>

    <section aria-labelledby="bantuan-privasi" class="bg-white rounded-2xl border-2 border-navy p-5 space-y-3">
        <h2 id="bantuan-privasi" class="font-display font-600 text-lg text-ink">Privasi dan data</h2>
        <p class="text-ink">Percakapan Ngobrol disimpan di database sementara dan dihapus otomatis setiap jam setelah sekitar 24 jam sejak dimulai (pemangkasan bisa menyisakan data mendekati 25 jam).</p>
        <p class="text-ink">Rekaman dan transkrip Ucapan serta teks yang dikirim ke penyedia AI dikirim ke layanan terkait untuk diproses. Kami tidak menyimpan rekaman suara Mode suara di server aplikasi.</p>
        <p class="text-ink">Log, cadangan, dan kebijakan pihak ketiga (penyedia AI, hosting) memiliki aturan retensi sendiri yang tidak kami kendalikan penuh.</p>
    </section>

    <section aria-labelledby="bantuan-pernyataan" class="bg-white rounded-2xl border-2 border-navy p-5 space-y-3">
        <h2 id="bantuan-pernyataan" class="font-display font-600 text-lg text-ink">Pernyataan aksesibilitas</h2>
        <p class="text-ink">Pernyataan aksesibilitas (draf): kami berupaya mengikuti pedoman WCAG 2.2 AA. Aplikasi ini dirancang untuk pembaca layar; pengujian oleh tim dengan Narator sedang berlangsung dan belum dilakukan dengan pengguna tunanetra. Masukan Anda sangat kami harapkan.</p>
    </section>
</div>
