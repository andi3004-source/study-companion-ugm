# Tuturnusa

Aplikasi web belajar untuk tunanetra: tiga fitur tanpa akun (Ngobrol, Ucapan,
Buku Saku). Dibangun dengan Laravel 12, Livewire 3, Tailwind, dan SQLite.

## Fitur

- **Ngobrol**: percakapan dengan AI (Gemini/Langflow).
- **Ucapan**: cek pelafaran kata (layanan pengucapan + STT cadangan).
- **Buku Saku**: panduan kegiatan mandiri dan senam (Markdown + narasi MP3).
- **PWA**: pasang dari beranda; Buku Saku dapat dipakai offline setelah dibuka.



## Ngobrol

Percakapan dengan AI lewat `/conversation` (tanpa akun). Terbatas `DAILY_MESSAGE_LIMIT` pesan per hari per sesi.

## Ucapan

Cek pelafaran di `/pronunciation-check` (layanan ML atau cadangan STT).

## Buku Saku

Bacaan dan audio narasi di `/buku-saku` (Markdown + MP3 di repo). Bisa dipasang sebagai PWA dan dipakai offline untuk bab yang pernah dibuka.

## PWA

`public/manifest.json`, service worker, dan tombol pasang di beranda (hanya saat browser menawarkan). Detail di Bantuan bagian memasang aplikasi.

## Menjalankan secara lokal

Butuh PHP 8.2+, Composer, Node.js, dan ekstensi `pdo_sqlite`.

```bash
composer install
cp .env.example .env           # lalu isi variabel yang diperlukan (lihat di bawah)
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install
composer run dev               # server, queue worker, log, dan Vite sekaligus
```

`composer run setup` menjalankan sebagian besar langkah di atas sekaligus.
Terjemahan arti lewat AI tidak berjalan otomatis. Jalankan
`php artisan translate:meanings` dengan `queue:work` aktif.

## Menjalankan tes

```bash
php artisan test
bash scripts/ci.sh   # build Vite, PHPUnit, Pint pada perubahan
```

Tes memakai SQLite in-memory (lihat `phpunit.xml`), jadi database lokal tidak tersentuh.

## Variabel `.env`

Lihat `.env.example` untuk keterangan dan nilai bawaan. Variabel utama:

| Variabel | Keterangan |
| --- | --- |
| `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL` | Pengaturan dasar Laravel |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE` | Bahasa aplikasi |
| `DB_CONNECTION` | `sqlite` |
| `SESSION_DRIVER`, `QUEUE_CONNECTION`, `CACHE_STORE` | Driver sesi, antrean, dan cache |
| `MAIL_*` | Pengiriman email sistem (opsional) |
| `GEMINI_API_KEY`, `GEMINI_MODEL` | Provider AI bawaan: percakapan dan `translate:meanings` |
| `AI_PROVIDER` | `gemini` (bawaan), `anthropic`, atau `openai_compatible` |
| `OPENAI_COMPATIBLE_API_KEY`, `OPENAI_COMPATIBLE_MODEL`, `OPENAI_COMPATIBLE_BASE_URL` | Endpoint OpenAI-compatible (`/chat/completions`) bila `AI_PROVIDER=openai_compatible`: router lokal (9router, LiteLLM, Ollama, llama.cpp, vLLM) atau OpenRouter. Nama model harus sama dengan yang disajikan endpoint (`curl $OPENAI_COMPATIBLE_BASE_URL/models`) |
| `DAILY_MESSAGE_LIMIT` | Pesan Ngobrol per pengguna per hari (bawaan 50; bilangan bulat 1-1000, nilai lain kembali ke 50) |
| `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL` | Provider AI alternatif (tidak aktif) |
| `PRONUNCIATION_SERVICE_URL`, `PRONUNCIATION_CHECKER_DRIVER` | Layanan cek pelafalan (`ml` atau `stt_match`) |
| `HSK_CORRECTIONS_PATH` | Opsional: path file koreksi HSK2-3 |
| `HSK_LEVEL_LOCK` | `true`/`false`: kunci percakapan Mandarin ke kosakata HSK 1 |

Jangan commit `.env`.

### Provider AI lewat router lokal

Bila `AI_PROVIDER=openai_compatible`, Ngobrol, analisis kesalahan (jalur
cadangan Gemini), dan `translate:meanings` semuanya lewat endpoint di
`OPENAI_COMPATIBLE_BASE_URL` dengan key `OPENAI_COMPATIBLE_API_KEY` — tanpa
perubahan kode, karena ketiganya memakai kontrak `AiClient` yang sama.
Contoh untuk 9router di mesin yang sama:

```
AI_PROVIDER=openai_compatible
OPENAI_COMPATIBLE_API_KEY=<key 9router>
OPENAI_COMPATIBLE_MODEL=<nama model di 9router>
OPENAI_COMPATIBLE_BASE_URL=http://127.0.0.1:20128/v1
```

## Deploy ke Laravel Cloud

Target: MySQL terkelola, antrean `database`, HTTPS di belakang proxy. Belum
pernah di-deploy sungguhan; ikuti daftar uji di akhir bagian ini.

**Perintah build:**

```
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

**Perintah deploy** (setiap rilis):

```
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**Pekerja antrean** (analisis kesalahan sesi, terjemahan arti):

```
php artisan queue:work --tries=3
```

**Perintah sekali jalan** (setelah migrasi pertama):

```
php artisan app:seed-content
```

`app:seed-content` membutuhkan berkas besar yang tidak di-commit (lihat
"Mengisi konten"). Tanpa berkas itu, perintah melewati seeder terkait dan
keluar dengan status gagal.

**Variabel lingkungan** (nama saja; nilai diisi di dasbor Laravel Cloud, jangan
di-commit):

- Aplikasi: `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`,
  `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `TRUSTED_PROXIES`, `APP_FORCE_HTTPS`
- Basis data (biasanya diisi otomatis oleh Laravel Cloud): `DB_CONNECTION`,
  `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- Antrean, cache, sesi, log: `QUEUE_CONNECTION`, `CACHE_STORE`,
  `SESSION_DRIVER`, `SESSION_SECURE_COOKIE`, `LOG_CHANNEL`, `LOG_LEVEL`
- Email: `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`,
  `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`
- AI dan layanan: `GEMINI_API_KEY`, `GEMINI_MODEL`, `ANTHROPIC_API_KEY`,
  `ANTHROPIC_MODEL`, `LANGFLOW_URL`, `LANGFLOW_FLOW_ID`, `LANGFLOW_API_KEY`,
  `LANGFLOW_TIMEOUT`, `PRONUNCIATION_SERVICE_URL`,
  `PRONUNCIATION_CHECKER_DRIVER`, `DAILY_MESSAGE_LIMIT`
- Lainnya: `HSK_LEVEL_LOCK`, `HSK_CORRECTIONS_PATH`, `PRONUNCIATION_SERVICE_URL`

Wajib di produksi: `APP_KEY`, `APP_ENV`, `APP_URL` (dengan `https://`), `APP_DEBUG`
(harus `false`), kredensial DB, `QUEUE_CONNECTION` (`database`), dan kunci AI
untuk fitur yang dipakai. Tanpa `GEMINI_API_KEY`, Ngobrol tidak bisa membalas.
Analisis kesalahan memakai Langflow lalu Gemini sebagai cadangan.

**Catatan:**

- **Basis data:** migrasi sudah disesuaikan untuk MySQL 8.0.13+ (indeks
  fungsional `LOWER(word)`, `meaning_en` dan `meaning_id` HSK bertipe `text`).
  Pencarian Kamus memakai `JSON_UNQUOTE`/`CONCAT` di MySQL dan sintaks SQLite
  di lokal/tes. Tes otomatis hanya berjalan di SQLite. Perbedaan yang tersisa
  ada di "Keterbatasan data".
- **HTTPS di belakang proxy:** isi `TRUSTED_PROXIES` dengan `*` dan aktifkan
  `APP_FORCE_HTTPS` supaya aplikasi memercayai header `X-Forwarded-*` dari
  proxy Laravel Cloud dan selalu membuat tautan `https://` (lihat daftar uji).
**Peringatan biaya dan kuota:**

- Pasang **batas pengeluaran** (spending limit) di Laravel Cloud dan di
  penyedia AI (Gemini, Anthropic, Langflow). Aplikasi membatasi 50 pesan
  Ngobrol per pengguna per hari (bisa diubah lewat `DAILY_MESSAGE_LIMIT`),
  tetapi tidak ada batas global; banyak pengguna berarti banyak panggilan API.
- `php artisan translate:meanings` menghabiskan kuota AI; jangan masukkan ke
  perintah deploy.
- Pekerja antrean yang terus menyala dan basis data terkelola ditagih walaupun
  tidak ada pengguna. Matikan lingkungan staging bila tidak dipakai.

**Daftar uji setelah deploy:**

1. `GET /up` mengembalikan 200.
2. Buka beranda dan ketiga fitur utama lewat HTTPS.
3. Selesaikan satu sesi Ngobrol; analisis muncul setelah pekerja antrean memprosesnya.

## Deploy ke satu server

`docker-compose.yml` menjalankan tiga layanan di satu server: `app` (FrankenPHP,
web), `worker` (`php artisan queue:work --tries=3 --sleep=3`, image yang sama),
dan `proxy` (Caddy, HTTPS otomatis untuk `DOMAIN`). SQLite dan `storage`
disimpan di satu volume Docker (`data`, di `/data`) yang dipasang `app` dan
`worker`. Belum pernah dijalankan sungguhan; ikuti daftar uji di bawah.
Pengembangan lokal memakai `docker-compose.dev.yml`.

Saat `app` mulai, entrypoint (`docker/entrypoint.sh`) membuat berkas SQLite
bila belum ada, menjalankan `php artisan migrate --force`, menjalankan
`php artisan app:seed-content` hanya bila tabel konten masih kosong, lalu
`config:cache`, `route:cache`, dan `view:cache`. `worker` menunggu sampai `app`
selesai migrasi, supaya hanya satu container yang memigrasi berkas SQLite.

**Prasyarat:**

- Server Linux dengan Docker dan plugin Docker Compose.
- Nama domain yang record DNS-nya (A/AAAA) menunjuk ke server ini.
- Port 80 dan 443 terbuka (Caddy memakainya untuk sertifikat Let's Encrypt).

**Pemasangan pertama:**

```
git clone <url-repo> study-companion
cd study-companion
cp .env.production.example .env
nano .env                # isi DOMAIN, APP_URL, APP_KEY, kunci AI, dll.
echo "base64:$(openssl rand -base64 32)"   # nilai untuk APP_KEY
docker compose up -d --build
docker compose logs -f app   # tunggu "mulai: frankenphp run"
```

Tiga berkas data besar tidak ada di git (lihat "Mengisi konten"). Salin ke
`storage/app/seed-data/` **sebelum** `docker compose up -d --build` supaya ikut
masuk image. Bila tertinggal, salin lalu jalankan seeding sekali jalan:

```
docker compose cp mandarin_hsk1to6_complete.json app:/data/storage/app/seed-data/
docker compose cp irregular_verbs_183_complete.json app:/data/storage/app/seed-data/
docker compose cp english_vocab_cefr_10674.json app:/data/storage/app/seed-data/
docker compose exec app php artisan app:seed-content
```

**Pembaruan:**

```
git pull
docker compose up -d --build
```

Migrasi baru dijalankan otomatis oleh entrypoint. Data di volume `data` tetap.

**Pencadangan SQLite** (aman saat aplikasi berjalan; `VACUUM INTO` membuat
salinan utuh, termasuk isi berkas WAL):

```
docker compose exec app php -r '(new PDO("sqlite:/data/database/database.sqlite"))->exec("VACUUM INTO \"/data/database/backup.sqlite\"");'
docker compose cp app:/data/database/backup.sqlite ./backup-$(date +%Y%m%d).sqlite
docker compose exec app rm /data/database/backup.sqlite
```

Simpan salinan di luar server. Untuk memulihkan: hentikan `app` dan `worker`,
salin berkas cadangan ke `/data/database/database.sqlite`, lalu nyalakan lagi.

**Variabel lingkungan** (nama saja; isi di `.env` di server, contoh di
`.env.production.example`):

- Proxy dan domain: `DOMAIN`, `TRUSTED_PROXIES`, `APP_FORCE_HTTPS`
- Aplikasi: `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`,
  `APP_LOCALE`, `APP_FALLBACK_LOCALE`
- Basis data: `DB_JOURNAL_MODE`, `DB_BUSY_TIMEOUT` (`DB_CONNECTION` dan
  `DB_DATABASE` ditetapkan oleh `docker-compose.yml`)
- Log, sesi, antrean, cache: `LOG_CHANNEL`, `LOG_LEVEL`, `SESSION_DRIVER`,
  `SESSION_LIFETIME`, `SESSION_SECURE_COOKIE`, `QUEUE_CONNECTION`,
  `CACHE_STORE`, `FILESYSTEM_DISK`
- Email: `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`,
  `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`
- AI: `GEMINI_API_KEY`, `GEMINI_MODEL`, `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL`,
  `LANGFLOW_URL`, `LANGFLOW_FLOW_ID`, `LANGFLOW_API_KEY`, `LANGFLOW_TIMEOUT`,
  `DAILY_MESSAGE_LIMIT`
- Lainnya: `PRONUNCIATION_CHECKER_DRIVER`, `HSK_LEVEL_LOCK`, `REGISTRATION_INVITE_CODE`

Layanan cek pelafalan ML (`pronunciation-service`) tidak ikut; contoh `.env`
memakai `PRONUNCIATION_CHECKER_DRIVER=stt_match`.

**Peringatan:**

- **Batas pengeluaran API:** pasang batas pengeluaran (spending limit) di
  Gemini, Anthropic, dan Langflow sebelum mengisi kuncinya. Aplikasi membatasi
  50 pesan Ngobrol per pengguna per hari (`DAILY_MESSAGE_LIMIT`), tetapi tidak
  ada batas global.
- `.env` berisi rahasia: jangan di-commit dan jangan disalin ke image
  (`.dockerignore` sudah mengecualikannya).

**Daftar uji setelah deploy:**

1. `docker compose ps`: `app` berstatus healthy, `worker` dan `proxy` running.
2. `https://<DOMAIN>/up` mengembalikan 200 dengan sertifikat yang sah.
3. Buka beranda lewat HTTPS.
4. Selesaikan satu sesi Ngobrol; analisis muncul (berarti `worker` jalan).
5. `docker compose restart`: data di volume tetap ada.





## Keterbatasan data

Tes otomatis berjalan di SQLite. Di MySQL (Laravel Cloud) perilakunya sama,
kecuali hal-hal berikut. Semuanya diperiksa lewat audit kode dan data, belum
lewat MySQL sungguhan.

**Kunci unik `(hanzi, pinyin)` di `mandarin_hsk_words`.** Collation MySQL
`utf8mb4_unicode_ci` tidak membedakan nada maupun huruf besar-kecil, jadi bagi
MySQL `hǎo` sama dengan `hào`, dan `Dū` sama dengan `dū`.

- Data sekarang aman: ke-4.991 kunci di `mandarin_hsk1to6_complete.json` tetap
  unik walaupun nada dan huruf besar-kecil diabaikan.
- Ke-63 koreksi yang hanya mengubah nada atau huruf besar-kecil (misalnya 个
  gě → gè) diterapkan pada baris yang sama, tidak menambah baris baru.
- Kelak, bila data memuat dua bacaan untuk hanzi yang sama yang hanya berbeda
  nada (misalnya 好 hǎo dan hào), upsert seeder akan **menimpa salah satunya
  tanpa pesan galat**. Sebelum itu terjadi, kolom `pinyin` perlu memakai
  collation `utf8mb4_bin` di MySQL. Ini belum dikerjakan.

**Perbedaan kecil SQLite dan MySQL:**

- **Urutan Kamus:** hasil diurutkan menurut `headword`. SQLite mengurutkan per
  kode karakter, MySQL memakai aturan collation Unicode, sehingga urutan hanzi
  dan huruf besar-kecil bisa berbeda. Isi hasilnya sama.
- **Aksen di pencarian:** `LIKE` di MySQL mengabaikan aksen ("cafe" juga cocok
  dengan "café"), jadi pencarian arti bisa memberi sedikit lebih banyak hasil.
- **Hanzi di luar BMP:** `utf8mb4_unicode_ci` menganggap semua karakter di luar
  Basic Multilingual Plane (misalnya CJK Extension B) sama. Kosakata HSK
  seluruhnya ada di BMP, jadi saat ini tidak berpengaruh.
- **Indeks `LOWER(word)`** pada `english_vocab`: indeks fungsional di MySQL
  8.0.13 ke atas; MariaDB tidak mendukungnya, jadi di sana dipakai indeks biasa
  pada `word` (collation-nya sudah tidak membedakan huruf besar-kecil).




## Mode suara

Lapisan suara bersama (`resources/js/voice/engine.js` dan
`resources/js/voice/adapters.js`) memakai Web Speech API bawaan browser.
**Pelengkap, bukan pengganti:** markup semantik, keyboard, dan pembaca layar
tetap jalur utama. Mati secara bawaan.

- **Kontrol:** tombol "Mode suara" (`aria-pressed`) pertama setelah tautan
  lewati; tombol "Berhenti" saat mode aktif. Tidak ada pintasan tombol tunggal.
  **Satu ujaran per tekan:** nyalakan mode, lalu tekan lagi untuk membuka
  mikrofon; setelah satu perintah diproses, mikrofon tertutup hingga tekan
  berikutnya.
- **Alur:** narasi/audio aplikasi dan TTS dihentikan, bunyi pendek, lalu
  mikrofon (`SpeechRecognition.continuous=false`, id-ID, 3 alternatif).
  Alternatif dikirim ke `POST /voice/command` dan diurai lewat
  `App\Support\VoiceAlternatives`. Status diumumkan lewat `role="status"`.
- **Halaman:** beranda, ngobrol, ucapan, buku saku, bantuan. Adapter untuk
  chat, pemilih skenario Ngobrol, Ucapan, dan Bantuan (`data-voice-page`).
- **Data:** tidak menyimpan audio atau transkrip. Hanya tanda persetujuan di
  localStorage dan status aktif di sessionStorage.

Hanya **Chrome atau Edge**, **butuh internet**. Suaramu diproses oleh
penyedia pengenalan suara browser. Akurasi bervariasi; suara Mandarin harus terpasang di perangkat. Belum diuji dengan pengguna tunanetra. Pembaca layar tetap jalur utama.

