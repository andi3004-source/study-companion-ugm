# Tuturnusa: rampingkan jadi 3 fitur untuk anak tunanetra

## Context / kenapa
Tuturnusa sekarang diarahkan untuk **anak-anak tunanetra**: tidak bisa melihat, tapi pendengaran dan fungsi kognitifnya normal, dan biasanya sudah memakai screen reader (NVDA atau JAWS di Windows, TalkBack di Android). Aplikasi yang sekarang punya 12 menu di sidebar dan 3 grup, terlalu banyak buat dinavigasi lewat suara. Targetnya: begitu dibuka langsung satu halaman dengan 3 pilihan besar. Kode yang tidak dipakai lagi dibuang supaya aplikasinya lebih ringan.

## Goals
- **Tanpa landing page dan tanpa login/daftar.** Buka aplikasi langsung masuk ke **satu halaman beranda**. Logo TUTURNUSA di pojok kiri atas dan 3 kartu besar: **Ngobrol**, **Ucapan**, **Buku Saku**.
- Tanpa sidebar dan tanpa bottom bar di HP. Tiap fitur kebuka di halamannya sendiri dengan tautan "Kembali ke beranda".
- **Buku Saku** baru: dua buku dari PDF yang diberikan, ditampilkan sebagai teks HTML yang ramah screen reader, plus audio natural (edge-tts) per bab.
- Fitur lain **dihapus total**: kode, route, view, tes, dan JS-nya.

## Non-goals
- Tidak menulis ulang ke Rust. Tetap Laravel 12 + Livewire, karena bottleneck-nya ada di AI/API, bukan di server.
- Tidak ada admin atau upload materi. Materi baru ditambahkan lewat file oleh developer.
- Ngobrol dan Ucapan tidak diubah isinya, hanya pindah layout.

## Anti-goals (gagal walau "jalan")
- PDF ditampilkan langsung di halaman. Itu tidak bisa dinavigasi dengan baik oleh screen reader.
- Audio diputar otomatis lalu bertabrakan dengan suara screen reader.
- Kartu yang cuma bagus dilihat: urutan fokus tidak jelas, nama aksesibelnya tidak deskriptif, fokusnya tidak terlihat.
- Suara TTS yang kaku (suara sistem).

## Keputusan user
- Fitur yang dipertahankan: Ngobrol, Ucapan, dan fitur ketiga (bacaan pengetahuan dasar dari PDF yang dibacakan TTS Python).
- 9 fitur lain dihapus total.
- Tidak ada landing page, login, atau daftar. Tanpa akun sama sekali, dan riwayat Ngobrol hilang begitu browser ditutup.
- TTS: **edge-tts** (suara natural, bukan voice over sistem).
- Pendekatan **A**: refactor di repo ini, tetap Laravel. Materi disimpan sebagai file Markdown, MP3 dibuat sekali lewat script Python.
- Desain dibuat di Figma (file `rmrzsfxcFAakWCD7mljbxP`) lewat figma-console, mengikuti sketsa: logo di pojok dan 3 kartu sejajar, kartu yang fokus diberi garis biru.

## Approaches yang dipertimbangkan
| | Pendekatan | Trade-off | Status |
|---|---|---|---|
| A | Refactor di repo ini, materi berupa file Markdown + MP3 pre-generated | Paling ringan, tanpa DB/admin. Tambah materi harus lewat developer | **Dipilih** |
| B | Materi di DB + admin upload + konversi PDF di server | Guru bisa upload sendiri, tapi kerjanya jauh lebih banyak | Ditolak |
| C | App baru (Laravel/Rust) berisi 3 fitur | Lebih bersih, tapi Ngobrol, Ucapan, dan mode suara harus dipindah. Berminggu-minggu | Ditolak |

## Desain

### 0. Tanpa akun sama sekali *(keputusan user)*
- `/` langsung merender beranda. Landing `welcome` (view, `welcome.js`, partial `welcome/`, `landing/`), seluruh auth (`routes/auth.php`, `Livewire/Forms/LoginForm`, `Actions/Logout`, view `profile`, auth views/components, middleware `auth`/`verified` di route), dan akun demo (`DemoAccount*`) dihapus beserta tesnya.
- **Ngobrol tanpa user** *(call gue: cara paling hemat yang tetap menjaga analisis background)*:
  - Migrasi baru: `conversation_sessions.user_id` dibuat nullable. Tabel `users` dibiarkan tapi tidak dipakai.
  - `ConversationService::startSession/startRandomSession/startSessionForTopic` tidak lagi menerima `User`. ID sesi yang dibuat dicatat ke session Laravel (`session()->push('ngobrol.sesi', $id)`).
  - Pengecekan kepemilikan di `Chat::mount` (`Chat.php:41`) diganti `in_array($session->id, session('ngobrol.sesi', []))`. Kalau tidak ada, 404.
  - `config/session.php`: `expire_on_close => true` (lewat env `SESSION_EXPIRE_ON_CLOSE=true`), jadi riwayat hilang begitu browser ditutup.
  - Jatah pesan harian (`messagesSentToday`/`remainingMessagesToday`, `Chat.php:68,104`) diganti `RateLimiter` per IP dengan batas `dailyMessageLimit()` per 24 jam. Throttle per menit per IP juga ada di kirim pesan.
  - Model `ConversationSession` memakai trait `Prunable` (`created_at < now()->subDay()`). Turn dan api_call ikut terhapus lewat cascade/`pruning()`. `model:prune` dijadwalkan harian di `routes/console.php`.
  - Yang tetap jalan tanpa perubahan: `AnalyzeSessionJob`, `RetryFailedAnalyses`, `SessionTranscript`.
- Rate limiter lain yang memakai `$request->user()?->id ?: ip` cukup memakai IP.

### 1. Beranda (`/`)
`/dashboard` di-redirect ke `/` supaya tautan lama tidak mati *(call gue)*.
```
[logo TUTURNUSA]
<h1>Mau belajar apa hari ini?</h1>
┌──────────┐ ┌──────────┐ ┌──────────┐
│ Ngobrol  │ │ Ucapan   │ │ Buku Saku│   ← <a> besar, satu per kartu
│ 1 kalimat│ │ 1 kalimat│ │ 1 kalimat│
└──────────┘ └──────────┘ └──────────┘
footer: Bantuan & aksesibilitas (tautan kecil)
```
- Tiap kartu berupa `<a>` dalam `<ul>`. Judul kartu `<h2>`, deskripsinya satu kalimat. Screen reader membacanya "daftar, 3 item".
- Fokus memakai garis biru tebal sesuai sketsa (`focus-visible`), ditambah area klik yang besar.
- Di HP kartunya ditumpuk vertikal.

### 2. Layout `resources/views/layouts/study.blade.php`
- Hapus `$navGroups`, sidebar, bottom bar, dan panel "Lainnya".
- Header berisi logo kecil (tautan ke beranda) dan toggle Mode suara. Tidak ada tombol Keluar.
- Tiap halaman fitur punya tautan "← Beranda" di paling atas `#konten`.
- `$voiceRoutes` dikurangi menjadi `['dashboard', 'conversation', 'pronunciation-check', 'buku-saku']`. Pemetaan perintah di `resources/js/voice/adapters.js` dan `engine.js` untuk fitur yang dihapus ikut dibuang. Server-side command parsing di `VoiceCommandController` beserta servisnya juga dirampingkan.
- Isi Bantuan yang esensial (daftar perintah suara + pernyataan aksesibilitas) dipindah ke halaman statis kecil `/bantuan` yang ditautkan dari footer, bukan jadi kartu *(call gue)*.

### 3. Buku Saku (fitur baru)
**Konten:** dua buku dari PDF di root repo:
- *Buku Saku Mandiri — Panduan Aktivitas Sehari-Hari Tunanetra* (prinsip dasar, 4 bagian, 15 bab)
- *Buku Saku Senam Dasar — Panduan Olahraga Mandiri Tunanetra* (persiapan, 4 bagian, 12 gerakan, daftar referensi)

**Struktur file:** `resources/buku-saku/<slug-buku>/<NN>-<slug-bab>.md`, dengan front-matter `judul`, `bagian`, `urutan`. Pengantar ("Cara Menggunakan", "Prinsip Dasar"/"Persiapan") jadi `00-pengantar.md`. Tabel prinsip/persiapan diubah jadi daftar `### Judul` + paragraf, karena tabel dua kolom lebih susah dibacakan. Daftar referensi jadi `99-referensi.md`.

**Konversi PDF → Markdown:** dijalankan sekali oleh developer lewat `scripts/buku_saku/pdf_to_md.py` (pdftotext + parsing heading `Bagian N:`, `Bab N.`/`Gerakan N.`, `Alat dan bahan:`/`Posisi dan alat:`, langkah bernomor, `Patokan ...`). Footer "Page x of y" dibuang. Heading yang terpotong pindah halaman (contoh: Bab 4, 13, Gerakan 1, 8, 10) harus tetap kebaca, dan **15 + 12 bab wajib ada**. Hasilnya direview manual lalu di-commit. Python **tidak** jalan saat runtime.

**Audio:** `scripts/buku_saku/tts.py` memakai `edge-tts`, suara `id-ID-GadisNeural`, rate sedikit lebih pelan (mis. `-10%`) *(call gue; bisa diganti `id-ID-ArdiNeural`)*. Satu MP3 per bab ke `public/audio/buku-saku/<slug-buku>/<NN>.mp3`. Teks yang dibacakan diambil dari Markdown tanpa sintaks, dan langkah bernomor dibaca "Langkah satu, ...". Bab yang MP3-nya lebih baru dari .md-nya di-skip. `scripts/buku_saku/requirements.txt` berisi `edge-tts`. Ada komentar `ponytail:` bahwa edge-tts bukan API resmi, dengan Gemini TTS (`GEMINI_API_KEY`) sebagai cadangan. MP3 di-commit *(call gue; ukurannya kira-kira beberapa MB)*.

**Halaman:**
- `/buku-saku`: daftar dua buku (`<h1>`, tiap buku berupa `<a>`).
- `/buku-saku/{buku}`: daftar isi yang dikelompokkan per `<h2>` Bagian, tiap bab berupa tautan.
- `/buku-saku/{buku}/{bab}`: `<h1>` judul bab, lalu `<audio controls preload="none">` (tanpa autoplay), lalu teks. "Alat dan bahan" pakai `<h2>`, langkah pakai `<ol>`, patokan pakai `<h2>` + paragraf. Di bawahnya ada tautan "Bab sebelumnya / Bab berikutnya / Daftar isi".
- Implementasi: satu Livewire/controller `BukuSaku` yang membaca file pakai `Str::markdown()` (CommonMark sudah ada di Laravel) dan front-matter yang di-parse sederhana. Tidak pakai DB dan tidak menambah dependency PHP. Slug yang tidak dikenal menghasilkan 404, dan path divalidasi dari daftar file supaya tidak ada traversal.
- Route tanpa middleware auth (tamu otomatis sudah login, lihat §0). Middleware `onboarded` dihapus, lihat §4.

### 4. Yang dihapus total
Route, Livewire, view, servis khusus, JS adapter, dan tes untuk: **Hari Ini/Dashboard lama, Kartu (Deck), Kamus, Ujian (Picker/Runner), Latihan Isian, Game (Hub/ListenPick/ErrorHunter), Progress, Kata bicara, Raba lalu dengar (ScanBenda + ScanBendaController + `app/Services/Scan/*` + `config/scan.php` + rate limiter `scan-benda`), Onboarding + `EnsureOnboarded`**. Onboarding cuma mengisi `start_date`/`track` untuk kurikulum harian (dipakai Dashboard, Ujian, `ProgressStats`, `Scheduler`).
- Pekerjaan yang belum di-commit, `OpenAiVisionRecognizer`, `ScanVisionPrompt`, dan binding `ObjectRecognizer` di `AppServiceProvider`, ikut dibuang. `OpenAiCompatibleClient`, `OpenAiStreamParser`, dan pemilihan `AI_PROVIDER` **dipertahankan** (dipakai Ngobrol) dan di-commit terpisah sebelum refactor.
- Model/servis yang hanya dipakai fitur terhapus ikut dihapus (contoh: `Scheduler`, `ProgressStats`, `KataBicaraCatalog`). Sebelum menghapus, cek dulu pemakaiannya dengan grep. Kalau Ngobrol/Ucapan masih memakainya, jangan dihapus.
- **Tabel DB dan migrasi tidak dihapus** *(call gue)*. Menghapus migrasi lama itu berisiko ke data produksi, dan tabelnya diam saja tidak mengganggu. Seeder untuk kurikulum yang tidak dipakai boleh dibuang dari `DatabaseSeeder`.
- README bagian Fitur diperbarui jadi 3 fitur.

### 5. Desain Figma (sebelum kode)
Di file Figma `tuturnusa`, buat frame **Beranda (desktop 1440 + mobile 390)**, **Buku Saku – daftar isi**, dan **Buku Saku – halaman bab**, memakai token warna dan font yang ada (navy `#16294d`-ish, aksen oranye, Plus Jakarta Sans; ambil nilai pastinya dari `tailwind.config.js`). Prasyaratnya, user membuka plugin **Figma Desktop Bridge** (Plugins → Development → Figma Desktop Bridge), karena saat ini belum tersambung. Screenshot ditunjukkan ke user untuk disetujui sebelum coding layout.

## Sisa keputusan untuk builder
- Teks deskripsi satu kalimat tiap kartu (pakai bahasa yang ramah anak).
- Pilih Livewire atau controller biasa untuk Buku Saku (cukup controller + Blade karena statis).
- Detail parsing PDF. Hasilnya tetap harus direview manual.

## Verifikasi
1. `php artisan test`: semua tes tersisa hijau. Tes untuk fitur terhapus ikut dihapus.
2. Tes baru:
   - `TanpaAkunTest`: `/` status 200 tanpa login dan tidak membuat baris `users`. `/login`, `/register`, `/profile` menghasilkan 404.
   - Ngobrol: mulai sesi tanpa akun berhasil. Membuka `/conversation/{id}` milik session lain menghasilkan 404. Melewati batas harian per IP ditolak dengan pesan yang ramah. Sesi berumur lebih dari 24 jam terhapus oleh `model:prune`. `ConversationTest` dan `DailyMessageLimitTest` yang lama disesuaikan.
   - `BerandaTest`: `/` menampilkan tepat 3 tautan kartu dan tidak ada `nav` sidebar.
   - `BukuSakuTest`: daftar buku tampil, kedua buku punya 15 dan 12 bab, halaman bab punya `<h1>`, `<ol>`, dan `<audio>` tanpa `autoplay`. Slug tidak dikenal menghasilkan 404, `../` menghasilkan 404.
   - Route yang dihapus (`/kamus`, `/game`, `/scan-benda`, ...) menghasilkan 404.
3. `python scripts/buku_saku/tts.py`, lalu dengarkan satu MP3 tiap buku.
4. `npm run build` jalan tanpa error, dan ukuran bundle JS turun.
5. Manual: buka di Chrome dengan VoiceOver/NVDA. Tab dari atas harus berurutan skip link → mode suara → logo → 3 kartu, nama kartunya dibacakan, dan navigasi heading (H) di halaman bab bekerja. Tes Mode suara dengan "buka buku saku".
