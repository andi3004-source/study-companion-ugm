# Web Belajar Bahasa

Aplikasi belajar mandiri Mandarin (HSK) dan Inggris (IELTS) dengan kurikulum
harian. Dibangun dengan Laravel 12, Livewire 3 (Volt), Tailwind, dan SQLite.

## Fitur

- **Dashboard harian**: checklist per hari dari kurikulum HSK1 (150 kata dalam batch) dan topik IELTS.
- **Kartu**: flashcard Mandarin (HSK1-6), irregular verbs, dan kosakata IELTS, dengan tanda "sudah hafal".
- **Kamus**: pencarian Mandarin (hanzi, pinyin tanpa nada, arti) dan Inggris (CEFR + irregular verbs).
- **Latihan Isian**: isi pinyin, hanzi, atau arti; pinyin dinilai tanpa memperhatikan nada.
- **Ujian**: kuis yang dibuat dari kosakata.
- **Ngobrol (Conversation Partner)**: percakapan dengan AI (Gemini).
- **Cek Pelafalan**: lewat microservice `pronunciation-service/`, atau STT lokal sebagai cadangan.
- **Progres**: ringkasan kemajuan belajar.

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
| `MAIL_*` | Pengiriman email (verifikasi, reset password) |
| `GEMINI_API_KEY`, `GEMINI_MODEL` | Provider AI aktif: percakapan dan `translate:meanings` |
| `GEMINI_RETRY_MAX`, `GEMINI_RETRY_DELAYS`, `GEMINI_RETRY_JITTER_MS`, `GEMINI_RETRY_BUDGET_CHAT`, `GEMINI_RETRY_BUDGET_SCAN`, `GEMINI_RETRY_BUDGET_ANALYSIS`, `GEMINI_RETRY_BUDGET_SECONDS`, `GEMINI_FALLBACK_MODELS` | Coba ulang saat Gemini penuh, lihat [Ketahanan terhadap beban Gemini](#ketahanan-terhadap-beban-gemini) |
| `DAILY_MESSAGE_LIMIT` | Pesan Ngobrol per pengguna per hari (bawaan 50; bilangan bulat 1-1000, nilai lain kembali ke 50) |
| `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL` | Provider AI alternatif (tidak aktif) |
| `PRONUNCIATION_SERVICE_URL`, `PRONUNCIATION_CHECKER_DRIVER` | Layanan cek pelafalan (`ml` atau `stt_match`) |
| `HSK_CORRECTIONS_PATH` | Opsional: path file koreksi HSK2-3 |
| `HSK_LEVEL_LOCK` | `true`/`false`: kunci percakapan Mandarin ke kosakata HSK 1 |

Jangan commit `.env`.

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
# hanya di staging, bila akun demo dipakai:
php artisan db:seed --class=DemoUserSeeder --force
```

`app:seed-content` membutuhkan tiga berkas besar yang tidak di-commit (lihat
"Mengisi konten"). Tanpa berkas itu, perintah melewati seeder terkait dan
keluar dengan status gagal. `DemoUserSeeder` selalu menolak bila `APP_ENV` bernilai production.

**Variabel lingkungan** (nama saja; nilai diisi di dasbor Laravel Cloud, jangan
di-commit):

- Aplikasi: `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`,
  `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `TRUSTED_PROXIES`, `APP_FORCE_HTTPS`
- Basis data (biasanya diisi otomatis oleh Laravel Cloud): `DB_CONNECTION`,
  `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- Antrean, cache, sesi, log: `QUEUE_CONNECTION`, `DB_QUEUE_RETRY_AFTER`, `CACHE_STORE`,
  `SESSION_DRIVER`, `SESSION_SECURE_COOKIE`, `LOG_CHANNEL`, `LOG_LEVEL`
- Email: `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`,
  `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`
- AI dan layanan: `GEMINI_API_KEY`, `GEMINI_MODEL`, `GEMINI_RETRY_MAX`,
  `GEMINI_RETRY_DELAYS`, `GEMINI_RETRY_JITTER_MS`, `GEMINI_RETRY_BUDGET_SECONDS`,
  `GEMINI_RETRY_BUDGET_CHAT`, `GEMINI_RETRY_BUDGET_SCAN`,
  `GEMINI_RETRY_BUDGET_ANALYSIS`,
  `GEMINI_FALLBACK_MODELS`, `ANTHROPIC_API_KEY`,
  `ANTHROPIC_MODEL`, `LANGFLOW_URL`, `LANGFLOW_FLOW_ID`, `LANGFLOW_API_KEY`,
  `LANGFLOW_TIMEOUT`, `PRONUNCIATION_SERVICE_URL`,
  `PRONUNCIATION_CHECKER_DRIVER`, `DAILY_MESSAGE_LIMIT`
- Fitur: `HSK_LEVEL_LOCK`, `HSK_CORRECTIONS_PATH`, `GAME_ITEMS_PATH`,
  `KATABICARA_PATH`, `SCAN_BENDA_ENABLED`, `SCAN_BENDA_MAX_IMAGE_BYTES`,
  `SCAN_BENDA_RATE_LIMIT_PER_MINUTE`, `SCAN_BENDA_TIMEOUT`
- Akses: `REGISTRATION_ENABLED`, `REGISTRATION_INVITE_CODE`,
  `DEMO_ACCOUNT_ENABLED`, `DEMO_ACCOUNT_EMAIL`, `DEMO_ACCOUNT_PASSWORD`

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
- **Disk lokal** di server bersifat sementara. Aplikasi tidak menyimpan unggahan
  pengguna; foto Raba lalu dengar tidak disimpan. `php artisan langflow:ping`
  menulis `storage/app/langflow-last-response.json` (diagnostik saja).
- **HTTPS di belakang proxy:** isi `TRUSTED_PROXIES` dengan `*` dan aktifkan
  `APP_FORCE_HTTPS` supaya aplikasi memercayai header `X-Forwarded-*` dari
  proxy Laravel Cloud dan selalu membuat tautan `https://` (lihat daftar uji).
- **Pendaftaran:** untuk demo publik, pertimbangkan mematikan `REGISTRATION_ENABLED`
  atau `REGISTRATION_INVITE_CODE`.

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
2. Halaman login terbuka lewat `https://`, dan tautan "Lupa kata sandi?" juga
   `https://`.
3. Kamus: cari "makan" dan "eat"; hasil Inggris menampilkan arti Indonesia
   tanpa tanda kutip.
4. Selesaikan satu sesi Ngobrol Mandarin; analisis muncul setelah pekerja
   antrean memprosesnya.
5. Jalankan ulang `php artisan app:seed-content`; jumlah baris tidak berubah.

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

Akun demo (hanya bila `APP_ENV=staging`; isi `DEMO_ACCOUNT_ENABLED` dan
`DEMO_ACCOUNT_PASSWORD` di `.env`, lalu `docker compose up -d` supaya terbaca):

```
docker compose exec app php artisan db:seed --class=DemoUserSeeder --force
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
  `SESSION_LIFETIME`, `SESSION_SECURE_COOKIE`, `QUEUE_CONNECTION`, `DB_QUEUE_RETRY_AFTER`,
  `CACHE_STORE`, `FILESYSTEM_DISK`
- Email: `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`,
  `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`
- AI: `GEMINI_API_KEY`, `GEMINI_MODEL`, `GEMINI_RETRY_MAX`, `GEMINI_RETRY_DELAYS`,
  `GEMINI_RETRY_JITTER_MS`, `GEMINI_RETRY_BUDGET_SECONDS`, `GEMINI_RETRY_BUDGET_CHAT`,
  `GEMINI_RETRY_BUDGET_SCAN`, `GEMINI_RETRY_BUDGET_ANALYSIS`, `GEMINI_FALLBACK_MODELS`,
  `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL`,
  `LANGFLOW_URL`, `LANGFLOW_FLOW_ID`, `LANGFLOW_API_KEY`, `LANGFLOW_TIMEOUT`,
  `DAILY_MESSAGE_LIMIT`
- Fitur: `PRONUNCIATION_CHECKER_DRIVER`, `HSK_LEVEL_LOCK`, `SCAN_BENDA_ENABLED`
- Akses: `REGISTRATION_ENABLED`, `REGISTRATION_INVITE_CODE`,
  `DEMO_ACCOUNT_ENABLED`, `DEMO_ACCOUNT_EMAIL`, `DEMO_ACCOUNT_PASSWORD`

Layanan cek pelafalan ML (`pronunciation-service`) tidak ikut; contoh `.env`
memakai `PRONUNCIATION_CHECKER_DRIVER=stt_match`.

**Peringatan:**

- **Batas pengeluaran API:** pasang batas pengeluaran (spending limit) di
  Gemini, Anthropic, dan Langflow sebelum mengisi kuncinya. Aplikasi membatasi
  50 pesan Ngobrol per pengguna per hari (`DAILY_MESSAGE_LIMIT`), tetapi tidak
  ada batas global.
- **Pendaftaran:** server yang bisa diakses publik sebaiknya memakai
  `REGISTRATION_ENABLED` bernilai false atau `REGISTRATION_INVITE_CODE`
  (contoh `.env` menutup pendaftaran). Tanpa itu siapa pun bisa membuat akun
  dan memakai kuota AI.
- `.env` berisi rahasia: jangan di-commit dan jangan disalin ke image
  (`.dockerignore` sudah mengecualikannya).

**Daftar uji setelah deploy:**

1. `docker compose ps`: `app` berstatus healthy, `worker` dan `proxy` running.
2. `https://<DOMAIN>/up` mengembalikan 200 dengan sertifikat yang sah.
3. Tautan di halaman login memakai `https://`.
4. Selesaikan satu sesi Ngobrol; analisis muncul (berarti `worker` jalan).
5. `docker compose restart`: data dan akun tetap ada.

## Partner percakapan (Ngobrol)

- **Suara**: input suara (mic) memakai Web Speech API bawaan browser, jadi hanya
  berjalan di **Chrome atau Edge**. Di browser lain, tetap bisa mengetik. Setiap
  pesan user dicatat asalnya di `conversation_turns.input_mode` (`text` atau `voice`).
- **Kunci level HSK 1** (`HSK_LEVEL_LOCK=true`, bawaan): sesi Mandarin mendapat
  daftar 150 hanzi HSK 1 beserta instruksi untuk hanya memakai kata dari daftar
  itu, dan menulis kalimat Mandarin tanpa pinyin, kurung, atau terjemahan (karena
  dibacakan text-to-speech). Halaman chat menampilkan lencana "Level: HSK 1 (terkunci)".
  - **Biaya**: tiap pesan Mandarin menambah sekitar **1.074 karakter atau ±440 token
    input** (daftarnya sendiri 378 karakter, ±270 token). Ini perkiraan kasar, bukan
    hitungan tokenizer; cek ulang dengan `php artisan hsk:prompt-stats`. Token input
    yang sebenarnya per panggilan tercatat di `conversation_api_calls.tokens_in`.
  - `HSK_LEVEL_LOCK=false` mematikan fitur ini, dan biaya tambahan itu ikut hilang.
  - **Keterbatasan**: kunci ini bekerja lewat instruksi di prompt. Sifatnya imbauan
    kuat, **bukan jaminan**; model tetap bisa sesekali memakai kata di luar HSK 1.

### Melihat model Gemini yang tersedia (`gemini:models`)

```bash
php artisan gemini:models
```

Mencetak nama model yang bisa dipakai kunci `GEMINI_API_KEY` untuk
`generateContent`, satu per baris, untuk mengisi `GEMINI_MODEL` atau
`GEMINI_FALLBACK_MODELS` tanpa menebak. Kunci dikirim lewat header
`x-goog-api-key`. Yang dicetak hanya nama model, tanpa kunci dan tanpa isi
respons lainnya. Perintah ini tidak memakai kuota pembuatan teks. Bila kunci
kosong atau ditolak, kuota habis, layanan sedang bermasalah, atau koneksi
gagal, yang muncul pesan singkat dalam bahasa Indonesia.

## Ketahanan terhadap beban Gemini

Saat Google sedang kebanjiran permintaan, Gemini menjawab HTTP 503 ("This
model is currently experiencing high demand"). Sering kali permintaan yang sama
berhasil beberapa detik kemudian, jadi aplikasi mengulangnya sendiri. Semua
aturannya ada di satu kelas, `App\Support\GeminiRetry`, yang dipakai oleh semua
panggilan HTTP ke Gemini: klien chat (`GeminiClient`, yang juga dipakai analisis
kesalahan versi Gemini) dan pengenal benda Raba lalu dengar
(`GeminiObjectRecognizer`). Langflow punya mekanisme coba ulang sendiri dan
tidak ikut diubah. Kunci Gemini selalu dikirim lewat header `x-goog-api-key`,
tidak pernah di URL, sehingga tidak bisa ikut tercetak di pesan galat koneksi.

**Aturan:**

- Yang diulang **hanya** HTTP 503 dan galat koneksi atau timeout. Respons 400,
  403, 404, 429, dan galat lain langsung gagal dengan satu panggilan saja.
- Maksimal `GEMINI_RETRY_MAX` kali coba ulang (bawaan 2, jadi 3 percobaan
  total). Jedanya `GEMINI_RETRY_DELAYS` (bawaan 1 detik lalu 3 detik), masing-masing
  ditambah acak kecil 0 sampai `GEMINI_RETRY_JITTER_MS` milidetik (bawaan 250).
- **Anggaran waktu per jenis panggilan** adalah batas keras untuk seluruh
  proses: semua percobaan, semua jeda, dan model cadangan. Timeout tiap
  percobaan dipotong menjadi sisa anggaran. Percobaan berikutnya tidak dimulai
  bila jeda ditambah minimal 1 detik waktu panggilan tidak muat lagi.

  | Jenis | Variabel | Bawaan | Timeout per percobaan |
  | --- | --- | --- | --- |
  | Chat (Ngobrol, pesan pembuka, umpan balik akhir sesi) | `GEMINI_RETRY_BUDGET_CHAT` | 15 detik | 30 detik, dipotong ke sisa anggaran |
  | Kamera (Raba lalu dengar) | `GEMINI_RETRY_BUDGET_SCAN` | `SCAN_BENDA_TIMEOUT` bila diisi, kalau tidak 30 detik | `SCAN_BENDA_TIMEOUT`, dipotong ke sisa anggaran |
  | Analisis akhir sesi (analyzer Gemini) | `GEMINI_RETRY_BUDGET_ANALYSIS` | 120 detik | seluruh sisa anggaran |
  | Terjemahan arti (`translate:meanings`) | tidak ada | tidak dipotong | 30 detik, satu panggilan tanpa `GeminiRetry` |

  Variabel yang kosong memakai nilai bawaan. Variabel lama
  `GEMINI_RETRY_BUDGET_SECONDS` (bawaan 12) tetap dibaca, tetapi hanya sebagai
  cadangan untuk jenis panggilan yang tidak punya anggaran sendiri. Jawaban
  yang memang butuh lebih lama dari anggarannya ikut terpotong. Naikkan
  variabel jenis itu bila hal ini terjadi.
- **Analisis di antrean**: `AnalyzeSessionJob` punya timeout 180 detik.
  Langflow (sampai `LANGFLOW_TIMEOUT`, bawaan 120 detik) ditambah Gemini harus
  selesai sebelum 180 - 15 = 165 detik. Karena itu anggaran analisis Gemini
  dipotong ke sisa waktu tersebut. Kalau Langflow memakai 120 detik penuh lalu
  gagal, Gemini hanya mendapat sekitar 45 detik. `retry_after` antrean
  (`DB_QUEUE_RETRY_AFTER`, kini bawaan 200 detik) harus selalu di atas 180.
  Kalau tidak, job yang lambat diserahkan ke worker kedua dan analisisnya
  jalan dua kali.
- **Model cadangan** `GEMINI_FALLBACK_MODELS` (daftar dipisah koma, **bawaan
  kosong**). Model cadangan hanya dicoba bila model utama tetap 503 setelah
  semua percobaan ulangnya, berurutan sesuai daftar, dengan aturan yang sama
  dan anggaran waktu yang sama (anggaran jenis panggilan itu). Galat koneksi atau timeout tidak memicu model
  cadangan. Isi daftar ini hanya dengan nama dari `php artisan gemini:models`.
  Aplikasi tidak menebak nama model.
- **Log**: tiap percobaan yang gagal dicatat sebagai `gemini: percobaan gagal`
  dengan empat isian saja: nama kelas galat, kode status HTTP (kosong untuk galat
  koneksi), nomor percobaan, dan model. Teks pesan, isi permintaan, gambar, dan
  kunci tidak pernah dicatat.
- **Kuota dan privasi**: percobaan ulang terjadi di dalam klien, di bawah
  `ConversationService`. Satu pesan tetap satu giliran pengguna dan satu jatah
  kuota harian, berapa pun percobaannya. Bila semua percobaan gagal, giliran
  pengguna dihapus seperti sebelumnya dan kuota tidak berkurang. Untuk Raba lalu
  dengar, byte foto hanya ada di memori selama percobaan: tidak ada berkas dan
  tidak ada log.
- **Tampilan**: bila semua percobaan habis, pengguna melihat pesan yang sudah
  ada ("Layanan AI sedang penuh, coba lagi sebentar."). Di Raba lalu dengar,
  pesan itu disertai tombol **Foto lagi**.
- `translate:meanings` **tidak** memakai `GeminiRetry`. Pekerjaan massal ini
  tetap memakai coba ulang lambatnya sendiri (429 dan 5xx, jeda 15/30/60 detik)
  dengan satu panggilan HTTP per percobaan, jadi coba ulangnya tidak bertumpuk.

**Batas jujurnya:** coba ulang hanya menolong lonjakan beban yang singkat. Bila
Gemini penuh lebih lama dari anggaran waktunya, permintaan tetap gagal. Coba
ulang juga tidak menjamin layanan tersedia. Model cadangan hanya membantu bila
model itu memang tersedia untuk akun atau kunci yang dipakai. Model lain bisa
saja sedang penuh juga. Pengguna juga menunggu lebih lama sebelum melihat pesan
galat, paling lama sebesar anggaran jenis panggilannya (15 detik untuk chat,
30 detik untuk kamera).

## Analisis kesalahan ("Kesalahan terbanyakmu")

Saat sesi percakapan **Mandarin** diselesaikan, transkripnya dianalisis AI di
latar belakang (`AnalyzeSessionJob`). Tiap kesalahan diberi satu kategori tetap
(urutan kata, 是 dengan kata sifat, 不 atau 没, kata penggolong, nada,
pelafalan, pilihan kata, lainnya). Hasilnya tampil di ringkasan sesi, dan tiga
kategori teratas 7 hari terakhir tampil di halaman Progress.

- **Analyzer**: memakai flow IBM Langflow bila `LANGFLOW_URL` dan
  `LANGFLOW_FLOW_ID` terisi. Kalau Langflow error, timeout, atau mengembalikan
  JSON rusak, analisis otomatis jatuh ke Gemini. Tanpa Langflow, langsung Gemini.
  Kalau keduanya tidak terkonfigurasi, analisis tidak dijalankan dan bagiannya
  tidak ditampilkan. Setiap analisis memakai satu panggilan AI.
- **Sumber**: `error_analysis.sumber` berisi `langflow` atau `gemini`, yaitu
  analyzer yang benar-benar menghasilkan jawaban (jadi hasil cadangan tercatat
  sebagai `gemini`). Ringkasan sesi menampilkan "Dianalisis oleh IBM Langflow"
  atau "Dianalisis oleh Gemini (cadangan)". Sesi lama tanpa kunci ini tidak
  menampilkan label.
- **Permintaan Langflow**: sama dengan contoh di panel *API access* Langflow,
  yaitu `POST {LANGFLOW_URL}/api/v1/run/{LANGFLOW_FLOW_ID}?stream=false` dengan
  header `x-api-key` dan body `output_type`, `input_type`, `input_value`,
  `session_id`. `LANGFLOW_URL` cukup URL dasar; garis miring di akhir atau
  akhiran `/api` dibuang otomatis. Tiap analisis memakai `session_id` baru
  (`analisis-{id sesi}-{waktu}-{acak}`) supaya riwayat chat Langflow tidak
  tercampur antarsesi. `LANGFLOW_API_KEY` wajib untuk Langflow yang memakai
  autentikasi; header `x-api-key` hanya dikirim bila key terisi.
- **Timeout**: `LANGFLOW_TIMEOUT` (detik, bawaan 120). Satu analisis transkrip
  dengan banyak kesalahan pernah memakan sekitar 74 detik. Job punya batas
  waktu 180 detik: cukup untuk Langflow lalu cadangan Gemini.
- **Error Langflow di log**: respons non-2xx dicatat sebagai
  `Langflow HTTP {status}: {500 karakter pertama isi respons}`. Sebelum
  dipotong, isi itu disamarkan (`[REDACTED]`): nilai `LANGFLOW_API_KEY` dan
  `GEMINI_API_KEY`, serta apa pun yang mirip `sk-…`, `AIza…`, `AstraCS:…`.
  Header dan key tidak pernah dicatat.
- **Retry**: dengan worker antrean, kegagalan Langflow yang sementara (HTTP 429,
  502, 503, 504, atau teks "high demand"/"overloaded") diulang sampai 3
  percobaan dengan jeda 15 lalu 45 detik. Kegagalan permanen (401, 403, 422,
  kuota habis, JSON rusak, timeout koneksi) tidak diulang dan langsung jatuh ke
  Gemini. Percobaan terakhir juga jatuh ke Gemini. Dengan `QUEUE_CONNECTION=sync`
  tidak ada retry: langsung Langflow lalu Gemini, sehingga sesi tidak menggantung.
- **Gagal**: sesi dengan analisis gagal bisa dijalankan ulang dengan
  `php artisan analysis:retry-failed` (pakai `--dry-run` untuk melihat daftarnya dulu).

### Mendiagnosis Langflow (`langflow:ping`)

```bash
php artisan langflow:ping --config          # tanpa jaringan: apa yang terisi, timeout, URL akhir
php artisan langflow:ping                   # SATU panggilan sungguhan: status, waktu, teks hasil
php artisan langflow:ping --dump-structure  # + pohon kunci JSON respons, simpan respons tersamarkan
```

- `--config` tidak memanggil jaringan dan tidak pernah mencetak key (hanya
  "terisi, panjang N" atau "kosong").
- Tanpa `--config`, perintah ini mengirim transkrip pendek tetap ke Langflow dan
  **memakai kuota model**. Error ditampilkan dengan status HTTP dan isi respons
  yang sudah disamarkan, beserta jenisnya (sementara atau permanen).
- `--dump-structure` mencetak nama kunci dan nilai string (dipotong 80 karakter,
  disamarkan), lalu menyimpan seluruh respons yang sudah disamarkan ke
  `storage/app/langflow-last-response.json` (tidak ikut git). Periksa isinya,
  lalu salin ke `tests/Fixtures/langflow_response.json` sebagai fixture tes.

### Menjalankan analisis saat demo

Analisis berjalan sebagai job antrean. Ada dua cara:

1. **Disarankan:** `QUEUE_CONNECTION=database` (bawaan) dengan worker di terminal terpisah:

   ```bash
   php artisan queue:work
   ```

   Tanpa worker, job hanya menumpuk di tabel `jobs`. Ringkasan sesi berhenti
   menunggu setelah 150 detik dan menampilkan tombol **Muat ulang**; hasilnya
   juga muncul di Progress setelah worker selesai memprosesnya.
   `composer run dev` sudah menjalankan worker (`queue:listen`).

   **`DB_QUEUE_RETRY_AFTER` bawaannya 200 detik.** Jangan turunkan di bawah
   180 detik, karena job analisis boleh berjalan sampai 180 detik. Antrean
   menganggap job yang belum selesai setelah `retry_after` detik sebagai macet dan menyerahkannya
   lagi ke worker, sehingga analisis yang lambat bisa jalan dua kali (dua
   panggilan AI). `retry_after` harus selalu lebih besar dari timeout job.

2. **`QUEUE_CONNECTION=sync`:** job dijalankan setelah respons dikirim
   (`afterResponse`), tanpa worker. Catatan: `php artisan serve` hanya
   memproses **satu permintaan sekaligus** kecuali `PHP_CLI_SERVER_WORKERS`
   diatur (misalnya `PHP_CLI_SERVER_WORKERS=4`). Jadi selama analisis berjalan
   (bisa sampai puluhan detik), polling dan halaman lain bisa tertahan.

## Mengisi konten (`app:seed-content`)

Semua berkas data dibaca dari `storage/app/seed-data`:

- `reference/*.json` (di-commit): kurikulum HSK1, minggu, tips, tautan, dan
  data referensi lain (`ReferenceDataSeeder`);
- `mandarin_hsk1to6_complete.json`, `irregular_verbs_183_complete.json`,
  `english_vocab_cefr_10674.json` (**tidak** di-commit, karena besar; salin
  sendiri ke server);
- `kesalahan_game.json`, `daftar_benda_tertutup.json`,
  `hsk1to3_kosakata_id.json` (di-commit; dibaca saat aplikasi berjalan).

Setelah `php artisan migrate`, jalankan sekali:

```
php artisan app:seed-content
```

Perintah ini hanya mengisi tabel konten (tidak membuat pengguna) dan aman
dijalankan ulang: tabel referensi yang sudah berisi dilewati (id-nya dipakai
progres pengguna, jadi tidak pernah dihapus), sedangkan HSK, kata kerja, dan
kosakata memakai upsert. Bila berkas sumber tidak ada, seeder itu dilewati
dengan pesan, lalu perintah keluar dengan status gagal supaya terlihat di log
deploy.

## Data HSK dan koreksinya

Kosakata HSK1-6 diimpor dari `storage/app/seed-data/mandarin_hsk1to6_complete.json`.
Untuk sebagian kata, dataset ini memilih homograf yang keliru, misalnya 都
"Dū, surname Du" (seharusnya dōu) atau 听 "yǐn, smile (archaic)" (seharusnya tīng).

- **HSK1 (kurikulum, `mandarin_words`)**: pinyin dan arti diambil dari data kurasi
  `storage/app/seed-data/reference/`. Seeder HSK hanya menambah kolom pelengkap.
  Basis data yang sudah terlanjur tertimpa dipulihkan dengan
  `php artisan hsk:repair-curriculum` (pakai `--dry-run` untuk melihat perubahan dulu).
- **HSK2-3 (`mandarin_hsk_words`)**: dikoreksi dari
  `storage/app/seed-data/hsk1to3_kosakata_id.json` dengan
  `php artisan hsk:apply-corrections` (juga ada `--dry-run`). Baris yang dikoreksi
  ditandai `data_status = corrected_draft` dan tampil dengan lencana
  "Data dikoreksi (draf)" di Kamus dan Kartu.
- Glos Inggris yang **arti pertamanya** (bagian sebelum `;` pertama) diawali
  "surname" atau "(slang)" tidak dipakai sebagai arti utama dan tidak
  diterjemahkan otomatis. Arti slang di urutan berikutnya tidak berpengaruh,
  misalnya 机场 "airport; …(slang)…". Jika arti Indonesia belum ada, tampil
  "Arti belum tersedia". Terjemahan lama dari glos seperti itu bisa dikosongkan
  dengan `php artisan hsk:clear-unreliable-translations` (ada `--dry-run`).
- `translate:meanings` mengirim hanzi dan pinyin bersama glos Inggris, supaya
  model menerjemahkan makna HSK yang dimaksud.

**Keterbatasan:**

- Data terkoreksi masih berstatus **draf** dan belum ditinjau ulang.
- **HSK4-6 belum dikoreksi**, jadi masih bisa memuat homograf atau glos yang keliru.
- Arti Indonesia hasil `translate:meanings` adalah terjemahan mesin dari glos Inggris.

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

## Aksesibilitas

Status per butir di cabang `fitur-aksesibilitas`. **Selesai** berarti sudah dikerjakan dan markup HTML-nya dites otomatis (`AccessibilityChatTest`, `DisplaySettingsTest`, `BantuanPageTest`, `KataBicaraTest`). Itu belum berarti sudah diverifikasi dengan pembaca layar: pengujian manual oleh tim dengan Narator sedang berlangsung.

Pedoman menulis teks untuk pengguna pembaca layar (judul yang menyebut bagiannya, simbol yang dibacakan sebagai kata, penandaan bahasa, teks alt, dan lainnya) ada di [`docs/pedoman-teks-pembaca-layar.md`](docs/pedoman-teks-pembaca-layar.md). Halaman awal sudah mengikutinya.

| Butir | Status | Catatan |
|---|---|---|
| `lang="id"`, tautan "Lewati ke konten" / "Lewati ke kolom tulis" | Selesai | Tautan tampil saat mendapat fokus. |
| Landmark header, nav (berlabel), main `#konten`, footer; satu `h1` | Sebagian | Ada di layout halaman belajar. Layout `app`/`guest` (profil, login) hanya mendapat `lang="id"`. |
| Kolom tulis chat: label, `aria-describedby`, Enter / Shift+Enter, IME | Selesai | Fokus tetap di kolom setelah kirim. |
| Percakapan `role="log"` + `aria-live="polite"`, nama pengirim, `lang` zh-Hans/en/id | Selesai | Pesan pengguna berhanzi diberi `zh-Hans`; pinyin tidak. |
| Daerah `role="status"` (Terkirim, membalas, analisis) | Selesai | Polling tidak memindahkan fokus. |
| Tombol bicara `aria-pressed`, tombol putar per balasan, konfirmasi "Selesai dan analisis" sebaris | Selesai | |
| Pengaturan tampilan: ukuran teks (3 tingkat), kontras tinggi, mode pembaca layar | Selesai | Disimpan di `localStorage`, diterapkan sebagai kelas pada `<html>`. Hanya di layout halaman belajar. |
| Teks dasar 18 px, fokus 3 px, target 44 px, `prefers-reduced-motion` | Sebagian | Aturan CSS global sudah ada; belum dicek visual di setiap halaman. |
| Kontras teks ≥ 4,5:1 | Sebagian | Warna `ink-soft`, `ink-faint`, `mandarin`, `english` digelapkan (≥ 5:1 di latar cream/putih). Halaman lain belum diaudit satu per satu. |
| Informasi tidak hanya lewat warna | Sebagian | Halaman aktif di navigasi diberi garis bawah + `aria-current`. Halaman lain belum diaudit. |
| Halaman Bantuan (`/bantuan`) dan pernyataan aksesibilitas (draf) | Selesai | |
| Kata bicara (`/kata-bicara`) | Selesai | Katalog `storage/app/seed-data/daftar_benda_tertutup.json` sudah ada di repo (di-commit). Jika berkas itu hilang atau rusak, halaman menampilkan "Katalog belum tersedia". Skema yang diharapkan ada di `config/katabicara.php`. |
| Halaman lain (Kartu, Kamus, Ujian, Isian, Game, Ucapan, Progress) | Belum diaudit | Hanya mendapat perubahan layout dan warna global. |
| Uji manual oleh tim dengan pembaca layar Narator | Sedang berlangsung | Belum selesai untuk butir mana pun di atas. |
| Uji dengan pengguna tunanetra | Belum dilakukan | |

**Batas:**

- Dirancang untuk pembaca layar. Pengujian oleh tim dengan Narator **sedang berlangsung**; **belum** dilakukan dengan pengguna tunanetra.
- Input dan output suara memakai Web Speech API dan hanya berjalan di **Chrome atau Edge**.
- Tes otomatis memeriksa markup HTML, bukan pengalaman nyata dengan pembaca layar.

## Game inklusif

Halaman `/game` berisi kartu permainan (`App\Livewire\Games\Hub`; tambah game
dengan menambah satu entri di `games()`). Tautan "Game" di navigasi membuka
halaman ini. Semua game tanpa panggilan AI.

- **Dengar & Pilih** (`/game/dengar-pilih`): 5 soal per sesi. Kata diambil dari
  kosakata HSK 1 yang punya arti bahasa Indonesia (tabel yang sama dengan
  Kartu, satu baris per hanzi) dan, bila ada, daftar benda tertutup
  (`ikut_prompt: true`). Hanzi dan pinyin selalu tampil sebagai teks; kata
  diucapkan dengan suara browser (tidak otomatis bila Mode pembaca layar
  aktif), ada tombol "Dengarkan lagi" dan pilihan kecepatan suara. Empat
  pilihan arti (pengecoh dari level yang sama). Jawaban tidak disimpan ke
  basis data: `game_attempts` dipakai Progress sebagai kategori kesalahan
  Pemburu Kesalahan, jadi jawaban kosakata tidak dicampur ke sana.
- **Pemburu Kesalahan** (`/game/pemburu-kesalahan`): alur dan penyimpanan
  jawaban tidak berubah; pilihan kini berupa radio dalam fieldset dan ada
  tombol "Dengarkan kalimat". Soalnya dibaca dari
  `storage/app/seed-data/kesalahan_game.json` (`GAME_ITEMS_PATH`; soal masih
  draf). Bila berkas itu tidak ada, halaman hanya menampilkan pesan bahwa soal
  belum tersedia.

**Dirancang agar dapat dimainkan dengan keyboard, suara, dan tampilan
diperbesar:** pilihan berupa radio berlabel dalam fieldset (panah untuk
memilih, Enter untuk menjawab), tanpa batas waktu dan tanpa seret-lepas,
umpan balik berupa teks dengan simbol ✓/✗ yang diumumkan (`role="status"`),
fokus pindah ke tombol berikutnya setelah menjawab, hanzi berukuran 4rem dan
tombol jawaban minimal 3,5rem yang ikut membesar mengikuti ukuran teks, satu
kolom, serta opsi "Jarak baris dan huruf lebih lega" di panel Tampilan.

**Batas:**

- **Belum** diuji dengan pengguna tunanetra atau pengguna lemah penglihatan.
  Tes otomatis hanya memeriksa markup HTML.
- Hasil Dengar & Pilih **tidak masuk halaman Progress**. Ini disengaja: Progress
  membaca `game_attempts` sebagai kategori kesalahan Pemburu Kesalahan, dan
  jawaban kosakata akan mencemari kategori itu.
- Suara memakai suara bawaan browser (Web Speech API); kualitasnya bervariasi
  antarperangkat, dan suara Mandarin harus terpasang di perangkat agar kata
  Mandarin terdengar benar.
- Panel Tampilan masih punya tiga tingkat ukuran teks (Normal, Besar, Sangat
  besar). Tingkat keempat belum ditambahkan.
- Rencana: tahap benar atau salah sebelum memilih kategori, untuk mengurangi
  beban metabahasa; belum dibangun.

## Raba lalu dengar

Pelajar memotret satu benda yang dipegang dengan kamera bawaan perangkat, lalu
aplikasi menampilkan dan membacakan namanya dalam bahasa Indonesia, Inggris,
dan Mandarin (`/scan-benda`).

- **Dimatikan secara default.** Aktifkan dengan `SCAN_BENDA_ENABLED=true`.
  Selama mati, `/scan-benda` dan `POST /scan-benda/kenali` menjawab 404 dan
  tautannya tidak tampil di navigasi. Pengaturan lain ada di `config/scan.php`
  (`SCAN_BENDA_MAX_IMAGE_BYTES`, `SCAN_BENDA_RATE_LIMIT_PER_MINUTE`,
  `SCAN_BENDA_TIMEOUT`).
- **Daftar tertutup.** Model visi (Gemini, memakai `GEMINI_API_KEY` dan
  `GEMINI_MODEL`) hanya boleh memilih `benda_id` dari entri `ikut_prompt: true`
  di `storage/app/seed-data/daftar_benda_tertutup.json` (lewat
  `KataBicaraCatalog`), atau menjawab "tidak yakin" atau "perlu foto ulang".
  Nama Indonesia, Inggris, hanzi, pinyin, dan IPA selalu diambil dari katalog,
  tidak pernah dari teks model. `benda_id` di luar daftar dianggap "tidak
  yakin". Tanpa `GEMINI_API_KEY`, aplikasi selalu menjawab "tidak yakin"
  (catatan: model visi belum terhubung).
- **Hanya keyakinan tinggi yang diumumkan pasti.** Keyakinan sedang atau rendah
  diumumkan sebagai "Mungkin …" dengan tawaran foto ulang.
- **Foto tidak disimpan.** Foto dikompres di browser (sisi terpanjang sekitar
  1024 px, JPEG), dikirim sebagai base64 di badan JSON, diproses di memori,
  lalu dibuang. Tidak ada berkas gambar di storage, tidak ada kolom basis
  data, tidak memakai unggahan sementara Livewire, dan gambar maupun teks
  model tidak ditulis ke log. Foto memang dikirim ke layanan Gemini untuk
  dikenali; pengguna harus mencentang persetujuan dulu.
- **Pratinjau foto hanya tampil di layar.** Foto yang sama (versi sekitar
  1024 px) ditampilkan di samping hasil lewat object URL di browser, dengan
  keterangan bahwa foto tidak disimpan. Foto tidak ditulis ke localStorage,
  sessionStorage, IndexedDB, atau state Livewire, dan object URL dilepas saat
  "Foto lagi", saat foto baru dipilih, saat galat, dan saat meninggalkan
  halaman. Kotak centang "Tampilkan foto di layar" (bawaan aktif) bisa
  dimatikan untuk menyembunyikan foto yang berisi hal pribadi; yang disimpan di
  localStorage (`scanBenda`) hanya pilihan kotak centang itu, bukan fotonya.
- **Lembar catatan uji di browser belum dibuat.** Rencananya hanya di memori
  halaman dan tanpa foto, tetapi membutuhkan `benda_id` dan keyakinan dalam
  jawaban endpoint, yang belum dikirim.
- **Bukan alat keselamatan atau medis.** Jangan dipakai untuk obat atau benda
  berbahaya; peringatan ini tampil di halaman.
- **Deskripsi bentuk adalah perkiraan** dari foto dan mungkin tidak persis
  dengan perabaan.
- **Status uji:** baru diuji dengan tes otomatis memakai pengenal palsu dan
  `Http::fake`. **Belum** diuji dengan foto sungguhan maupun dengan model visi
  yang benar-benar terhubung, dan **belum diuji dengan pengguna tunanetra**.

## Mode suara

Lapisan suara bersama untuk seluruh aplikasi (`resources/js/voice/engine.js`
dan `resources/js/voice/adapters.js`), memakai Web Speech API bawaan browser
tanpa biaya. **Pelengkap, bukan pengganti:** markup semantik, keyboard, dan
pembaca layar tetap jalur utama dan tidak diubah. Mati secara bawaan.

- **Kontrol:** tombol "Mode suara" (`aria-pressed`) adalah kontrol pertama
  setelah tautan lewati; tombol "Berhenti" tampil selama mode aktif. Tidak ada
  pintasan tombol tunggal. Mode suara dan Mode pembaca layar saling
  meniadakan.
- **Alur:** aplikasi berbicara (antrean dengan bahasa per segmen: id-ID, en-US,
  zh-CN), lalu bunyi pendek, lalu mikrofon dibuka (id-ID, 3 alternatif).
  Mikrofon tidak pernah terbuka saat aplikasi berbicara. Ketiga alternatif
  dikirim dalam satu `POST /voice/command` dan masing-masing diurai
  (`App\Support\VoiceAlternatives`); teks mentahnya tidak dibandingkan.
  Alternatif teratas dikenal dan tidak ada alternatif lain yang dikenal
  sebagai perintah berbeda: langsung dijalankan (juga untuk jawaban game).
  Dua alternatif dikenal sebagai perintah berbeda: aplikasi bertanya "satu,
  ujian, atau dua, isian?". Hanya alternatif lain yang dikenal: "Saya dengar:
  … Benar?". Keyakinan di bawah 0,5 hanya memicu "Saya dengar: … Benar?" bila
  nomor yang dipilih menekan tindakan yang tidak bisa dibatalkan, dan
  tindakan itu tetap ditanya "Yakin?". Diam 8 detik diingatkan sampai dua
  kali, lalu mode berhenti.
- **Perintah** dikenali oleh `App\Support\VoiceCommandParser` lewat
  `POST /voice/command`, yang hanya mengembalikan `{type, target, index}`
  (ditambah dua kandidat untuk `clarify`): buka (nama halaman), mulai,
  kembali, ulangi, baca halaman, daftar tindakan, nomor 1–12 atau a–d, lanjut,
  ya/tidak, berhenti, perintah, isi/selesai isi. Pengurainya toleran terhadap
  ucapan alami:
  - kata pengisi di mana saja dibuang ("buka", "bukakan", "masuk ke",
    "pergi ke", "tolong", "mau", "ingin", "aku", "saya", "halaman", "menu",
    "fitur", "coba", "ayo", "yuk", "dong", "deh", "tes", …), jadi "masuk ke
    game" dan "tes obrolan" dikenali;
  - alias halaman: game/gim/permainan/main game, ngobrol/obrolan/percakapan/
    chat/ngomong, ucapan/pengucapan/cek pengucapan, kata bicara/kamus bicara,
    kartu/flashcard, ujian/kuis, progress/kemajuan, bantuan/bantu, dan raba
    lalu dengar/kamera/potret (hanya bila fiturnya aktif, selain itu "belum
    tersedia");
  - salah dengar umum pengenalan id-ID untuk "game": gem, geim, gaim, gam;
  - satu huruf salah (jarak Levenshtein 1) untuk kata 5 huruf atau lebih; bila
    dua perintah berbeda sama dekat ("usian": ujian atau isian), hasilnya
    `clarify` dan aplikasi bertanya "satu atau dua", tidak menebak;
  - `start`: "mulai", "main", "ayo mulai", "mulai main", "mulai pemburu
    kesalahan", "main dengar dan pilih". "Main" saja berarti mulai; "main game"
    membuka halaman Game;
  - jawaban: "pilih satu", "jawaban dua", "jawab tiga", "opsi empat", "nomor
    tiga", "yang pertama".

  Ucapan yang memuat dua perintah, dua angka berbeda, atau perintah yang
  bertabrakan tetap dianggap tidak dikenali. Saat tidak dikenali, aplikasi
  membacakan dan menampilkan (lewat `role="status"`) "Saya dengar: …" serta
  perintah yang bisa dipakai di halaman itu; "perintah" membacakan daftar yang
  disesuaikan dengan halaman aktif. Tindakan yang tidak bisa dibatalkan (Hapus,
  Reset, Selesai dan analisis, Keluar) selalu dikonfirmasi.
- **Mulai:** di hub Game dan pemilihan skenario Ngobrol, "mulai" menekan
  kontrol bertanda `data-voice-start`; bila ada lebih dari satu, pilihannya
  dibacakan bernomor. Di dalam game, "mulai" membacakan soal atau menekan "Main
  lagi".
- **Mode debug** (mati secara bawaan): tambahkan `?voicedebug=1` pada alamat
  untuk melihat panel kecil berisi teks terdengar, alternatif, confidence, dan
  hasil parse terakhir. Isinya hanya di memori dan tidak disimpan.
- **Adapter halaman** (`data-voice-page`, dicari ulang dari DOM pada setiap
  ucapan; satu MutationObserver pada `<main>` dan event `livewire:navigated`
  memanggil `onActivate` saat halaman berganti, tanpa pendengar ganda): hub
  Game, pemilihan skenario Ngobrol, Kata bicara ("cari …",
  "dengarkan inggris"), Dengar & Pilih dan Pemburu Kesalahan (soal dan pilihan
  bernomor; jawaban lewat radio dan tombol Jawab yang sudah ada), Ngobrol
  (balasan dibacakan, ucapan dikirim lewat formulir yang sudah ada, "selesai"
  dengan konfirmasi), Kartu ("balik", "berikutnya", "dengarkan"), Hari Ini,
  Progress, dan Raba lalu dengar ("ambil foto" hanya memfokuskan tombol, karena
  browser tidak mengizinkan kamera dibuka lewat suara). Game "Pilih yang
  Benar" belum ada, jadi perintah itu dijawab "belum tersedia".
- **Data:** tidak ada audio, gambar, atau transkrip yang disimpan. localStorage
  hanya menyimpan tanda persetujuan sekali pakai; sessionStorage hanya
  menyimpan bahwa mode aktif supaya bisa dilanjutkan setelah pindah halaman.
  Endpoint tidak menulis transkrip ke log atau basis data. Teks terakhir yang
  terdengar hanya ada di memori halaman (untuk "Saya dengar" dan panel debug)
  dan dihapus saat mode berhenti.

**Batas (jujur):**

- Hanya Chrome atau Edge, dan butuh internet: audio diproses oleh penyedia
  pengenalan suara browser, bukan oleh aplikasi ini.
- Akurasi bervariasi; suara Mandarin harus terpasang di perangkat.
- Setiap pindah halaman adalah muat ulang penuh, jadi mikrofon perlu satu
  ketukan atau Enter pada tombol Mode suara untuk aktif lagi.
- Tes otomatis hanya memeriksa pengurai, endpoint, markup, dan teks kode JS;
  JS-nya belum diuji otomatis di browser. **Belum diuji dengan pengguna
  tunanetra.** Pembaca layar tetap jalur utama.

## Akun demo

Akun siap pakai untuk peninjau di lingkungan **lokal atau staging** (`APP_ENV=local` atau `staging`). Akun ini mati secara bawaan dan tidak pernah tersedia di `APP_ENV=production`.

1. Di `.env`, isi:

   ```
   DEMO_ACCOUNT_ENABLED=true
   DEMO_ACCOUNT_PASSWORD=<kata sandi khusus demo>
   # opsional, bawaan demo@studycompanion.test
   DEMO_ACCOUNT_EMAIL=demo@studycompanion.test
   ```

2. Jalankan `php artisan db:seed --class=DemoUserSeeder`. Perintah ini membuat atau memperbarui pengguna "Akun Demo" dengan email terverifikasi dan onboarding selesai. Tidak ada data sesi atau latihan palsu. Bila akun demo aktif, `php artisan db:seed` juga memanggil seeder ini.
3. Halaman login menampilkan kotak "Akun demo (lokal dan staging, bukan production)" berisi email, kata sandi, dan tombol **Isi otomatis**. Tombol itu hanya mengisi kolom, tidak mengirim formulir.

**Peringatan:**

- Hanya untuk lokal dan staging. Seeder menolak berjalan di `APP_ENV=production`, dan kotak login hanya dirender bila `DEMO_ACCOUNT_ENABLED=true`, kata sandi terisi, **dan** environment bukan production. Bila akun demo mati, kotak itu tidak meninggalkan jejak di HTML.
- Kata sandi tampil apa adanya di halaman login. Pakai kata sandi **khusus demo** dan **jangan pernah** dipakai di tempat lain.
- Kata sandi hanya dibaca dari `.env`; tidak ada nilai bawaan di kode. Bila kosong, seeder berhenti dengan pesan galat.
