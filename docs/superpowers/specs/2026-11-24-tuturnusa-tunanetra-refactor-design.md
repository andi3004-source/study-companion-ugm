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
  - Model `ConversationSession` memakai trait `Prunable` (`started_at < now()->subDay()`; tabel ini tidak punya `created_at`). Turn dan api_call ikut terhapus lewat cascade/`pruning()`. `model:prune` dijadwalkan **tiap jam** di `routes/console.php`.
  - Pemeriksaan kepemilikan sesi ada di satu tempat (mis. method `sesiMilikBrowserIni()`), dipanggil di `mount` **dan** di setiap aksi Livewire (kirim pesan, selesai, poll analisis), bukan cuma di `mount`.
  - Pembacaan otomatis balasan AI (`autoPlay`) **default mati**. Suara hanya diputar setelah anak menekan tombol, supaya tidak bertabrakan dengan pembaca layar.
  - Sebelum mikrofon pertama kali aktif di Ngobrol/Ucapan, tampilkan pemberitahuan privasi singkat: apa yang dikirim (suara diproses browser/penyedia STT, teks dikirim ke AI) dan bahwa data dihapus dalam 24 jam. Ketentuan persetujuan wali dan tinjauan hukum UU PDP menjadi tanggung jawab pemilik produk, di luar cakupan kode.
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
- Mode suara menjadi **push-to-talk**: satu ujaran per tekan tombol, status diumumkan lewat `role="status"`, tidak ada hotkey huruf tunggal, mikrofon tidak terbuka lagi otomatis setelah respons, dan semua audio aplikasi berhenti sebelum mendengarkan (temuan R1 §4).
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

## Masukan riset (24 Nov 2026)
Riset ada di `docs/research/`. Yang diadopsi:
- R1: prune `started_at` per jam, cek kepemilikan sesi di setiap aksi, `autoPlay` default mati, Mode suara push-to-talk, pemberitahuan privasi, metadata durasi MP3 dibuat saat build.
- R3: `aria-label` pada `<audio>` ("Audio narasi bab: …"), satu kalimat yang menjelaskan dua pilihan (dengar narasi atau pakai pembaca layar sendiri), wadah `aria-live="polite"` yang tidak di-remount di Ngobrol, rate edge-tts −10%, review manual 2–3 bab sebelum MP3 di-commit, `@axe-core/playwright` di CI, dan `/bantuan` berisi pintasan NVDA/TalkBack.
- R2 (kontras sudah dihitung ulang oleh Claude): teks/garis utama navy `#14294D` (14.44:1 di putih). Oranye `#F9B934` **hanya** untuk latar dengan teks navy (8.26:1). Teks oranye di latar terang dilarang (1.75:1). Fokus global `:focus-visible` memakai cincin dua nada: 3px biru `#1D4ED8` (6.7:1 di putih) dengan offset 3px dan cincin luar putih, supaya tetap terlihat di latar navy (biru di navy hanya 2.16:1). Target sentuh minimal 56×56px. Kartu dan tombol memakai border 2px nyata supaya tetap terlihat di `forced-colors`. Reflow 320px/zoom 400% tanpa scroll horizontal. Teks bab `max-w-2xl`, rata kiri. Kalimat UI maksimal 10–15 kata, tanpa kiasan visual. Pola kartu ala GOV.UK: `<ul>` → `<li>` → satu `<a>` berisi `<h2>` + `<p>`, tanpa kontrol bersarang.
- Aset dari Figma (`tuturnusa`, "ASET KOMPONEN"), keputusan user: font **Inter Variable** self-host (`@fontsource-variable/inter`) menggantikan Plus Jakarta Sans. Isi teks bobot 400, **judul minimal 600** (bukan 460 seperti di aset, demi pengguna low vision). Ikon gaya outline sesuai aset; file `icons.js` aslinya tidak ditemukan, jadi dipakai **Lucide** (MIT) yang identik gayanya, hanya ikon yang dibutuhkan, sebagai sprite SVG inline (`<svg class="o-ic" aria-hidden="true"><use href="#i-NAME"/></svg>`). Ikon selalu dekoratif; makna tetap dari teks.
- Figma: file bisa dibaca lewat REST, tapi menulis mockup butuh Figma Desktop + plugin Desktop Bridge. Sampai itu tersambung, mockup dibuat di kode dan diverifikasi dengan screenshot Playwright (desktop 1440, mobile 390, reflow 320).
- Latar halaman **putih `#FFFFFF`**, bukan cream (keputusan user setelah melihat mockup). Token `cream` di Tailwind diganti/dialihkan ke putih untuk latar halaman.
- **Jadi aplikasi: PWA** (keputusan user, 24 Nov 2026: "saat buka web app ada bagian untuk unduh jadi app, otomatis jadi app, untuk pengajar dan siswa"). Web app manifest + service worker. Di beranda, **di bawah tiga kartu** (bukan kartu keempat), ada section "Pasang Tuturnusa di perangkat ini" berisi tombol **Pasang aplikasi** yang memanggil `beforeinstallprompt` (Chrome/Edge di Android, ChromeOS, Windows, macOS). Di browser tanpa event itu (Safari iOS/macOS, Firefox), tombol diganti tautan "Cara memasang" ke `/bantuan#pasang` berisi langkah yang bisa dibacakan pembaca layar (iOS: Bagikan → Tambahkan ke Layar Utama). Section disembunyikan bila sudah berjalan sebagai app (`display-mode: standalone`). Service worker: cache halaman Buku Saku + MP3 + aset agar Buku Saku bisa dipakai offline. Ngobrol/Ucapan butuh internet dan menampilkan pesan yang jelas saat offline. Halaman yang tidak boleh di-cache (Ngobrol per sesi, endpoint POST) dikecualikan. Distribusi toko (TWA untuk Play Store) bisa ditambahkan nanti di atas PWA yang sama; Tauri/Capacitor tidak dipakai sekarang.

## Hasil QA desain Bab 1 (QA-D1, 24 Nov 2026)
Reviewer: Claude, Codex, Cursor (`docs/research/qa-desain-bab1-*.md`). Antigravity menyusul. Diadopsi:
- **Jalur app:** ketiganya sepakat PWA dulu, TWA nanti bila perlu Play Store; Capacitor/Tauri ditunda (Tauri punya masalah fokus NVDA, tauri-apps/tauri#15624). Service worker harus melayani MP3 dari cache dengan dukungan **Range request (206)**, karena `<audio>` meminta potongan file.
- **Layout bab desktop:** kolom baca **di tengah, 800px, teks isi 22px** (line-height 1.6, ±70 karakter/baris), h1 48px, h2 30px. Tidak ada kolom samping.
- **h1 memuat nomor:** "Bab 1. Cara Menyikat Gigi Mandiri"; meta "Bab 1 dari 15 · Bagian 1: …" diletakkan **setelah** h1.
- **Audio:** `<section aria-labelledby>` dengan h2 "Audio narasi" + durasi; `<audio preload="none">` tanpa `controls`, dikendalikan `<button>` asli ≥56px: "Putar narasi"/"Jeda narasi" (`aria-pressed`), "Mundur 10 detik", "Maju 10 detik"; posisi waktu sebagai teks biasa; hanya putar/jeda/selesai diumumkan lewat satu `role="status"`. Tanpa autoplay.
- **Tombol Mode suara:** nama tetap "Mode suara", status lewat `aria-pressed` (teks "mati/nyala" visual).
- **Tautan bawah** bernama "Kembali ke daftar isi" (beda dengan "Daftar isi" di atas).
- **Istilah (keputusan user):** judul bagian "Patokan indra" (Buku Saku Mandiri) menjadi **"Cara tahu sudah benar"**; "Patokan rasa dan napas" (Senam Dasar) menjadi **"Yang kamu rasakan"**. Isi tetap dari buku. Catatan: di PDF Senam Dasar hanya 11 dari 12 gerakan memuat "Patokan rasa dan napas" — W1-G wajib memeriksa gerakan yang tidak punya.
- Teks penjelasan minimal 18px.
- **Semua permukaan putih** (keputusan user): tidak ada latar pastel/berwarna di layar maupun kotak catatan; blok dibedakan dengan border navy 2px, oranye hanya aksen.

## Warna konsisten di semua perangkat (keputusan user, 24 Nov 2026)
- Satu palet terang, hex sRGB saja (tidak ada P3/oklch, tidak ada `prefers-color-scheme`, `darkMode` Tailwind tidak dipakai).
- `<meta name="color-scheme" content="only light">` + `:root { color-scheme: only light; }` agar browser tidak menggelapkan otomatis (Chrome Android auto-dark, mode gelap Samsung Internet, WebView TWA). Mode gelap Samsung Internet diverifikasi di perangkat saat QA.
- `<meta name="theme-color" content="#14294D">` tanpa variasi media; manifest PWA `theme_color #14294D`, `background_color #FFFFFF`.
- Kontrol bawaan memakai `accent-color: #14294D`.
- Font Inter self-host (tidak bergantung font sistem).
- **Setelan "kontras tinggi" bawaan aplikasi DIHAPUS** (keputusan user): kelas `kontras-tinggi`, aturan CSS-nya, dan opsinya di `displaySettings`/Bantuan. Setelan **ukuran teks** (normal/besar/sangat besar) tetap ada karena tidak mengubah warna.
- Pengecualian yang sengaja TIDAK dikunci karena merupakan alat bantu pilihan pengguna di tingkat OS: tema kontras Windows (`forced-colors`, tetap didukung dengan border nyata dan fokus `Highlight`) serta Smart Invert iOS / inversi warna Android. Web tidak dapat dan tidak boleh memblokirnya (WCAG).
