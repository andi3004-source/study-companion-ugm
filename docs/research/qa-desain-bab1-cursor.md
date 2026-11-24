# QA desain halaman Bab 1 Buku Saku + jalur menjadi app

**Agent:** cursor  
**Tanggal:** 24 November 2026  
**Acuan visual:** `docs/orchestra/qa/bab1-desktop.png` (utama), `beranda-desktop.png`, `beranda-mobile.png`, `daftar-isi-desktop.png`  
**Acuan teks:** `docs/orchestra/mockup-figma.md`, `docs/orchestra/00-konteks-bersama.md`, spesifikasi Tuturnusa tunanetra

Dokumen ini mensimulasikan pengalaman pembaca layar dan low vision pada halaman Bab 1 (*Cara Menyikat Gigi Mandiri*), merespons masukan “negative space masih banyak” di desktop 1440px, dan membandingkan jalur distribusi aplikasi (PWA, TWA, Capacitor, Tauri 2) dengan implikasi arsitektur Laravel.

---

## 1. Pengguna buta total (simulasi pembaca layar)

### 1.1 Asumsi dan metode simulasi

Simulasi mengikuti urutan DOM yang disepakati (bukan tata letak visual): skip link → tombol Mode suara → logo → `main#konten` (Beranda, Daftar isi, meta bab, `h1`, kotak audio, bagian isi, navigasi bawah) → footer Bantuan. Perilaku elemen mengacu pada dokumentasi TetraLogical (NVDA/JAWS/TalkBack/VoiceOver), RAT media, WCAG, dan W3C APG — tanpa uji perangkat fisik di sesi ini.

### 1.2 Urutan Tab (keyboard / linear navigation)

Perkiraan urutan fokus dari atas:

| Langkah | Elemen | Yang didengar (contoh) | Catatan |
|--------|--------|------------------------|---------|
| 1 | Skip link | “Lewati ke konten utama, link” | Harus terlihat saat fokus ([G1 WCAG](https://www.w3.org/WAI/WCAG22/Techniques/general/G1)). |
| 2 | Mode suara | “Mode suara: mati, tombol” | DOM sebelum logo — konsisten beranda; anak melewati 1–2 stop sebelum konten bab. |
| 3 | Logo (tautan beranda) | “TUTURNUSA, link” | Biasanya memutar ke `/`. |
| 4 | Beranda | “Beranda, link” | Navigasi keluar bab. |
| 5 | Daftar isi (atas) | “Daftar isi, link” | **Duplikat nama** dengan tombol bawah. |
| 6 | Meta (teks biasa) | “Bab 1 dari 15 · Bagian 1: Kebersihan…” | Bukan heading; tidak muncul di daftar `H`. |
| 7 | `h1` | “Cara Menyikat Gigi Mandiri, heading level 1” | Judul utama bab. |
| 8 | Konten kotak audio | Judul kotak + kontrol `audio` | Lihat §1.4. |
| 9–12 | Kontrol native `<audio controls>` | Play, seek, mute, dll. | NVDA: tombol-tombol dalam pemutar ([TetraLogical NVDA](https://tetralogical.github.io/screen-reader-HTML-support/NVDA.html)). TalkBack: kontrol dijangkau per komponen, bukan “satu kotak” ([TalkBack](https://tetralogical.github.io/screen-reader-HTML-support/TalkBack-android.html)). |
| 13 | Paragraf penjelasan audio | Teks penuh kalimat bantuan | Penting: menjelaskan *tidak* autoplay. |
| 14 | `h2` Alat dan bahan | “Alat dan bahan, heading level 2” | |
| 15 | Paragraf alat | Teks daftar alat | |
| 16 | `h2` Langkah-langkah | “Langkah-langkah, heading level 2” | |
| 17+ | `<ol>` / `<li>` | “List … item 1 …” | Posisi “1 dari 8” pada tiap langkah ([web.dev struktur](https://web.dev/learn/accessibility/structure)). |
| 18 | `h2` Patokan indra | “Patokan indra, heading level 2” | Istilah “indra” berisiko (§1.6). |
| 19 | Isi kotak kuning | Paragraf petunjuk indra | Tanpa `role="note"`, dibaca sebagai teks biasa setelah heading. |
| 20 | Daftar isi (bawah, pill) | “Daftar isi, link” atau “tombol” | **Nama sama** dengan langkah 5. |
| 21 | Bab berikutnya | “Bab 2: Botol sabun dan sampo, link” | Lebih deskriptif — baik. |
| 22 | Bantuan | “Bantuan dan aksesibilitas, link” | Footer. |

**Tab stop di header (3 + logo + 2 nav + Mode suara + skip):** sekitar 6–7 stop sebelum `h1`. Bagi anak yang sering berpindah bab, ini bisa terasa repetitif meski sudah ada skip link ([SC 2.4.1](https://www.w3.org/WAI/WCAG22/Understanding/bypass-blocks)).

### 1.3 Navigasi struktural (landmark, heading, rotors)

**Landmark (daftar NVDA `INSERT+F7` / rotor VoiceOver):**

- Ekspektasi: satu `main#konten`, header tanpa `navigation` terpisah (hanya tautan inline), footer dengan tautan bantuan.
- Logo + Mode suara di luar `main` — konsisten mockup beranda; pembaca membedakan “banner” vs “konten utama” jika `<header>` dan `<main>` dipetakan dengan benar ([landmarks web.dev](https://web.dev/learn/accessibility/structure)).

**Heading (`H` / rotor Headings):**

| Level | Teks | Masalah? |
|-------|------|----------|
| 1 | Cara Menyikat Gigi Mandiri | OK |
| 2 | Alat dan bahan | OK |
| 2 | Langkah-langkah | OK |
| 2 | Patokan indra | OK secara struktur; leksikon perlu dipadankan (§1.6) |

Meta “Bab 1 dari 15” **bukan** heading — tidak muncul di peta heading. Anak yang lompat `H` langsung ke `h1` tanpa konteks bagian/bab kecuali membaca linear atau daftar link.

**TalkBack geser (swipe):** daftar `<ol>` tidak selalu masuk urutan geser container; item `<li>` tetap dibaca dengan posisi ([TalkBack](https://tetralogical.github.io/screen-reader-HTML-support/TalkBack-android.html)). Mode “Headings” / “Controls” di Chrome Android tetap relevan ([dukungan TalkBack + Chrome](https://support.google.com/accessibility/android/answer/2633135?hl=en)).

### 1.4 Kotak audio — kejelasan dan beban kognitif

Mockup menampilkan:

- Judul teks: “Audio narasi · durasi 3 menit 12 detik”
- `<audio controls preload="none">`
- Kalimat: “Dengarkan narasi, atau baca teks di bawah dengan pembaca layarmu. Audio tidak diputar sendiri.”

**Yang baik:**

- `preload="none"` + tidak autoplay selaras keputusan produk dan menghindari tabrakan dengan suara pembaca layar ([desain anti-goal autoplay](../superpowers/specs/2026-11-24-tuturnusa-tunanetra-refactor-design.md)).
- Instruksi eksplisit membedakan dua cara belajar (dengar vs baca).

**Yang membingungkan atau berlebihan:**

1. **Nama pemutar vs judul bab:** Kontrol `<audio>` native sering diumumkan generik (“audio”, tombol play) tanpa menyebut judul bab kecuali ada `aria-labelledby` ke judul kotak atau `h1` ([RAT media](https://jonneal.dev/rat/media.html), [NVDA `audio`](https://tetralogical.github.io/screen-reader-HTML-support/NVDA.html)).
2. **Banyak stop di area audio:** Judul (teks) → beberapa tombol pemutar → paragraf penjelasan = 3–6 fokus berturut-turut sebelum “Alat dan bahan”. Untuk anak yang tidak akan memutar audio, paragraf penjelasan wajib tetap ada, tetapi urutan bisa didengarkan sebagai “beberapa hal tentang audio” sebelum konten inti.
3. **`aria-label` hanya pada `<audio>` tanpa `controls`:** tidak terdengar di banyak kombinasi pembaca ([PowerMapper `audio-aria-label`](https://www.powermapper.com/tests/screen-readers/media/audio-aria-label/)). Desain memakai `controls` — tepat.

**Usul konkret:**

- Bungkus kotak dengan `role="region"` + `aria-labelledby` ke id judul kotak (mis. `id="audio-narasi-judul"`).
- Pertimbangkan **satu** heading tingkat 2 “Audio narasi” (visually tied) lalu durasi di teks biasa, supaya muncul di daftar heading: “Audio narasi, heading 2” → kontrol → penjelasan.
- Jangan mengandalkan `aria-label` pada elemen `audio` saja; gabungkan label dari teks terlihat.

### 1.5 Dua tautan “Daftar isi”

**Masalah:** Dua target dengan nama aksesibel sama (“Daftar isi”) di urutan yang jauh ([WCAG 2.4.4 Link Purpose](https://www.w3.org/WAI/WCAG22/Understanding/link-purpose-in-context) — masih bisa lulus jika konteks cukup, tetapi daftar rotor tautan menampilkan duplikat).

**Usul:**

- Atas: “Daftar isi buku” atau pertahankan “Daftar isi” dengan `aria-current` tidak relevan.
- Bawah (pill): “Kembali ke daftar isi” atau “Daftar isi, navigasi akhir bab”.
- Atau satu di atas dihapus di desktop jika redundan — tetapi untuk konsistensi mobile, lebih amat membedakan **nama aksesibel** (`aria-label`) daripada menghapus salah satu.

### 1.6 “Patokan indra” — pemahaman anak

Istilah **indra** (peraba, penciuman, dll.) mungkin belum umum di kelas dasar; konten sudah konkret (“ujung jari telunjuk…”, “lidah meraba…”). Risiko: anak mengabaikan heading dan melewatkan kotak kuning.

**Usul:**

- Pertahankan `h2` singkat untuk konsistensi buku, tambahkan baris pengantar satu kalimat **sebelum** kotak: “Ini tips memakai perabaan tubuh, bukan mata.”
- Opsional: `role="note"` pada kotak kuning dengan `aria-labelledby` ke `h2` ([semantik note MDN](https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Roles/note_role)) — beberapa pembaca mengumumkan “note”.
- Hindari hanya warna kuning sebagai petunjuk; border navy sudah membantu low vision.

### 1.7 Ringkasan masalah pembaca layar

| Isu | Dampak | Perbaikan prioritas |
|-----|--------|---------------------|
| Duplikat “Daftar isi” | Bingung di daftar tautan | Nama aksesibel berbeda |
| Meta sebelum `h1` | Konteks bagus di Tab, absent di daftar `H` | Pertimbangkan `p` dengan kelas visual kecil **atau** sematkan meta di dalam `h1` (aria-hidden pada duplikat) — hati-hati kompleksitas |
| Kotak audio banyak stop | Lelah sebelum langkah | Region + satu `h2` “Audio narasi” |
| “Patokan indra” | Istilah abstrak | Satu kalimat plain language |
| 6+ Tab sebelum isi | Repetitif | Skip link wajib diuji; Mode suara tetap global |

---

## 2. Low vision: tipografi, kontras, zoom, negative space

### 2.1 Kondisi desain saat ini (desktop 1440px)

- Kolom baca **720px**, latar putih `#FFFFFF`, teks navy gelap.
- `h1` 44px, body/langkah 20px (mockup), meta 18px, `h2` 28px.
- **Negative space:** (1440 − 720) / 2 = **360px** kosong per sisi; secara visual mockup terasa seperti ~640px “kosong” di kanan jika kolom rata kiri (header full-width memperkuat kesan asymetri).

**Kontras:** Kombinasi navy pada putih umumnya kuat; `ink-soft` `#4F5968` untuk teks sekunder perlu dicek ke WCAG 1.4.3 (rasio 4,5:1 teks normal) — [Understanding Contrast (Minimum)](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum).

**Panjang baris:** Pada 720px dan 20px, perkiraan ~70–85 karakter per baris bahasa Indonesia — mendekati batas disarankan **80 karakter** (AAA 1.4.8, [Visual Presentation](https://www.w3.org/WAI/WCAG22/Understanding/visual-presentation)).

### 2.2 Zoom 200–400% dan reflow 320px

- **SC 1.4.4 Resize Text:** teks harus dapat diperbesar 200% tanpa hilang fungsi ([WCAG 2.2](https://www.w3.org/TR/WCAG22/)).
- **SC 1.4.10 Reflow:** pada lebar setara **320 CSS px** (viewport 1280px @ 400% zoom), konten vertikal **tanpa scroll horizontal** kecuali pengecualian ([Understanding Reflow](https://www.w3.org/WAI/WCAG22/Understanding/reflow)).
- Layout satu kolom + flex/grid yang membiarkan teks wrap (bukan fixed px pada container teks) mendukung reflow ([teknik C20](https://www.w3.org/WAI/WCAG22/Techniques/css/C20)).

**Risiko pada mockup:** Tombol pill navigasi bawah berdampingan bisa sempit di 320px — harus **stack vertikal** di breakpoint mobile (mockup mobile belum ditampilkan untuk halaman bab, tetapi beranda sudah vertikal).

**Fokus:** Cincin fokus 3px putih + biru `#1D4ED8` hingga 6px (mockup beranda) — penting untuk low vision; harus konsisten di kotak audio dan tombol bab.

### 2.3 Apakah kolom lebih lebar + teks lebih besar lebih baik?

| Opsi | Pro low vision | Kontra |
|------|----------------|--------|
| Lebar 720 → 900px, font 20px | Teks terasa lebih besar per layar | Baris >80 karakter, tracking mata lebih sulit ([1.4.8](https://www.w3.org/WAI/WCAG22/Understanding/visual-presentation)) |
| Lebar tetap 720px, font 22–24px | Baris lebih pendek relatif terhadap huruf; kontras tetap | Perlu lebih banyak scroll vertikal |
| **720px + toggle “Teks besar”** (user prefs, `rem` scale) | Memisahkan kebutuhan zoom browser vs preferensi permanen | Butuh UI satu kontrol + penyimpanan `localStorage` |

**Rekomendasi desain:** untuk tunanetra low vision, **pertahankan ~65–75 karakter efektif**; naikkan **ukuran font dan line-height** (1.6 sudah pada langkah) daripada melebarkan kolom. Kolom lebih lebar lebih cocok untuk **panel sekunder** (bukan paragraf instruksi).

### 2.4 Negative space — layout desktop konkret

Tujuan: mengisi ruang 1440px **tanpa** memecah urutan baca linear dan **tanpa** memanjangkan baris isi utama.

#### Sketsa A — Kolom utama + panel kanan informatif (bukan teks instruksi)

```
|<-- 72px -->|<-------- 720px main -------->|<-- 24px -->|<--- 360px panel --->|<-- 72px -->|
|   margin   |  Beranda / Daftar isi        |    gap     |  [Ringkasan bab]     |   margin   |
|            |  h1 + audio + langkah        |            |  Bab 1 / 15          |            |
|            |  (max ~75 karakter/baris)    |            |  Durasi audio        |            |
|            |                              |            |  Daftar langkah (anchor)|        |
|            |                              |            |  (link ke #langkah-3)|            |
```

- **Total:** 72+720+24+360+72 = **1248px** (sisanya fleksibel atau margin simetris sampai 1440).
- Panel: `aria-label="Ringkasan bab"`, `nav` dengan daftar anchor ke `h2`/langkah — **melengkapi**, bukan menggandakan paragraf.
- Pembaca layar: panel **setelah** `main` di DOM *atau* `aside` dengan `aria-labelledby` agar tidak menyela audio sebelum langkah (urutan DOM: utamakan `main` penuh dulu, panel di akhir `main` atau `aside` berikutnya — uji Tab).

#### Sketsa B — Konten tetap 720px, “zona pinggir” bermakna visual

```
|<<<<<<<<<<<<<<<< 1440px latar #F5F7FA >>>>>>>>>>>>>>>>>>>>|
|          |<-- 720px kartu putih shadow -->|              |
|          |         konten bab               |  (kosong    |
|          |                                  |   visual    |
|          |                                  |   tenang)   |
```

- Mengurangi kesan “setengah halaman kosong” dengan **latar abu sangat muda** di pinggir, kartu putih di tengah — tidak mengubah panjang baris.
- Low vision yang sensitif kontras: pastikan kartu tetap putih `#FFF` untuk teks utama.

#### Sketsa C — Dua kolom hanya pada zoom ≥200% (CSS `min()`)

Pada zoom tinggi, panel kanan pindah **di bawah** `main` (satu kolom) — memenuhi reflow ([Reflow](https://www.w3.org/WAI/WCAG22/Understanding/reflow)).

**Tidak disarankan:** melebarkan `<ol>` langkah ke 1100px agar “memenuhi layar” — merusak SC 1.4.8 secara praktik meski bukan kewajiban AA.

### 2.5 Ringkasan low vision

| Aspek | Status mockup | Tindakan |
|-------|---------------|----------|
| Kontras utama | Kuat (navy/putih) | Verifikasi `ink-soft` 4,5:1 |
| Panjang baris | ~OK di 720px | Jangan lebar >80 karakter untuk body |
| Zoom/reflow | Perlu uji implementasi | Stack tombol nav; `max-width` + `rem` |
| Negative space | Masalah UX visual | Sketsa A atau B |
| Fokus keyboard | Baik di beranda | Samakan di bab |

---

## 3. Jalur menjadi app

Konteks Tuturnusa: Laravel 12 + Livewire, tiga fitur, Buku Saku = Markdown + MP3 statis, Ngobrol/Ucapan butuh **HTTPS + mikrofon** ([`getUserMedia` secure context](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia), [MDN Secure contexts](https://developer.mozilla.org/en-US/docs/Web/Security/Defenses/Secure_Contexts)).

### 3.1 Ringkasan per jalur

| Kriteria | PWA (installable) | TWA (Play Store) | Capacitor | Tauri 2 (desktop + mobile) |
|----------|-------------------|------------------|-----------|----------------------------|
| **Basis runtime** | Browser / installed standalone | Chrome Custom Tab fullscreen | WKWebView / Android WebView | WKWebView / WebView2 / webkit2gtk |
| **TalkBack (Android WebView)** | Chrome penuh: sangat baik | Sama (Chrome engine) | WebView sistem: tree ke TalkBack jika host tidak memblokir ([Chromium Android a11y](https://chromium.googlesource.com/chromium/src/+/HEAD/docs/accessibility/browser/android.md)) | WebView Android: sama; risiko wrapper native |
| **VoiceOver (WKWebView)** | Safari: baik | N/A iOS (bukan TWA) | Default: jangan set `isAccessibilityElement` pada webview ([SO WKWebView](https://stackoverflow.com/questions/47324568/how-can-i-enable-voiceover-for-webview-content-on-tableview)) | iOS Tauri: WKWebView; async load kadang desync ([Apple Forums](https://developer.apple.com/forums/tags/accessibility)) |
| **NVDA desktop** | Browser: optimal | N/A | N/A Android | **WebView2:** browse mode / NVDA+Space ([NVDA users guide WebView2](https://groups.google.com/a/nvaccess.org/g/nvda-users/c/Pq9qw56miSQ)); regresi Alt+Tab di beberapa host ([Tauri #15624](https://github.com/tauri-apps/tauri/issues/15624)) |
| **Mikrofon** | `getUserMedia` di PWA terinstall (secure origin) | Sama | Manifest iOS `NSMicrophoneUsageDescription` + Android `RECORD_AUDIO` ([Capacitor permissions](https://capawesome.io/docs/sdks/capacitor/permissions/)); WebView delegasi izin | Sama persyaratan native plist/manifest |
| **Offline MP3 Buku Saku** | SW + cache/IDB; Range 206 untuk `<audio>` ([AudioSilo offline](https://docs.audiosilo.app/developers/frontend/offline)) | Sama web stack | Bisa cache hybrid | File bundled + webview |
| **Offline Ngobrol (AI)** | Tidak realistis penuh | — | — | — |
| **Ukuran unduhan** | Minimal (tidak ada APK besar) | APK kecil + aset web | APK/IPA medium | Desktop ~3–15MB+; mobile lebih besar |
| **Biaya pemeliharaan** | Rendah (satu codebase web) | + assetlinks, signing Play | + native toolchain Xcode/Gradle | + Rust, rilis per store |
| **Distribusi** | URL + install prompt | Google Play | Play / App Store | Microsoft Store / dmg / apk sideload |

### 3.2 PWA (rekomendasi utama)

**Kelebihan:** Satu Blade/Livewire; pembaca layar = pengalaman browser penuh (TalkBack+Chrome, NVDA+Firefox/Chrome); kriteria instalasi: HTTPS, manifest (`name`, ikon 192/512, `start_url`, `display`) ([web.dev install criteria](https://web.dev/articles/install-criteria)); service worker **tidak wajib** untuk menu install sejak Chrome 108/112 ([Chrome blog](https://developer.chrome.com/blog/update-install-criteria)) — tetapi **disarankan** untuk offline Buku Saku.

**Offline audio:** Simpan MP3 di `public/audio/buku-saku/` dengan **CORS**; service worker cache file penuh (bukan 206) atau IndexedDB blob + `URL.createObjectURL` untuk menghindari masalah Range ([own.audio PWA offline](https://www.own.audio/blog/offline-music-in-an-iphone-web-app/)). Pretes “unduh buku untuk offline” per buku (15+12 MP3, beberapa MB).

**Kekurangan:** iOS: notifikasi push terbatas; penyimpanan 7-hari policy Safari — Home Screen app lebih aman ([own.audio](https://www.own.audio/blog/offline-music-in-an-iphone-web-app/)); install UI kurang terlihat tanpa edukasi pengguna.

### 3.3 Trusted Web Activity (Play Store)

**Kelebihan:** Kepercayaan orang tua/sekolah “dari Play Store”; fullscreen Chrome; **aset web tetap satu deployment** ([Chrome TWA](https://developer.chrome.com/docs/android/trusted-web-activity)).

**Syarat:** Digital Asset Links di `/.well-known/assetlinks.json`, SHA-256 dari signing key Play ([TWA quick start](https://developer.chrome.com/docs/android/trusted-web-activity/quick-start)); PWA memenuhi kriteria kualitas ([using PWA in Android app](https://web.dev/articles/using-a-pwa-in-your-android-app)). Crash jika offline tanpa SW menangani navigasi.

**Aksesibilitas:** Setara Chrome Android — **sangat selaras** dengan target TalkBack Tuturnusa.

### 3.4 Capacitor

**Kapan:** Butuh kontrol mikrofon lebih andal (push-to-talk mode suara), interupsi panggilan, atau plugin native (screen reader announce: [Capacitor Screen Reader](https://capawesome.io/docs/sdks/capacitor/screen-reader/)).

**Aksesibilitas:** Inti tetap HTML di WebView — semantik yang sama; tambahan risiko **wrapper** memblokir a11y tree ([EN 301 549 mobile SR](https://a11ybot.app/learn/en-301-549-mobile-screen-reader-interoperability/)).

**Biaya:** Pipeline CI Android/iOS, review App Store untuk `NSMicrophoneUsageDescription` ([MDN getUserMedia](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia)).

### 3.5 Tauri 2

**Kapan:** Instalasi desktop di lab Windows (NVDA) tanpa bergantung browser pengguna; mobile masih matang ([Tauri 2.0](https://v2.tauri.app/blog/tauri-20/), [webview versions](https://v2.tauri.app/reference/webview-versions/)).

**Aksesibilitas:** Desktop NVDA pada WebView2 umumnya bisa dipakai dengan mode browse, tetapi **regresi fokus setelah Alt+Tab** dilaporkan pada wry/WebView2 2025–2026 ([Tauri #15624](https://github.com/tauri-apps/tauri/issues/15624)) — risiko untuk anak yang alt-tab sering.

**Biaya:** Skill Rust/toolchain, build matrix; **tidak** mengganti Laravel backend.

**Posisi:** Opsional jalur desktop sekolah; **bukan** pengganti PWA untuk Android TalkBack.

### 3.6 Rekomendasi jalur produk

1. **Sekarang–medium:** Web + **PWA** (manifest, ikon, SW untuk shell + opsi cache Buku Saku).
2. **Android distribusi formal:** **TWA** mengisi Play Store tanpa fork UI.
3. **Capacitor:** hanya jika uji lapangan menunjukkan WebView mic/izin menjadi blocker.
4. **Tauri 2:** eksplor desktop lab setelah PWA stabil; uji NVDA Alt+Tab eksplisit.

### 3.7 Yang disiapkan SEKARANG di arsitektur Laravel (agar jalur tidak tertutup)

| Area | Langkah konkret |
|------|-----------------|
| **HTTPS** | Produksi wajib TLS; dev `localhost` untuk mic ([secure contexts](https://developer.mozilla.org/en-US/docs/Web/Security/Defenses/Secure_Contexts/features_restricted_to_secure_contexts)). |
| **Aset statis** | MP3 + MD di path prediktif `public/audio/buku-saku/{buku}/{nn}.mp3`; header `Cache-Control` panjang + **CORS** untuk SW ([offline audio](https://abidali671.hashnode.dev/offline-sound-buttons-with-a-service-worker-caching-audio-handling-range-requests-and-staying-inside-storage-limits)). |
| **Manifest & meta** | `public/manifest.webmanifest`, `<link rel="manifest">` di `layouts/study.blade.php`, ikon 192/512 maskable ([MDN installable](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Making_PWAs_installable)). |
| **Service worker** | Terpisah dari Livewire — cache versi hash dari `vite build`; route Laravel **tidak** di-cache; strategi runtime untuk `/audio/buku-saku/*` ([web.dev learn PWA](https://web.dev/learn/pwa/web-app-manifest)). |
| **API boundary** | Ngobrol/Ucapan lewat route JSON yang konsumen bisa panggil dari PWA; hindari logic hanya di wire:click tanpa endpoint ([persiapan hybrid]). |
| **Tanpa auth** | Session cookie tetap same-site; PWA install tidak mengandaikan akun — sesuai §0 desain. |
| **Asset links** | Placeholder route `/.well-known/assetlinks.json` (env-driven) untuk TWA nanti. |
| **Feature detection** | Cek `navigator.mediaDevices` + UI ramah jika bukan secure context. |
| **Semantik HTML** | Keputusan di §1 (landmark, audio, link) **sama** di web/PWA/TWA/Capacitor — WebView membacakan DOM yang ada ([Chromium Android](https://chromium.googlesource.com/chromium/src/+/HEAD/docs/accessibility/browser/android.md)). |

---

## Rekomendasi

Diurutkan dari **dampak terbesar** ke terkecil:

1. **Beri nama aksesibel berbeda** pada kedua tautan “Daftar isi” (atas vs bawah) agar rotor tautan dan memori anak tidak ambigu ([WCAG 2.4.4](https://www.w3.org/WAI/WCAG22/Understanding/link-purpose-in-context)).
2. **Perkuat kotak audio:** `region` + `h2` “Audio narasi” + `aria-labelledby` pada `<audio controls>`; pertahankan kalimat “tidak autoplay” ([RAT media](https://jonneal.dev/rat/media.html)).
3. **Atasi negative space desktop** dengan Sketsa A (panel ringkasan/anchor kanan, 360px) atau Sketsa B (latar pinggir + kartu 720px) **tanpa** melebar melebihi ~75 karakter pada langkah ([Visual Presentation](https://www.w3.org/WAI/WCAG22/Understanding/visual-presentation)).
4. **Plain language untuk “Patokan indra”:** satu kalimat pengantar anak-friendly sebelum kotak kuning.
5. **Uji dan dokumentasikan skip link** sebagai fokus pertama yang terlihat menuju `#konten` / `h1` ([G1](https://www.w3.org/WAI/WCAG22/Techniques/general/G1)).
6. **Siapkan PWA sekarang:** manifest, HTTPS, SW untuk shell + rencana cache MP3 Buku Saku dengan strategi Range/IDB ([install criteria](https://web.dev/articles/install-criteria), [AudioSilo](https://docs.audiosilo.app/developers/frontend/offline)).
7. **Rencanakan TWA** setelah PWA stabil (assetlinks + Bubblewrap) untuk Play Store tanpa duplikasi UI ([Google codelab PWA in Play](https://developers.google.com/codelabs/pwa-in-play)).
8. **Tunda Capacitor/Tauri** sampai ada bukti kegagalan mic WebView atau kebutuhan desktop lab — jika Tauri, buat tes NVDA Alt+Tab wajib ([Tauri #15624](https://github.com/tauri-apps/tauri/issues/15624)).
9. **Stack navigasi bawah** vertikal pada lebar ≤320px dan pastikan reflow 400% zoom ([Reflow](https://www.w3.org/WAI/WCAG22/Understanding/reflow)).
10. **Opsi “Teks lebih besar”** (skala `rem`) tanpa memperlebar kolom 720px — lebih aman untuk low vision daripada kolom 900px.

---

*Diproduksi oleh agent **cursor**. Tidak ada file lain diubah.*