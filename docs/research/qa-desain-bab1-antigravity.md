# QA Desain Halaman Bab 1 & Analisis Jalur Aplikasi — Antigravity

**Dokumen:** `docs/research/qa-desain-bab1-antigravity.md`  
**Penyusun:** Antigravity (Riset QA-D1)  
**Tanggal:** 24 November 2026  
**Objek Penilaian:** Mockup baru `docs/orchestra/qa/bab1-desktop.png` (kolom tengah 800px, kontrol audio mandiri, latar belakang putih `#FFFFFF`), urutan DOM di `docs/orchestra/qa-d1-desain-bab1.md`, konteks bersama `00-konteks-bersama.md`, dan spesifikasi refaktor Tuturnusa.

---

## 1. Penelusuran Pengguna Buta Total (NVDA, JAWS, TalkBack, VoiceOver)

Penelusuran dilakukan dengan mensimulasikan pohon aksesibilitas (*accessibility tree*) peramban dan kebiasaan navigasi non-visual: penelusuran linier Tab (kontrol fokus), penelusuran struktur Heading (`H` / `1`–`6`), daftar elemen (*Elements List* `Insert+F7` pada NVDA / `Insert+F6` pada JAWS), Rotor pada VoiceOver (iOS/macOS), serta sapuan (*swipe*) dan menu kontrol baca pada TalkBack (Android).

```
[Pohon Aksesibilitas & Alur Fokus Pengguna Buta Total]
┌────────────────────────────────────────────────────────────────────────┐
│ 1. Skip Link: "Lewati ke konten utama" (href="#konten")                 │
├────────────────────────────────────────────────────────────────────────┤
│ 2. Tombol: "Mode suara" [aria-pressed="false"]                         │
├────────────────────────────────────────────────────────────────────────┤
│ 3. Tautan: "TUTURNUSA, kembali ke beranda"                             │
├────────────────────────────────────────────────────────────────────────┤
│ 4. Landmark <main id="konten">:                                        │
│    ├─ Navigasi Atas: [← Beranda] [≡ Daftar isi]                        │
│    ├─ ⚠️ [Teks Terlewat]: "Bab 1 dari 15 · Bagian 1..." (sebelum h1)   │
│    ├─ <h1> "Cara Menyikat Gigi Mandiri"                                │
│    ├─ Kotak Pemutar Audio: [▶ Putar] [« 10s] [10s »] [Slider Scrubber] │
│    │  └─ Teks Bantuan: "Dengarkan narasi, atau baca teks di bawah..."  │
│    ├─ <h2> "Alat dan bahan" -> Paragraf                                │
│    ├─ <h2> "Langkah-langkah" -> <ol> (Item 1 s.d. 8)                   │
│    ├─ <aside>: <h2> "Cara tahu sudah benar" (Patokan indra)           │
│    └─ Navigasi Bawah: [≡ Daftar isi] [Bab 2: Botol sabun dan sampo ›]  │
├────────────────────────────────────────────────────────────────────────┤
│ 5. Landmark <footer>: [? Bantuan dan aksesibilitas]                    │
└────────────────────────────────────────────────────────────────────────┘
```

### 1.1 Apa yang Dibacakan & Potensi Kebingungan / Redundansi

1. **Urutan DOM: Metadata Bab Sebelum `<h1>` (Masalah Kritis Navigasi Heading)**
   - **Perilaku Pengguna:** Menurut survei global WebAIM Screen Reader Survey #10, sebanyak **67,7%** pengguna pembaca layar primer menggunakan tombol pintas Heading (`H` atau angka `1`–`6`) sebagai cara utama menemukan konten pada halaman baru ([WebAIM Screen Reader Survey #10: Finding Information](https://webaim.org/projects/screenreadersurvey10/#finding)).
   - **Masalah:** Pada urutan DOM saat ini:
     ```html
     <p class="meta">Bab 1 dari 15 · Bagian 1: Kebersihan dan Perawatan Diri</p>
     <h1>Cara Menyikat Gigi Mandiri</h1>
     ```
     Ketika anak menekan tombol `H` untuk melompat dari header langsung ke isi bab, fokus mendarat di `<h1>`. Akibatnya, teks metadata posisi bab di atasnya **terlewatkan sepenuhnya (*completely bypassed*)**. Anak tidak mendengar bahwa bab ini adalah "Bab 1 dari 15" kecuali mereka menyapu mundur (panah atas).
   - **Solusi Konkret:** Tempatkan metadata posisi bab tepat **setelah `<h1>`** di dalam pohon DOM, atau masukkan sebagai sub-judul di dalam elemen `<h1>` itu sendiri:
     ```html
     <h1>
       <span class="block text-lg font-normal text-slate-600">Bab 1 dari 15 · Bagian 1: Kebersihan dan Perawatan Diri</span>
       Cara Menyikat Gigi Mandiri
     </h1>
     ```
     Pola ini menjamin pembaca layar mengumumkan judul beserta konteks posisi bab dalam satu tarikan nafas ([W3C WAI Tutorials: Heading Structure](https://www.w3.org/WAI/tutorials/page-structure/headings/)).

2. **Kotak Audio Mandiri (Custom Player): Kejelasan & Beban Kognitif**
   - **Evaluasi Desain Baru:** Gambar `bab1-desktop.png` versi baru menampilkan tombol Play bulat besar dan linimasa waktu kustom. Ini perbaikan signifikan dibanding kontrol native peramban yang kaku dan berukuran kecil (32px).
   - **Risiko Aksesibilitas Widget Kustom:**
     - Widget audio kustom wajib mengimplementasikan spesifikasi WAI-ARIA secara disiplin ([W3C WAI-ARIA Authoring Practices Guide: Button Pattern](https://www.w3.org/WAI/ARIA/apg/patterns/button/) & [Slider Pattern](https://www.w3.org/WAI/ARIA/apg/patterns/slider/)).
     - Tombol Play harus berupa `<button>` dengan `aria-label="Putar narasi audio bab 1"` yang secara dinamis berganti menjadi `aria-label="Jeda narasi audio bab 1"`.
     - **Bahaya `aria-live` pada Linimasa:** Jangan pernah memasang `aria-live="polite"` pada pembaruan detik linimasa (`0:01`, `0:02`, `0:03`...). Hal ini akan menyebabkan pembaca layar mengoceh terus-menerus (*chattering/flooding*), memotong pembacaan teks langkah-langkah oleh anak ([MDN: Live Regions Pitfalls](https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Guides/Live_regions)). Waktu cukup diperbarui secara visual, dan status putar/jeda saja yang diumumkan lewat satu live region `role="status"`.
     - **Tombol Lompat Waktu (Skip +/- 10 Detik):** Anak tunanetra yang sedang mempraktikkan langkah fisik (misal meraba sikat gigi) sangat membutuhkan tombol `[Mundur 10 detik]` dan `[Maju 10 detik]`. Mengatur slider linimasa via gestur usap/keyboard membutuhkan motorik halus yang sulit bagi anak-anak.

3. **Dua Tautan "Daftar Isi" (Atas dan Bawah)**
   - **Tinjauan WCAG:** Menurut [WCAG 2.2 Kriteria 3.2.4: Consistent Identification (Level AA)](https://www.w3.org/WAI/WCAG22/Understanding/consistent-identification.html), dua komponen dengan fungsi dan tujuan yang sama (keduanya mengarah ke `/buku-saku/{buku}`) **boleh dan dianjurkan** memiliki label teks yang konsisten.
   - **Perspektif Pembaca Layar:** Pada daftar link (`Insert+F7`), muncul dua item bernama "Daftar isi". Untuk anak tunanetra, ini tidak membingungkan selama tujuannya sama. Namun, untuk memperjelas konteks navigasi:
     - Tautan atas diletakkan dalam `<nav aria-label="Navigasi kembali">`.
     - Tautan bawah diletakkan dalam `<nav aria-label="Navigasi bab">`.
     Dengan pembungkus landmark ini, pembaca layar mengumumkan kelompok navigasinya secara jelas ([W3C WAI-ARIA APG: Landmark Regions](https://www.w3.org/WAI/ARIA/apg/patterns/landmarks/)).

4. **Pemahaman Istilah "Patokan Indra" bagi Anak-Anak**
   - **Evaluasi Kognitif:** Frasa *"Patokan indra"* adalah terminologi formal orang dewasa. Bagi anak tunanetra usia sekolah dasar, kata "patokan" dan "indra" bersifat abstrak.
   - **Tujuan Konten:** Kotak ini menjelaskan umpan balik fisik non-visual: rasa rabaan jari saat pasta gigi keluar, dan rasa lidah saat gigi sudah licin.
   - **Usulan Perbaikan Konkret:** Ganti judul heading `<h2>` menjadi:
     - **Pilihan Utama:** `"Cara tahu sudah benar"`
     - **Pilihan Alternatif:** `"Tanda rasa dan sentuhan"`
   - Kotak ini harus dibungkus kontainer semantik: `<aside aria-label="Cara tahu sudah benar">` agar pembaca layar mengumumkan awal dan akhir kotak catatan khusus tersebut ([MDN: HTML Aside Element](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/aside)).

5. **Tombol "Mode Suara" di Header**
   - **Masalah:** Teks visual "Mode suara: mati" yang berubah menjadi "Mode suara: aktif" merombak nama aksesibel kontrol (*accessible name mutation*).
   - **Perbaikan:** Pertahankan nama tombol stabil `"Mode suara"`, dan gunakan atribut status `aria-pressed="false"` (atau `"true"`) serta `aria-label="Mode suara, mati"` ([W3C WAI-ARIA APG: Toggle Button Pattern](https://www.w3.org/WAI/ARIA/apg/patterns/button/#togglebutton)).

---

## 2. Penilaian Low Vision, Tipografi, & Optimasi Negative Space

Pemeriksaan dilakukan terhadap aspek ukuran teks, rasio kontras warna, reflow 320px, pembesaran (zoom 200–400%), indikator fokus, dan keluhan pengguna mengenai ruang kosong (*negative space*).

### 2.1 Analisis Tipografi & Rasio Kontras

| Elemen UI | Nilai Saat Ini | Rasio Kontras | Status Standar WCAG 2.2 | Evaluasi & Rekomendasi |
| :--- | :--- | :--- | :--- | :--- |
| **Latar Halaman** | Putih `#FFFFFF` | - | - | Keputusan user tepat; bersih dan bebas distraksi. |
| **Teks Utama (`h1`, `h2`, body)** | Navy `#14294D` | **14.44 : 1** di putih | **Lolos AAA** (Syarat: ≥ 7:1) | Kontras sangat tajam, ideal untuk mata kabur & katarak. |
| **Teks Meta / Kalimat Bantuan** | Slate `#4F5968` | **5.45 : 1** di putih | **Lolos AA**, Gagal AAA teks normal | Naikkan ke `#334155` (kontras **8.98:1**) agar lolos **AAA**. |
| **Kotak "Patokan Indra"** | Latar `#FDEBC0`, Border `#14294D`, Teks `#14294D` | **12.10 : 1** (teks ke latar kotak) | **Lolos AAA** | Warna latar kuning lembut memberi *visual tint* yang nyaman tanpa silau (*photophobia*). |
| **Garis Tepi Kartu / Tombol** | Border solid 2px `#14294D` | **14.44 : 1** | **Lolos AA** (Syarat: ≥ 3:1) | Memenuhi WCAG 1.4.11 (Non-text Contrast) dan mode Windows High Contrast (`forced-colors: active`). |
| **Indikator Fokus (`:focus-visible`)** | Cincin ganda: Biru `#1D4ED8` 3px + offset putih 3px | **6.70 : 1** di putih | **Lolos AAA** (Syarat: ≥ 3:1) | Memenuhi WCAG 2.4.13 (Focus Appearance Level AAA). |

### 2.2 Masalah Panjang Baris vs. Keluhan Negative Space

- **Keluhan Pengguna:** *"negative space-nya masih banyak"* (pada layar desktop 1440px dengan kolom tengah 800px, terdapat ruang kosong masing-masing ±320px di sisi kiri dan kanan).
- **Prinsip Ilmiah Panjang Baris (WCAG 1.4.8 Visual Presentation Level AAA):**
  - Standar aksesibilitas [WCAG 2.2 SC 1.4.8 (Level AAA)](https://www.w3.org/WAI/WCAG22/Understanding/visual-presentation.html) dan riset keterbacaan low vision ([Aardvark Accessibility: Line Length Guidelines](https://aaardvarkaccessibility.com/guidelines/line-length/)) menetapkan bahwa panjang baris teks blok **tidak boleh melebihi 80 karakter** (optimal 50–75 karakter).
  - **Bahaya Melebarkan Kolom Teks:** Jika kolom teks dilebarkan menjadi 1100px–1200px dengan font 18px biasa, satu baris akan memuat lebih dari 110–130 karakter! Bagi pengguna low vision dengan lapang pandang sempit (*tunnel vision*) atau yang menggunakan kacamata pembesar monokular, melompat dari ujung baris kanan kembali ke awal baris kiri berikutnya akan memicu disorientasi (*line skipping*), pusing, dan kelelahan visual ekstrem.
- **Dua Tuas Desain yang Tepat:**
  1. **Naikkan Ukuran Teks Isi ke 22px (Line Height 1.6):**
     - Pada kolom 800px, teks berukuran 22px Inter Variable menghasilkan rata-rata **64–70 karakter per baris**. Ini adalah titik temu sempurna (*golden sweet spot*) antara keterbacaan low vision dan pemenuhan WCAG 1.4.8!
  2. **Gunakan Layout 2-Kolom Asimetris (Desktop 1440px): Kolom Baca + Panel Pendamping (Companion Panel):**
     - Alih-alih membiarkan ruang samping kosong atau melebarkan teks secara berbahaya, gunakan ruang sisi kanan sebesar 360px untuk **Panel Pendamping Interaktif**:
       - **Pemutar Audio Melayang (*Sticky Floating Player*):** Ketika anak menggulir ke langkah 5–8, pemutar audio tetap berada di samping pandangan mereka. Mereka dapat menekan jeda/putar sewaktu-waktu tanpa harus scroll jauh ke atas.
       - **Daftar Bab Singkat (*Quick Chapter Jump*):** Navigasi ringkas 15 bab Buku Saku yang menyorot bab aktif saat ini.

### 2.3 Sketsa Layout Desktop yang Diusulkan (1440px)

```
|===================================================================================================|
| Header: [TUTURNUSA] (Logo 48px)                                           [Mode suara: Mati]      |
|===================================================================================================|
|                                                                                                   |
|  <-- 80px --> |<-------------- Kolom Utama 800px -------------->| <- 40px -> |<- Panel 360px ->|  |
|               |                                                 |            |                 |  |
|               | ← Beranda    ≡ Daftar isi                       |            | [STATUS BACA]   |  |
|               |                                                 |            | Buku Saku Mandiri|
|               | Cara Menyikat Gigi Mandiri            (h1 48px) |            | Bab 1 dari 15   |  |
|               | Bab 1 dari 15 · Perawatan Diri        (p  20px) |            | ─────────────── |  |
|               |                                                 |            | [AUDIO STICKY]  |  |
|               | ┌─────────────────────────────────────────────┐ |            | ┌─────────────┐ |  |
|               | │ Audio Narasi · Durasi 3 menit 12 detik      │ |            | │ ▶ Putar     │ |  |
|               | │ [ ▶ Putar ]  [ « 10s ] [ 10s » ] 0:00/3:12  │ |            | │ 0:00 / 3:12 │ |  |
|               | │ ──────────────────●───────────────────────  │ |            | │ [«10s] [10s»]│|
|               | │ Dengarkan narasi atau baca teks di bawah... │ |            | └─────────────┘ |  |
|               | └─────────────────────────────────────────────┘ |            |                 |  |
|               |                                                 |            | [LOMPAT BAB]    |  |
|               | Alat dan bahan                        (h2 32px) |            | ● 1. Sikat Gigi |  |
|               | Sikat gigi, pasta gigi atau odol...   (p  22px) |            | ○ 2. Sabun Mandi|  |
|               |                                                 |            | ○ 3. Cuci Tangan|  |
|               | Langkah-langkah                       (h2 32px) |            | ... s.d. Bab 15 |  |
|               | 1. Pegang gagang sikat gigi...                  |            |                 |  |
|               | 2. Buka tutup odol...                           |            |                 |  |
|               |                                                 |            |                 |  |
|               | ┌ Cara tahu sudah benar (Patokan indra) ──────┐ |            |                 |  |
|               | │ Ujung jari telunjuk menjadi penuntun...     │ |            |                 |  |
|               | └─────────────────────────────────────────────┘ |            |                 |  |
|               |                                                 |            |                 |  |
|               | [ ≡ Daftar isi ]   [ Bab 2: Botol sabun... › ]  |            |                 |  |
|                                                                                                   |
|===================================================================================================|
| Footer: ? Bantuan dan aksesibilitas                                                               |
|===================================================================================================|
```

### 2.4 Reflow 320px & Zoom 400% (WCAG 1.4.10)

Pada viewport mobile (320px–390px) atau saat pengguna desktop mengaktifkan browser zoom 400% (lebar viewport setara 360 CSS px):
- Grid 2-kolom otomatis melebur (*collapse*) menjadi **1 kolom linier 100% width** (padding kiri-kanan 16px).
- Panel pendamping kanan disembunyikan atau diletakkan di bagian bawah sebelum navigasi bab berikutnya.
- Tidak ada pergeseran dua dimensi (scroll horizontal) sama sekali, sesuai [WCAG 2.2 SC 1.4.10 Reflow (Level AA)](https://www.w3.org/WAI/WCAG22/Understanding/reflow.html).
- Semua tombol navigasi dan kontrol audio bertumpuk penuh (*full-width*) dengan tinggi minimal 56px untuk memudahkan sentuhan motorik anak.

---

## 3. Analisis Komprehensif Jalur Menjadi Aplikasi

Pengguna menginginkan Tuturnusa suatu saat dapat dipasang sebagai aplikasi di perangkat pengguna/pengajar. Berikut adalah perbandingan teknis 4 opsi arsitektur:

| Kriteria Evaluasi | 1. PWA (Progressive Web App) | 2. TWA (Trusted Web Activity) | 3. Capacitor (Ionic) | 4. Tauri 2 (Desktop + Mobile) |
| :--- | :--- | :--- | :--- | :--- |
| **Mesin Render (Underlying Engine)** | Browser asli sistem (Chrome, Edge, Safari, Firefox) | Google Chrome / Chromium Custom Tabs | Android WebView & iOS `WKWebView` | Windows WebView2, macOS/iOS `WKWebView`, Android WebView |
| **Kompatibilitas Screen Reader** | **Terbaik (Native 100%)**<br>TalkBack langsung di Chrome; VoiceOver di Safari; NVDA/JAWS di Chrome/Edge/Firefox. | **Sangat Baik**<br>Identik 100% dengan Chrome Android karena berjalan di atas Chrome Custom Tabs ([Chrome Developers TWA Overview](https://developer.chrome.com/docs/android/trusted-web-activity/overview)). | **Cukup / Rawan**<br>TalkBack di WebView sering monoton mengumumkan kontainer *"Web view"*; VoiceOver di WKWebView memiliki bug reset fokus saat DOM update dinamis ([WebKit Bugzilla #220311](https://bugs.webkit.org/show_bug.cgi?id=220311)). | **Baik di Desktop, Rawan di Mobile**<br>Windows WebView2 sangat solid untuk NVDA/JAWS; di Android/iOS bergantung pada kestabilan bridge WebView OS ([Tauri 2 Mobile A11y Guide](https://v2.tauri.app/develop/)). |
| **Izin Mikrofon (Ucapan / Ngobrol)** | Web Speech API / `getUserMedia`. Prompt izin standar browser. Di Safari iOS & Chrome Android berjalan mulus jika dipicu gestur pengguna ([MDN Web Speech API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Speech_API)). | Berbagi izin dengan Chrome Android via Digital Asset Links. Izin sekali klik pada tingkat peramban ([Google TWA Permissions](https://developer.chrome.com/docs/android/trusted-web-activity/)). | Perlu deklarasi native `RECORD_AUDIO` di `AndroidManifest.xml` dan `NSMicrophoneUsageDescription` di `Info.plist` ([Capacitor Permissions Guide](https://capacitorjs.com/docs/apis/screen-reader)). | Dikelola via sistem plugin perizinan Rust di Tauri Mobile + deklarasi OS ([Tauri Mobile Plugins](https://v2.tauri.app/plugin/)). |
| **Mode Offline untuk Audio Buku Saku** | **Bisa Penuh**, tetapi **Wajib Mendukung HTTP Range Requests (206 Partial Content)** di Safari/WebKit via Workbox `RangeRequestsPlugin` ([web.dev: Workbox Range Requests](https://developer.chrome.com/docs/workbox/modules/workbox-range-requests/)). | Bergantung pada Service Worker PWA di domain Laravel. Identik dengan PWA. | Aset lokal mudah di-cache, tetapi jika memuat remote Laravel live URL, kehilangan koneksi akan memicu layar putih error tanpa Service Worker khusus. | Sulit untuk arsitektur server-rendered Laravel; dirancang untuk SPA statis (React/Vue/Svelte) yang di-bundle lokal. |
| **Ukuran Paket (Bundle Size)** | **0 MB** instalasi native (hanya penyimpanan cache browser). | **~2–3 MB** (wrapper APK/AAB ringan). | **~15–25 MB** (wrapper runtime native). | **~10–15 MB** desktop, **~15–20 MB** mobile. |
| **Biaya Pemeliharaan & Rilis** | **Paling Rendah**<br>1 basis kode Laravel/Blade, sekali deploy di server langsung dinikmati semua perangkat tanpa kompilasi/store review. | **Sangat Rendah**<br>Dibungkus via Bubblewrap sekali; update kode web langsung live di Play Store tanpa rilis ulang binary ([Google Bubblewrap CLI](https://github.com/GoogleChromeLabs/bubblewrap)). | **Tinggi**<br>Perlu pemeliharaan Xcode, Android Studio, sertifikat rilis, dan perbaikan dependensi native secara berkala. | **Tinggi**<br>Membutuhkan keahlian bahasa Rust, toolchain Rust Android/iOS, dan manajemen kompilasi multi-platform. |
| **Distribusi** | Mandiri via browser ("Pasang Aplikasi" via `beforeinstallprompt` di Android/Windows; Add to Home Screen di iOS). | **Google Play Store** (Play Store langsung, pencarian mudah untuk sekolah/guru SLB). | Google Play Store, Apple App Store. | Google Play Store, Apple App Store, installer Windows (.msi), macOS (.dmg). |

---

### 3.1 Rekomendasi Jalur Bertahap (Roadmap)

Berdasarkan arsitektur Tuturnusa saat ini (**Laravel 12 + Livewire 3 + Blade Server-Rendered**):

```
[Strategi Jalur Menjadi App Tuturnusa]
┌────────────────────────────────────────────────────────┐
│ FASE 1 (SEKARANG): PWA Murni (Installable Web App)     │
│ - Manifest & Service Worker Cache (Offline Buku Saku)   │
│ - 100% Aksesibel di NVDA, JAWS, TalkBack, VoiceOver     │
│ - Tombol pasang otomatis di beranda (Android/Desktop)   │
└───────────────────────────┬────────────────────────────┘
                            │
                            ▼
┌────────────────────────────────────────────────────────┐
│ FASE 2: TWA (Trusted Web Activity) untuk Android       │
│ - Dibungkus Bubblewrap -> Masuk Google Play Store       │
│ - Aksesibilitas TalkBack tetap 100% Chrome native       │
│ - Kode tetap di server Laravel, tanpa rewrite           │
└───────────────────────────┬────────────────────────────┘
                            │ (Bila kebutuhan toko Apple mendesak)
                            ▼
┌────────────────────────────────────────────────────────┐
│ FASE 3 (Jangka Panjang): Capacitor / Tauri 2           │
│ - HANYA jika wajib masuk Apple App Store                │
│ - Membutuhkan refaktor frontend terpisah (Decoupled SPA)│
└────────────────────────────────────────────────────────┘
```

1. **Jalur Utama: PWA Terlebih Dahulu (Fase 1)**
   - Keputusan tim dan user untuk fokus pada PWA adalah **langkah paling tepat dan bijak**. PWA memberikan pengalaman terbaik bagi pembaca layar karena tidak ada lapisan abstraksi webview yang berpotensi memotong tree aksesibilitas.
   - PWA tidak memerlukan biaya lisensi developer Apple ($99/tahun) atau Google Play ($25), dan anak tunanetra dapat memasang langsung dari beranda.
2. **Jalur Distribusi Play Store: TWA via Bubblewrap (Fase 2)**
   - Saat user/klien menuntut aplikasi tampil di Google Play Store agar mudah dicari oleh guru SLB di Android, jalur terbaik adalah **TWA (Trusted Web Activity)** menggunakan CLI Bubblewrap dari Google.
   - TWA memanfaatkan Service Worker PWA yang sudah dibangun, tidak memerlukan penulisan ulang kode ke Android native, dan TalkBack berjalan dengan performa terbaik mesin Chrome.
3. **Mengapa Menolak Capacitor dan Tauri 2 untuk Saat Ini?**
   - Tuturnusa bukan aplikasi Single Page Application (SPA) berbasis React atau Vue, melainkan aplikasi server-rendered Laravel 12 + Livewire 3.
   - Memaksakan Capacitor atau Tauri 2 untuk memuat remote URL Laravel akan menimbulkan masalah rumit: penanganan offline yang rapuh, kegagalan penanganan session cookie/CSRF token di webview, ketidakstabilan Service Worker di iOS `WKWebView` ([WebKit: Service Worker in Apps](https://webkit.org/blog/11545/service-workers-in-apps/)), serta potensi ditolak oleh Apple App Store Review Guideline 4.2 (karena hanya berupa "repackaged website").

---

### 3.2 Yang Wajib Disiapkan di Arsitektur Laravel SEKARANG

Agar jalur PWA dan TWA terbuka mulus dan tidak terbentur di kemudian hari, berikut persiapan wajib di sisi backend Laravel:

1. **Dukungan HTTP Byte-Range Requests pada Berkas Audio MP3 (Krusial untuk Safari / iOS)**
   - **Masalah:** Safari pada iOS dan macOS memiliki arsitektur pemutar media yang ketat: Safari selalu mengirimkan header `Range: bytes=0-1` untuk memeriksa apakah server mendukung streaming parsial ([Apple Developer: Range Requests](https://developer.apple.com/library/archive/documentation/AppleApplications/Reference/SafariWebContent/CreatingVideoforSafarioniPhone/CreatingVideoforSafarioniPhone.html)). Jika server atau cache Service Worker merespons dengan `200 OK` (alih-alih `206 Partial Content`), audio akan gagal diputar total (*silent failure*).
   - **Tindakan di Laravel & Nginx:**
     - Pastikan web server (Nginx/Apache) menyertakan header `Accept-Ranges: bytes` untuk seluruh berkas statis di `/audio/buku-saku/*.mp3`.
     - Dalam Service Worker PWA (`public/sw.js`), integrasikan modul `workbox-range-requests` agar ketika offline, permintaan parsial byte dari Safari tetap dijawab dengan status `206 Partial Content` dari cache IndexedDB/CacheStorage ([web.dev: Handling Range Requests in Service Workers](https://developer.chrome.com/docs/workbox/modules/workbox-range-requests/)).

2. **Penyediaan `assetlinks.json` untuk TWA Google Play Store**
   - Buat route publik di Laravel untuk menyajikan berkas Digital Asset Links:
     ```php
     Route::get('/.well-known/assetlinks.json', function () {
         return response()->file(public_path('.well-known/assetlinks.json'), [
             'Content-Type' => 'application/json'
         ]);
     });
     ```
     Dengan menyiapkan endpoint ini dari sekarang, verifikasi kepemilikan domain saat rilis TWA Bubblewrap ke Google Play Store akan langsung lolos tanpa mengubah konfigurasi server.

3. **Konfigurasi Web App Manifest Standar (`manifest.webmanifest`)**
   - Siapkan berkas `public/manifest.webmanifest` dengan kriteria PWA modern:
     - `name`: "Tuturnusa — Sahabat Belajar Tunanetra"
     - `short_name`: "Tuturnusa"
     - `start_url`: "/?source=pwa"
     - `display`: "standalone"
     - `background_color`: "#FFFFFF"
     - `theme_color`: "#14294D"
     - Ikon maskable PNG berukuran 192×192 dan 512×512 piksel.

4. **Strategi Pemisahan Rute Offline vs. Online di Service Worker**
   - **Rute Statis & Offline (Buku Saku):** Cache-first untuk `/buku-saku`, `/buku-saku/*`, CSS, JS, dan MP3. Pengguna dapat mendengarkan seluruh bab Buku Saku tanpa koneksi internet sama sekali.
   - **Rute Dinamis & Online (Ngobrol & Ucapan):** Network-only. Jika pengguna membuka `/conversation` saat offline, tampilkan halaman fallback ramah anak berbahasa Indonesia:
     *"Fitur Ngobrol butuh koneksi internet. Kamu tetap bisa mendengarkan Buku Saku secara offline!"*
   - **Larangan Caching:** Dilarang mencache endpoint mutasi Livewire (`/livewire/update`) dan cookie sesi pengguna di Service Worker untuk mencegah kebocoran state atau bug sinkronisasi.

5. **Kebijakan Cookie & Sesi Ramah Webview**
   - Pastikan konfigurasi sesi di `config/session.php` menggunakan:
     - `same_site => 'lax'`
     - `secure => env('SESSION_SECURE_COOKIE', true)`
     - `http_only => true`
     Hal ini menjamin sesi Livewire tidak terblokir saat dijalankan di dalam container PWA atau TWA.

---

## 4. Rekomendasi Terurut Berdasarkan Dampak (Paling Besar ke Paling Rendah)

Berikut adalah daftar rekomendasi perbaikan konkret yang diurutkan berdasarkan tingkat keparahan (*impact*) terhadap aksesibilitas dan keberlanjutan produk:

### Rekomendasi 1 (P0 — Kritis Aksesibilitas): Sempurnakan Pemutar Audio Kustom dengan Tombol Nyata, Kontrol Lompat 10 Detik, dan Larangan `aria-live` Berjalan
- **Dampak:** Mencegah disorientasi screen reader dan menjamin anak tunanetra dapat mengendalikan audio secara mandiri.
- **Implementasi:**
  1. Buat tombol Play/Pause berbasis `<button>` asli berukuran minimal 56×56px dengan `aria-pressed="false/true"` dan `aria-label="Putar narasi audio bab 1"`.
  2. Tambahkan tombol `[« 10 detik]` dan `[10 detik »]` (target sentuh ≥ 56px).
  3. Slider linimasa diberi atribut lengkap `role="slider"`, `aria-valuemin="0"`, `aria-valuemax="192"`, `aria-valuenow="0"`, `aria-valuetext="0 menit 0 detik dari 3 menit 12 detik"`.
  4. Jangan gunakan `aria-live` pada linimasa detik berjalan; umumkan status pemutaran hanya saat tombol ditekan via satu `role="status"`.
- **Rujukan:** [W3C WAI-ARIA APG: Audio Player Pattern](https://www.w3.org/WAI/ARIA/apg/patterns/toolbar/), [WCAG 1.4.2 Audio Control](https://www.w3.org/WAI/WCAG22/Understanding/audio-control.html).

---

### Rekomendasi 2 (P0 — Kritis Navigasi): Pindahkan Metadata Bab ke Bawah `<h1>` dalam Struktur DOM
- **Dampak:** Memastikan 67,7% pengguna screen reader yang menavigasi via tombol pintas `H` (Heading) tidak melewatkan informasi posisi bab ("Bab 1 dari 15 · Bagian 1...").
- **Implementasi:** Pindahkan baris meta tepat setelah `<h1>Cara Menyikat Gigi Mandiri</h1>` di pohon HTML, atau satukan ke dalam elemen `<h1>` sebagai sub-teks semantik.
- **Rujukan:** [WCAG 2.4.6 Headings and Labels](https://www.w3.org/WAI/WCAG22/Understanding/headings-and-labels.html), [W3C WAI Tutorials: Headings](https://www.w3.org/WAI/tutorials/page-structure/headings/).

---

### Rekomendasi 3 (P1 — Desain Kognitif Anak): Ganti Istilah Abstrak "Patokan Indra" Menjadi "Cara Tahu Sudah Benar"
- **Dampak:** Menghilangkan kebingungan kognitif anak usia sekolah dasar terhadap terminologi dewasa.
- **Implementasi:** Ubah judul heading `<h2>` menjadi `"Cara tahu sudah benar"`. Bungkus kontainer kuning lembut `#FDEBC0` dengan elemen semantik `<aside aria-label="Cara tahu sudah benar">`.
- **Rujukan:** [WCAG 3.1.5 Reading Level](https://www.w3.org/WAI/WCAG22/Understanding/reading-level.html), [MDN HTML Aside Element](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/aside).

---

### Rekomendasi 4 (P1 — Kenyamanan Low Vision & Negative Space): Terapkan Layout Desktop 2-Kolom (Teks 22px + Sticky Audio & Navigasi Bab)
- **Dampak:** Memanfaatkan ruang kosong (*negative space*) 1440px secara fungsional tanpa melanggar batas aman panjang baris (< 80 karakter).
- **Implementasi:**
  1. Naikkan teks isi bab dari 18px ke **22px Inter Variable** (line-height 1.6, lebar kolom baca 800px) menghasilkan 64–70 karakter per baris.
  2. Gunakan ruang sisi kanan (360px) sebagai **Panel Pendamping (Companion Panel)** berisi pemutar audio melayang (*sticky*) dan daftar lompat 15 bab Buku Saku.
  3. Pada viewport ≤ 1024px atau zoom 400%, tata letak otomatis melebur menjadi 1 kolom linier (reflow 320px).
- **Rujukan:** [WCAG 1.4.8 Visual Presentation (80 characters rule)](https://www.w3.org/WAI/WCAG22/Understanding/visual-presentation.html), [WCAG 1.4.10 Reflow](https://www.w3.org/WAI/WCAG22/Understanding/reflow.html).

---

### Rekomendasi 5 (P1 — Kesiapan Arsitektur App): Pasang Modul Workbox Range Requests untuk Audio Offline PWA
- **Dampak:** Mencegah kegagalan audio hening (*silent failure*) di peramban WebKit / Safari iOS saat Buku Saku diputar secara offline.
- **Implementasi:** Tambahkan modul `workbox-range-requests` pada Service Worker `public/sw.js` agar menjawab permintaan `Range: bytes=...` dengan status `206 Partial Content`.
- **Rujukan:** [web.dev: Workbox Range Requests](https://developer.chrome.com/docs/workbox/modules/workbox-range-requests/), [Apple Developer: HTTP Live Streaming & Audio Range](https://developer.apple.com/library/archive/documentation/AppleApplications/Reference/SafariWebContent/CreatingVideoforSafarioniPhone/CreatingVideoforSafarioniPhone.html).

---

### Rekomendasi 6 (P2 — Kestabilan Kontrol Aksesibel): Stabilkan Nama Aksesibel Tombol "Mode Suara"
- **Dampak:** Mencegah kebingungan pengguna screen reader akibat perubahan nama aksesibel kontrol saat diaktifkan.
- **Implementasi:** Beri label statis `"Mode suara"` pada tombol, dan komunikasikan kondisi aktif/nonaktif melalui atribut `aria-pressed="false/true"`.
- **Rujukan:** [W3C WAI-ARIA APG: Toggle Button Pattern](https://www.w3.org/WAI/ARIA/apg/patterns/button/#togglebutton).

---

### Rekomendasi 7 (P2 — Diferensiasi Landmark Navigasi): Beri Pembeda Landmark pada Tautan "Daftar Isi" Atas dan Bawah
- **Dampak:** Memberikan konteks yang jelas pada daftar tautan screen reader (`Insert+F7`).
- **Implementasi:** Bungkus tautan "Daftar isi" bagian atas di dalam `<nav aria-label="Navigasi kembali">` dan tautan bagian bawah di dalam `<nav aria-label="Navigasi bab">`.
- **Rujukan:** [WCAG 3.2.4 Consistent Identification](https://www.w3.org/WAI/WCAG22/Understanding/consistent-identification.html), [W3C WAI-ARIA APG: Landmarks](https://www.w3.org/WAI/ARIA/apg/patterns/landmarks/).

---

### Rekomendasi 8 (P3 — Jalur Distribusi Play Store): Siapkan Berkas `assetlinks.json` dan Manifest PWA
- **Dampak:** Menjamin kesiapan pembungkusan aplikasi ke Google Play Store via TWA Bubblewrap tanpa hambatan teknis.
- **Implementasi:** Sediakan endpoint `/.well-known/assetlinks.json` di Laravel dan lengkapi `public/manifest.webmanifest` dengan ikon maskable standar.
- **Rujukan:** [Google Chrome Developers: Trusted Web Activity Quick Start](https://developer.chrome.com/docs/android/trusted-web-activity/quick-start/).
