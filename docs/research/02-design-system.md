# Riset Design System Tuturnusa: Aksesibilitas Tunanetra, Low Vision, dan Anak

**Tanggal:** 24 November 2026  
**Penyusun:** Antigravity (Riset R2)  
**Cakupan:** Aplikasi web Tuturnusa (Laravel 12 + Livewire 3 + Tailwind CSS), dirampingkan khusus untuk anak-anak tunanetra (buta total dan low vision).  
**Fitur Utama:** Tiga kartu besar di beranda (Ngobrol, Ucapan, Buku Saku) dan halaman bacaan Buku Saku (HTML semantik + pemutar audio MP3 hasil pra-generasi `edge-tts`).  
**Prinsip Desain:** Tanpa landing page promosi, tanpa registrasi/login, beranda langsung dapat diakses, audio tanpa pemutaran otomatis (*no autoplay*), tata letak bersih dan bebas distraksi.

---

## 1. Prinsip Design System untuk Pengguna Buta Total dan Low Vision

Perancangan antarmuka untuk penyandang disabilitas penglihatan menuntut pemisahan kebutuhan yang tegas namun harmonis antara **pengguna buta total** (yang menavigasi secara non-visual via pembaca layar / *screen reader*) dan **pengguna low vision** (yang mengandalkan sisa penglihatan dengan pembesaran visual, kontras tinggi, dan tata letak terstruktur).

```
                      ┌─────────────────────────────────────────┐
                      │            PENGGUNA SASARAN             │
                      └────────────────────┬────────────────────┘
                                           │
                 ┌─────────────────────────┴─────────────────────────┐
                 ▼                                                   ▼
   ┌───────────────────────────┐                       ┌───────────────────────────┐
   │    PENGGUNA BUTA TOTAL    │                       │    PENGGUNA LOW VISION    │
   ├───────────────────────────┤                       ├───────────────────────────┤
   │ • Pohon Aksesibilitas DOM │                       │ • Kontras Tinggi (AAA/AA) │
   │ • Landmark Semantik       │                       │ • Reflow 320px & Zoom 400%│
   │ • Hirarki Heading Logis   │                       │ • Indikator Fokus Tebal   │
   │ • Urutan Fokus Tab Linier │                       │ • Target Sentuh ≥ 44-56px │
   │ • Nama Aksesibel Utuh     │                       │ • Mode `forced-colors`    │
   └───────────────────────────┘                       └───────────────────────────┘
```

---

### 1.1 Prinsip untuk Pengguna Buta Total (Screen Reader Users)

Pengguna buta total mengonsumsi antarmuka melalui perantara suara atau layar braille yang membaca **pohon aksesibilitas (*accessibility tree*)** peramban. Informasi tata letak visual dua dimensi diratakan menjadi aliran informasi satu dimensi yang linier.

#### A. Struktur Semantik dan Landmark HTML5
- **Landmark Bersekat Jelas:** Halaman wajib dibagi ke dalam kontainer landmark standar: `<header>` (banner utama), `<nav>` (jika ada navigasi), `<main id="konten" tabindex="-1">` (area isi utama), dan `<footer>` (informasi pelengkap/bantuan). Pembaca layar modern (NVDA, JAWS, TalkBack, VoiceOver) menyediakan tombol pintas khusus (misal tombol `D` pada NVDA/JAWS atau menu Rotor Landmarks pada VoiceOver) untuk melompat antar-landmark.
- **Satu Landmark Utama per Halaman:** Elemen `<main>` wajib tunggal agar pengguna langsung memahami batas konten inti tanpa tersesat di elemen pembungkus generik `<div>`.
- **Skip Link:** Tautan lewati navigasi (*bypass blocks*) wajib diletakkan sebagai elemen fokus paling awal di DOM dengan teks eksplisit seperti `"Lewati ke konten utama"`, mengarahkan fokus langsung ke `<main id="konten">`.
- **Rujukan:**
  - [W3C WCAG 2.2: 2.4.1 Bypass Blocks (Level A)](https://www.w3.org/WAI/WCAG22/Understanding/bypass-blocks)
  - [W3C WAI-ARIA APG: Landmark Pattern](https://www.w3.org/WAI/ARIA/apg/patterns/landmarks/)
  - [MDN Web Docs: HTML Sectioning Elements and Accessibility](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/main)

#### B. Hirarki dan Disiplin Heading (Tingkat Judul)
- **Satu `<h1>` Utama per Halaman:** Setiap halaman wajib memiliki tepat satu elemen `<h1>` yang mendeskripsikan tujuan halaman secara spesifik (misalnya Beranda: `<h1>Mau belajar apa hari ini?</h1>`; Buku Saku Bab: `<h1>Bab 3: Membawa Gelas Berisi Air</h1>`).
- **Urutan Bertingkat Tanpa Melompat:** Struktur judul harus teratur (`h1` → `h2` → `h3`) tanpa melompati tingkatan (jangan pernah melompat dari `h1` langsung ke `h3`). Pengguna tunanetra memanfaatkan tombol pintas `H` (heading berikutnya) atau angka `1`–`6` untuk memindai dokumen.
- **Judul Deskriptif Mandiri:** Judul tidak boleh berupa kata abstrak atau petunjuk visual (hindari *"Di bawah ini"* atau *"Menu"* tanpa konteks).
- **Rujukan:**
  - [W3C WCAG 2.2: 2.4.6 Headings and Labels (Level AA)](https://www.w3.org/WAI/WCAG22/Understanding/headings-and-labels.html)
  - [W3C WAI Tutorials: Heading Structure](https://www.w3.org/WAI/tutorials/page-structure/headings/)

#### C. Urutan Fokus (Focus Order) dan Linearitas DOM
- **Kesesuaian Urutan DOM dan Visual:** Urutan pembacaan kode sumber HTML wajib identik dengan urutan visual di layar. Dilarang menggunakan manipulasi CSS seperti `flex-direction: reverse` atau CSS Grid order yang mengubah posisi visual tanpa mengubah urutan tabulasi keyboard.
- **Hindari `tabindex` Positif:** Jangan pernah memakai `tabindex="1"` atau nilai positif lainnya karena merusak alur fokus alami peramban. Elemen interaktif bawaan (`<a>`, `<button>`, `<input>`) otomatis berada di alur tab; kontainer konten dinamis cukup diberi `tabindex="-1"` ketika perlu menerima fokus terprogram melalui JavaScript.
- **Rujukan:**
  - [W3C WCAG 2.2: 2.4.3 Focus Order (Level A)](https://www.w3.org/WAI/WCAG22/Understanding/focus-order.html)
  - [W3C WCAG 2.2: 1.3.2 Meaningful Sequence (Level A)](https://www.w3.org/WAI/WCAG22/Understanding/meaningful-sequence.html)

#### D. Nama Aksesibel Kartu dan Tautan Navigasi (Accessible Names)
- **Kartu sebagai Item Daftar Semantik:** Tiga kartu utama di beranda wajib dibungkus dalam daftar semantik `<ul>` dan `<li>`. Pembaca layar akan mengumumkan informasi struktur yang sangat berharga: *"Daftar, 3 item"*, sehingga anak langsung mengetahui jumlah opsi yang tersedia.
- **Satu Target Tautan per Kartu:** Seluruh kartu navigasi dibungkus oleh satu elemen tautan `<a>`. Hindari meletakkan tautan atau tombol sekunder lain di dalam kartu tersebut (*no nested interactive controls*), karena akan menciptakan jebakan fokus ganda (*double tab stop*) yang membingungkan.
- **Nama Aksesibel Utuh dan Bermakna:**
  Nama kartu dihitung dari teks judul (`<h2>`) dan deskripsinya. Pola yang direkomendasikan adalah menyertakan judul dan deskripsi di dalam tautan, atau menghubungkannya via `aria-describedby`:
  ```html
  <ul class="grid grid-cols-1 md:grid-cols-3 gap-6" role="list">
    <li>
      <a href="/conversation" class="card-link" aria-describedby="desc-ngobrol">
        <h2>Ngobrol</h2>
        <p id="desc-ngobrol">Bicara seru bersama teman pintar tentang apa saja.</p>
      </a>
    </li>
  </ul>
  ```
  Dengan pola ini, pembaca layar mengumumkan: *"Tautan, Ngobrol, judul tingkat 2. Bicara seru bersama teman pintar tentang apa saja. 1 dari 3."*
- **Rujukan:**
  - [W3C WCAG 2.2: 4.1.2 Name, Role, Value (Level A)](https://www.w3.org/WAI/WCAG22/Understanding/name-role-value.html)
  - [W3C WAI-ARIA APG: Link Pattern](https://www.w3.org/WAI/ARIA/apg/patterns/link/)
  - [W3C WAI: Accessible Name and Description Computation 1.2](https://www.w3.org/TR/accname-1.2/)

---

### 1.2 Prinsip untuk Pengguna Low Vision

Pengguna *low vision* (daya lihat rendah, mata kabur, lapang pandang menyempit / *tunnel vision*, fotofobia, katarak kongenital) mengandalkan layar monitor dengan memperbesar ukuran teks, memperbesar tampilan peramban (*browser zoom* hingga 400%), mengaktifkan kontras tinggi, atau mengubah skema warna sistem.

#### A. Kontras Warna WCAG 2.2 (Level AA & AAA)
- **Teks Normal:** Rasio kontras minimal 4.5:1 untuk Level AA, dan **7:1 untuk Level AAA** (target optimal untuk anak low vision).
- **Teks Besar (≥18pt / ~24px biasa, atau ≥14pt / ~18.66px tebal):** Minimal 3:1 untuk Level AA, dan 4.5:1 untuk Level AAA.
- **Kontras Non-Teks (WCAG 2.2 Kriteria 1.4.11):** Elemen antarmuka pengguna seperti garis tepi kartu (*border*), kotak input, dan ikon status wajib memiliki rasio kontras minimal **3:1** terhadap warna latar belakang sekitarnya. Jangan hanya mengandalkan *box-shadow* tipis beropasitas rendah (misal `rgba(0,0,0,0.05)`) karena mata low vision tidak dapat menangkapnya.
- **Rujukan:**
  - [W3C WCAG 2.2: 1.4.3 Contrast (Minimum) (Level AA)](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html)
  - [W3C WCAG 2.2: 1.4.6 Contrast (Enhanced) (Level AAA)](https://www.w3.org/WAI/WCAG22/Understanding/contrast-enhanced.html)
  - [W3C WCAG 2.2: 1.4.11 Non-text Contrast (Level AA)](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html)

#### B. Ukuran Teks Minimal dan Satuan Relatif (`rem`)
- **Ukuran Dasar Lebih Besar:** Tuturnusa menetapkan ukuran dasar `html { font-size: 112.5%; }` (18px), bukan 16px standar, untuk memudahkan keterbacaan anak.
- **Selalu Gunakan `rem` dan `em`:** Hindari nilai absolut `px` pada ukuran font, padding, dan batas kontainer agar antarmuka membesar secara proporsional mengikuti setelan peramban atau sistem operasi pengguna.
- **Rujukan:**
  - [W3C WCAG 2.2: 1.4.4 Resize Text (Level AA)](https://www.w3.org/WAI/WCAG22/Understanding/resize-text.html)
  - [MDN Web Docs: CSS Values and Units - rem](https://developer.mozilla.org/en-US/docs/Learn_web_development/Core/Styling_basics/Values_and_units#relative_length_units)

#### C. Reflow Layar 320px (WCAG 2.2 Kriteria 1.4.10)
- Tampilan konten web tidak boleh mengharuskan pengguna menggulir secara horizontal dan vertikal sekaligus (*two-dimensional scrolling*) pada lebar viewport setara **320 CSS pixels** (ekuivalen dengan zoom peramban 400% pada resolusi desktop 1280px).
- Tiga kartu beranda yang sejajar di desktop wajib bertransformasi secara mulus menjadi tumpukan satu kolom vertikal (*single column flex/grid*) pada layar sempit atau saat di-zoom.
- **Rujukan:**
  - [W3C WCAG 2.2: 1.4.10 Reflow (Level AA)](https://www.w3.org/WAI/WCAG22/Understanding/reflow.html)

#### D. Pembesaran (Zoom) 200% hingga 400%
- Saat peramban diperbesar hingga 400%, tata letak tidak boleh patah (*broken layout*), teks tidak boleh terpotong (*text truncation*), teks tidak boleh tumpang tindih (*overlapping*), dan tombol aksi tidak boleh menghilang keluar layar.
- Ruang baca teks Buku Saku dibatasi pada lebar maksimal yang nyaman dibaca (maksimal 65–75 karakter per baris atau `max-w-2xl` / ~680px) agar mata low vision dengan lapang pandang terbatas tidak kesulitan menemukan awal baris berikutnya saat kembali ke sisi kiri.
- **Rujukan:**
  - [W3C WAI: Low Vision Needs - Text Resizing and Reflow](https://www.w3.org/TR/low-vision-needs/#reflow)

#### E. Mode Kontras Tinggi dan CSS Media Query `@media (forced-colors: active)`
- Pengguna low vision di Windows sering mengaktifkan *Contrast Themes* (seperti *Desert*, *Aquatic*, *Night Sky*, *Dusk*). Pada mode ini, peramban memaksa palet warna terbatas dan mengabaikan nilai warna yang ditulis pengembang serta meniadakan *box-shadow*.
- **Pemanfaatan CSS System Colors:** Gunakan warna sistem CSS seperti `Canvas`, `CanvasText`, `Highlight`, `HighlightText`, `ButtonFace`, dan `ButtonText`.
- **Garis Batas Tegas:** Setiap kartu dan tombol interaktif wajib memiliki garis tepi fisik (`border: 2px solid transparent` pada mode normal) yang otomatis berubah menjadi `border-color: CanvasText` atau `ButtonBorder` pada mode forced-colors.
  ```css
  .tuturnusa-card {
    border: 2px solid #14294d;
  }
  @media (forced-colors: active) {
    .tuturnusa-card {
      border: 2px solid ButtonBorder;
    }
    .tuturnusa-card:focus-visible {
      outline: 3px solid Highlight;
      outline-offset: 3px;
    }
  }
  ```
- **Rujukan:**
  - [MDN Web Docs: @media (forced-colors)](https://developer.mozilla.org/en-US/docs/Web/CSS/@media/forced-colors)
  - [W3C CSS Color Adjustment Module Level 1: forced-colors](https://www.w3.org/TR/css-color-adjust-1/#forced-colors-property)
  - [MDN Web Docs: CSS System Colors](https://developer.mozilla.org/en-US/docs/Web/CSS/system-color)

#### F. Gerakan Minim (`@media (prefers-reduced-motion: reduce)`)
- Anak-anak dengan gangguan penglihatan vestibular dapat mengalami pusing atau disorientasi jika elemen berpindah cepat atau berputar saat fokus berpindah.
- Matikan seluruh animasi perpindahan dan perpendek durasi transisi hingga di bawah 1ms saat preferensi sistem meminta gerakan minimal.
- **Rujukan:**
  - [W3C WCAG 2.2: 2.3.3 Animation from Interactions (Level AAA)](https://www.w3.org/WAI/WCAG22/Understanding/animation-from-interactions.html)
  - [MDN Web Docs: @media (prefers-reduced-motion)](https://developer.mozilla.org/en-US/docs/Web/CSS/@media/prefers-reduced-motion)

#### G. Indikator Fokus WCAG 2.2 (2.4.7, 2.4.11, 2.4.12, 2.4.13)
- **2.4.7 Focus Visible (Level AA):** Setiap kontrol interaktif yang menerima fokus keyboard wajib menampilkan indikator visual yang jelas.
- **2.4.11 Focus Not Obscured (Minimum) (Level AA - Baru di WCAG 2.2):** Saat elemen menerima fokus keyboard, elemen tersebut **tidak boleh tertutup sepenuhnya** oleh konten lengket (*sticky header*, bilah pengumuman, atau panel mengambang).
- **2.4.12 Focus Not Obscured (Enhanced) (Level AAA - Baru di WCAG 2.2):** Tidak ada bagian mana pun dari elemen fokus yang boleh tertutup oleh konten lain. Pada Tuturnusa, gunakan CSS `scroll-margin-top: 5rem;` pada elemen target navigasi internal agar fokus tidak tertutup header.
- **2.4.13 Focus Appearance (Level AAA - Baru di WCAG 2.2):**
  - Ketebalan garis fokus minimal **2px solid** (Tuturnusa menetapkan **3px solid** untuk visibilitas maksimal anak).
  - Kontras indikator fokus minimal **3:1** terhadap warna latar belakang dan minimal **3:1** terhadap status saat tidak fokus.
- **Rujukan:**
  - [W3C WCAG 2.2: 2.4.7 Focus Visible](https://www.w3.org/WAI/WCAG22/Understanding/focus-visible.html)
  - [W3C WCAG 2.2: 2.4.11 Focus Not Obscured (Minimum)](https://www.w3.org/WAI/WCAG22/Understanding/focus-not-obscured-minimum.html)
  - [W3C WCAG 2.2: 2.4.12 Focus Not Obscured (Enhanced)](https://www.w3.org/WAI/WCAG22/Understanding/focus-not-obscured-enhanced.html)
  - [W3C WCAG 2.2: 2.4.13 Focus Appearance](https://www.w3.org/WAI/WCAG22/Understanding/focus-appearance.html)

#### H. Target Sentuh dan Pointer (WCAG 2.2 Kriteria 2.5.8 & 2.5.5)
- **2.5.8 Target Size (Minimum) (Level AA - Baru di WCAG 2.2):** Target sentuh interaktif minimal berukuran **24 × 24 CSS pixels**, atau memiliki spasi pemisah di sekelilingnya sehingga tidak terjadi tumpang tindih area sentuh.
- **2.5.5 Target Size (Enhanced) (Level AAA):** Target sentuh minimal berukuran **44 × 44 CSS pixels**.
- **Standar Khusus Anak:** Karena koordinasi motorik halus anak masih berkembang dan pengguna low vision kesulitan membidik target kecil di layar sentuh ponsel, Tuturnusa menetapkan target tombol dan kartu utama berukuran minimal **56 × 56px**, dengan padding luas dan jarak antar-kartu minimal 24px (1.5rem).
- **Rujukan:**
  - [W3C WCAG 2.2: 2.5.8 Target Size (Minimum)](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html)
  - [W3C WCAG 2.2: 2.5.5 Target Size (Enhanced)](https://www.w3.org/WAI/WCAG22/Understanding/target-size-enhanced.html)

---

## 2. Pelajaran dari Design System Nyata yang Kuat Aksesibilitasnya

Beberapa sistem desain publik dunia telah teruji secara komprehensif dalam melayani pengguna difabel dan kebutuhan masyarakat luas.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           BENCHMARK DESIGN SYSTEM                           │
├──────────────────────┬──────────────────────┬───────────────────────────────┤
│    GOV.UK (UK GDS)   │     USWDS (US GSA)   │           BBC GEL             │
├──────────────────────┼──────────────────────┼───────────────────────────────┤
│ • Indikator fokus    │ • Sistem warna token │ • Tipografi ramah anak        │
│   kuning-hitam legendaris 0-100 (kontras pasti) (Reith Sans: aperture lega)│
│ • Summary Card & Link│ • Card Component     │ • Tata letak bebas distraksi  │
│ • Skala spasi stabil │ • Target sentuh 44px+│ • Audio didampingi teks nyata │
└──────────────────────┴──────────────────────┴───────────────────────────────┘
```

### 2.1 GOV.UK Design System (Pemerintah Inggris)

GOV.UK diakui secara global sebagai pelopor standar aksesibilitas web sektor publik. Komponen dan pola yang dapat diadopsi untuk Tuturnusa meliputi:

1. **Gaya Indikator Fokus yang Sangat Kontras (Yellow Focus Ring):**
   - GOV.UK tidak mengandalkan garis luar peramban yang tipis. Mereka menggunakan kombinasi latar belakang kuning terang (`#ffdd00`) dan garis tepi hitam tebal (`#0b0c0c` tebal 3–4px) serta `box-shadow` bawah tebal.
   - Pola ini memastikan indikator fokus selalu terlihat mencolok di atas latar belakang apa pun (putih, navy, abu-abu, maupun oranye).
   - [GOV.UK Design System: Focus state styling](https://design-system.service.gov.uk/styles/focus-state/)
2. **Pola Kartu dan Tautan Kontekstual (Summary Card & Navigation Cards):**
   - GOV.UK menghindari jebakan kartu yang rumit. Judul kartu menggunakan tag heading semantik (`<h2>`), dan jika seluruh kartu dapat diklik, tautan utama berada di judul atau membungkus blok dengan pseudo-elemen CSS `::after`.
   - Menghindari penumpukan tombol di dalam kartu yang dapat diklik.
   - Menggunakan teks tersembunyi visual (*visually hidden text*) untuk melengkapi konteks yang mungkin terputus jika pembaca layar hanya membaca kata aksi.
   - [GOV.UK Design System: Summary list component & Summary Cards](https://design-system.service.gov.uk/components/summary-list/#summary-cards)
   - [Ministry of Justice (MOJ) Pattern: Card component](https://design-patterns.service.justice.gov.uk/components/card/)
3. **Penyajian Teks Bacaan dan Tipografi:**
   - Skala tipografi GOV.UK dirancang stabil dan tidak meluap saat teks diperbesar (*zoom resistant*).
   - Panjang baris bacaan dibatasi secara ketat agar mata tidak lelah membaca paragraf panjang.
   - [GOV.UK Design System: Typography guidelines](https://design-system.service.gov.uk/styles/typography/)

### 2.2 USWDS - U.S. Web Design System (Pemerintah Amerika Serikat)

USWDS dikembangkan oleh Technology Transformation Services (TTS) di General Services Administration (GSA).

1. **Arsitektur Komponen Kartu (`usa-card`):**
   - Membagi kartu ke dalam struktur logis: kontainer (`usa-card__container`), kepala kartu (`usa-card__header`), badan konten (`usa-card__body`), dan kaki kartu (`usa-card__footer`).
   - Garis tepi (*border*) selalu didefinisikan secara eksplisit dengan warna berskala kontras tinggi, bukan sekadar shadow.
   - Area klik dirancang memenuhi kriteria target sentuh WCAG 2.2 Kriteria 2.5.8 dan 2.5.5 dengan batas padding minimal 16–24px.
   - [USWDS Component: Card](https://designsystem.digital.gov/components/card/)
2. **Sistem Token Warna Berbasis Skor Kontras (Grade-based Color Tokens):**
   - USWDS memberi nomor tingkat kecerahan warna dari 0 hingga 100.
   - Selisih nilai grade ≥ 40 menjamin rasio kontras 3:1 (lulus kontras elemen grafis WCAG 1.4.11).
   - Selisih nilai grade ≥ 50 menjamin rasio kontras 4.5:1 (lulus WCAG AA untuk teks normal).
   - Selisih nilai grade ≥ 70 menjamin rasio kontras 7:1 (lulus WCAG AAA).
   - Pendekatan matematis ini sangat tepat diadopsi dalam penentuan palet Tailwind Tuturnusa.
   - [USWDS Design Tokens: Color Overview](https://designsystem.digital.gov/design-tokens/color/overview/)

### 2.3 BBC GEL (Global Experience Language) & Desain Inklusif Anak

Divisi anak BBC (CBBC dan CBeebies) bersama tim aksesibilitas BBC menghasilkan pedoman digital ramah anak dan disabilitas penglihatan:

1. **Tipografi Terbuka dan Keterbacaan Huruf (Typeface Legibility):**
   - BBC mengembangkan huruf khusus *BBC Reith* dengan lubang huruf (*aperture*) yang lebar, x-height tinggi, dan bentuk karakter yang berbeda tegas (menghindari kerancuan antara huruf `I` besar, `l` kecil, dan angka `1`).
   - Font Tuturnusa (*Plus Jakarta Sans*) memiliki karakteristik modern sans-serif terbuka yang serupa dan sangat ramah untuk mata anak serta keterbacaan pada ukuran font besar.
   - [BBC GEL: Typography Guidelines](https://www.bbc.co.uk/gel/guidelines/typography)
2. **Kombinasi Suara dan Teks Sederhana Tanpa Distraksi:**
   - Konten instruksional untuk anak disajikan dalam kalimat-kalimat pendek, diiringi audio pendukung yang jelas tanpa kebisingan latar belakang.
   - Antarmuka tidak boleh memuat banyak tombol bersaing dalam satu layar pandang.
   - [BBC Accessibility Guidelines: Mobile and Touch Guidelines](https://www.bbc.co.uk/accessibility/forproducts/guides/mobile/)

---

## 3. Kebutuhan Khusus Anak (Tunanetra dan Low Vision)

Anak-anak penyandang tunanetra atau low vision memiliki profil kognitif dan interaksi yang berbeda dengan orang dewasa. Kapasitas memori kerja (*working memory*) mereka masih terbatas, rentang perhatian lebih pendek, dan mereka menafsirkan kata-kata secara harfiah (*literal interpretation*).

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                       PRINSIP KONTEN UNTUK ANAK                             │
├──────────────────────────┬──────────────────────────┬───────────────────────┤
│     BAHASA SEDERHANA     │      PANJANG KALIMAT     │    IKON & SIMBOL      │
├──────────────────────────┼──────────────────────────┼───────────────────────┤
│ • Kosa kata konkret      │ • Maksimal 10-15 kata    │ • Bersifat dekoratif  │
│ • Tanpa idiom/metafora   │ • Satu aksi per kalimat  │ • Selalu ada teks     │
│ • Nada ramah & langsung  │ • Hindari anak kalimat   │ • Siluet tebal kontras│
└──────────────────────────┴──────────────────────────┴───────────────────────┘
```

### 3.1 Bahasa Sederhana (*Plain Language for Children*)
1. **Kosa Kata Konkret vs Abstrak:**
   - Gunakan kata kerja dan kata benda yang nyata dalam kehidupan sehari-hari anak.
   - **Hindari istilah teknis atau birokratis:** Jangan gunakan kata seperti *"Inisialisasi"*, *"Konfigurasi"*, *"Autentikasi"*, *"Fitur"*, atau *"Navigasi"*. Gunakan *"Mulai"*, *"Pilih"*, *"Buku"*, *"Suara"*.
   - **Hindari metafora visual:** Anak tunanetra sejak lahir (*congenital blindness*) tidak memiliki referensi visual. Jangan gunakan ungkapan seperti *"Lihat gambar di bawah"*, *"Buka matamu lebar-lebar"*, atau *"Klik tombol yang berwarna kuning terang"*. Gantilah dengan *"Dengarkan cerita berikut"*, *"Pilih tombol Ngobrol"*.
2. **Panjang Kalimat dan Struktur Sintaksis:**
   - Panjang kalimat maksimal **10 hingga 15 kata per kalimat**.
   - Gunakan struktur kalimat aktif Subjek-Predikat-Objek yang lugas (misalnya: *"Pilih buku yang ingin kamu dengarkan"* bukan *"Pilihan terhadap buku yang dikehendaki untuk diperdengarkan dapat dilakukan"*).
   - Satu kalimat hanya menyampaikan **satu instruksi atau satu ide pokok**.
3. **Penyajian Deskripsi Tiga Kartu Beranda yang Ramah Anak:**
   - **Ngobrol:**
     - *Judul:* Ngobrol
     - *Deskripsi:* Bicara seru bersama teman pintar tentang apa saja. (8 kata)
   - **Ucapan:**
     - *Judul:* Ucapan
     - *Deskripsi:* Latih caramu berbicara kata demi kata dengan jelas. (8 kata)
   - **Buku Saku:**
     - *Judul:* Buku Saku
     - *Deskripsi:* Dengarkan cerita dan panduan kegiatan sehari-hari yang seru. (8 kata)

### 3.2 Ikon yang Tidak Wajib Dimengerti
1. **Ikon Bersifat Dekoratif / Sekunder:**
   - Bagi anak buta total, ikon visual tidak memiliki nilai apa pun. Oleh karena itu, semua elemen ikon visual (SVG, FontIcon) **wajib disembunyikan secara mutlak dari pembaca layar** menggunakan atribut `aria-hidden="true"`.
   - Screen reader tidak boleh membacakan nama ikon (hindari pengumuman sampah seperti *"Gambar graphic megaphone"* atau *"SVG path"*).
2. **Larangan Ikon Telanjang (*No Naked Icons*):**
   - Ikon tidak boleh berdiri sendiri tanpa label teks nyata. Setiap ikon tombol wajib didampingi teks yang terlihat jelas bagi anak low vision, atau label aksesibel lengkap bagi pembaca layar.
   - Anak-anak belum memiliki pengalaman untuk menafsirkan ikon metaforis abstrak (seperti ikon roda gigi untuk pengaturan atau awan untuk penyimpanan).
3. **Bentuk Ikon Khusus Anak Low Vision:**
   - Jika ikon ditampilkan untuk anak low vision, gunakan siluet tebal (*solid silhouette*), sederhana, dengan garis luar (*stroke*) minimal 2–3px.
   - Hindari ilustrasi bergaya garis halus (*thin hairline line-art*), gradasi warna lembut, atau detail rumit yang akan tampak seperti bercak buram bagi mata berkabut/katarak.
4. **Rujukan Riset:**
   - [Nielsen Norman Group: Children's Websites: Usability Issues in Designing for Kids](https://www.nngroup.com/articles/children-ux-usability/)
   - [Nielsen Norman Group: Icon Usability and Recognizability](https://www.nngroup.com/articles/icon-usability/)
   - [W3C WAI: Making Content Usable for People with Cognitive and Learning Disabilities](https://www.w3.org/TR/coga-usable/)
   - [Plain Language Association International (PLAIN): Guidelines](https://plainlanguagenetwork.org/plain-language/what-is-plain-language/)

---

## 4. Usulan Token Konkret untuk Tuturnusa

Token desain berikut disusun berdasarkan temuan di `tailwind.config.js`, `resources/css/app.css`, dan rancangan antarmuka pada `docs/superpowers/specs/2026-11-24-tuturnusa-tunanetra-refactor-design.md`.

### 4.1 Palet Warna dan Verifikasi Rasio Kontras Eksak

Identitas warna Tuturnusa adalah kombinasi **Navy** dan aksen **Oranye/Kuning**. Namun, audit kontras matematis berdasarkan rumus luminansi relatif WCAG menunjukkan temuan kritis yang harus dikoreksi pada level token:

```
                       AUDIT KONTRAS WARNA WCAG 2.2
┌──────────────────────┬──────────────────────┬─────────────┬───────────────┐
│     WARNA DEPAN      │     WARNA BELAKANG   │ RASIO HASIL │  STATUS WCAG  │
├──────────────────────┼──────────────────────┼─────────────┼───────────────┤
│ Navy (#14294D)       │ Putih (#FFFFFF)      │   14.44 : 1 │ Lolos AAA     │
│ Navy (#14294D)       │ Cream (#FBF8F3)      │   13.63 : 1 │ Lolos AAA     │
│ Ink (#2B3648)        │ Putih (#FFFFFF)      │   12.18 : 1 │ Lolos AAA     │
│ Ink-soft (#4F5968)   │ Putih (#FFFFFF)      │    7.09 : 1 │ Lolos AAA     │
│ Ink-faint (#5A6472)  │ Putih (#FFFFFF)      │    6.00 : 1 │ Lolos AA      │
│ Oranye (#F9B934)     │ Putih (#FFFFFF)      │    1.75 : 1 │ GAGAL TOTAL!  │
│ Oranye (#F9B934)     │ Cream (#FBF8F3)      │    1.65 : 1 │ GAGAL TOTAL!  │
│ Putih (#FFFFFF)      │ Oranye (#F9B934)      │    1.75 : 1 │ GAGAL TOTAL!  │
│ Navy (#14294D)       │ Oranye (#F9B934)     │    8.26 : 1 │ Lolos AAA!    │
│ Hitam (#000000)      │ Oranye (#F9B934)     │   12.02 : 1 │ Lolos AAA!    │
│ Amber Teks (#B45309) │ Putih (#FFFFFF)      │    5.02 : 1 │ Lolos AA      │
│ Amber Tua (#92400E)  │ Putih (#FFFFFF)      │    7.09 : 1 │ Lolos AAA     │
│ Biru Fokus (#1D4ED8) │ Putih (#FFFFFF)      │    6.70 : 1 │ Lolos AA      │
│ Garis Line (#ECE7DD) │ Putih (#FFFFFF)      │    1.23 : 1 │ Gagal (Non-T) │
└──────────────────────┴──────────────────────┴─────────────┴───────────────┘
```

#### Aturan Penggunaan Warna Navy dan Oranye
1. **Navy `#14294D`:** Lolos standar tertinggi WCAG AAA pada latar putih (14.44:1) dan latar cream (13.63:1). Navy adalah warna teks utama, judul, ikon fungsional, dan garis tepi kartu.
2. **Oranye Eksisting `#F9B934`:**
   - **DILARANG KERAS** digunakan sebagai warna teks di atas latar putih atau cream (kontras hanya 1.75:1 / 1.65:1, sangat membahayakan mata low vision).
   - **DILARANG KERAS** menggunakan teks warna putih di atas tombol oranye `#F9B934` (kontras 1.75:1).
   - **BOLEH DIPERTAHANKAN** sebagai warna latar belakang (*background badge*, pil aksen, atau tombol utama) **DENGAN SYARAT teks di dalamnya WAJIB berwarna Navy `#14294D` (kontras 8.26:1, Lolos AAA)** atau Hitam `#000000` (12.02:1, Lolos AAA).
3. **Oranye untuk Teks Mandiri (`text-orange-accessible`):** Jika antarmuka membutuhkan teks atau tautan bernuansa oranye di atas latar putih/cream, gunakan token `#92400E` (Amber-800, kontras 7.09:1, Lolos AAA) atau `#B45309` (Amber-700, 5.02:1, Lolos AA).
4. **Cream `#FBF8F3`:** Latar belakang halaman yang hangat, mengurangi silau (*glare*) bagi anak low vision dengan fotofobia dibandingkan putih murni 100%.
5. **Garis Tepi Kartu:** Garis lama `#ECE7DD` memiliki kontras 1.23:1 (tidak terlihat oleh mata low vision). Untuk batas kartu, gunakan garis tegas minimal 2px Navy `#14294D` atau abu-abu gelap `#5A6472` (kontras 6:1 terhadap putih).

#### Tabel Token Warna Tailwind
```js
// Usulan token warna untuk tailwind.config.js
colors: {
  navy: {
    DEFAULT: '#14294D', // 14.44:1 on white (AAA)
    deep: '#0E1F3B',    // 16.43:1 on white (AAA)
    soft: '#24416E',    // 10.21:1 on white (AAA)
  },
  cream: '#FBF8F3',     // Background ramah fotofobia
  card: {
    DEFAULT: '#FFFFFF',
    border: '#14294D',  // Border kartu tebal, 14.44:1
  },
  ink: {
    DEFAULT: '#14294D', // Teks utama memakai Navy pekat
    charcoal: '#2B3648',// 12.18:1 (AAA)
    soft: '#4F5968',    // 7.09:1 (AAA)
  },
  orange: {
    // Background aksen (WAJIB teks Navy di atasnya)
    brand: '#F9B934',   // 8.26:1 against Navy text (AAA)
    // Teks aksen jika berada di atas latar putih/cream
    text: '#92400E',    // 7.09:1 on white (AAA)
    'text-aa': '#B45309',// 5.02:1 on white (AA)
  },
  focus: {
    blue: '#1D4ED8',    // Focus ring biru sketsa Figma (6.70:1 on white)
    amber: '#B45309',   // Ring pelengkap dua-nada
    yellow: '#FFDD00',  // Alternatif model GOV.UK
  }
}
```

---

### 4.2 Skala Tipografi (rem)

Font yang ditetapkan adalah **Plus Jakarta Sans** (sans-serif netral dengan open counter dan x-height besar).

Skala font diatur dengan basis `html { font-size: 112.5%; }` (1rem = 18px). Hal ini menjamin ukuran teks dasar berada di angka 18px—standar ideal bagi anak dan low vision.

```
┌────────────┬─────────────┬──────────────┬──────────────┬───────────────────────────────┐
│ TOKEN      │ UKURAN REM  │ EKUIVALEN PX │ LINE HEIGHT  │ PENGGUNAAN PADA TUTURNUSA     │
├────────────┼─────────────┼──────────────┼──────────────┼───────────────────────────────┤
│ text-xs    │ 0.875rem    │ 15.75 px     │ 1.4 (1.25rem)│ Metadata durasi audio bab     │
│ text-sm    │ 1.000rem    │ 18.00 px     │ 1.5 (1.50rem)│ Label tombol bantuan/kembali  │
│ text-base  │ 1.125rem    │ 20.25 px     │ 1.6 (1.75rem)│ Paragraf bacaan, deskripsi    │
│ text-lg    │ 1.250rem    │ 22.50 px     │ 1.5 (1.75rem)│ Subjudul bagian, instruksi    │
│ text-xl    │ 1.500rem    │ 27.00 px     │ 1.3 (2.00rem)│ Judul 3 Kartu Beranda (H2)    │
│ text-2xl   │ 1.875rem    │ 33.75 px     │ 1.3 (2.25rem)│ Judul Bab Buku Saku (H1)      │
│ text-3xl   │ 2.250rem    │ 40.50 px     │ 1.2 (2.75rem)│ Judul Utama Beranda (H1)      │
└────────────┴─────────────┴──────────────┴──────────────┴───────────────────────────────┘
```

#### Aturan Spasi Huruf dan Paragraf (WCAG 1.4.12 Text Spacing)
- **Tinggi Baris (*Line Height*):** Minimal 1.6 untuk teks paragraf bacaan (`leading-relaxed`), tidak boleh dipatok kaku agar saat teks membesar tidak bertabrakan.
- **Dukungan Mode Teks Lega (`html.teks-lega`):** Sesuai CSS eksisting Tuturnusa yang memenuhi WCAG 1.4.12:
  - `line-height: 1.8 !important;`
  - `letter-spacing: 0.12em !important;`
  - `word-spacing: 0.16em !important;`
  - `margin-bottom: 0.25em !important;` untuk `<p>` dan `<li>`.

---

### 4.3 Spasi, Target Sentuh, Radius, dan Elevasi

1. **Target Sentuh Interaktif:**
   - Tombol Mode Suara, Tombol Putar Audio, dan Navigasi: minimal **56 × 56px** (melebihi standar WCAG AAA 44px).
   - Tiga Kartu Beranda: Seluruh area kartu merupakan target klik aktif seluas minimal **320 × 200px** di desktop dan **100% lebar layar × 120px tinggi** di mobile.
2. **Spasi Antar Elemen (*Gap*):**
   - Jarak antar kartu beranda: `gap-6` (1.5rem / 27px) hingga `gap-8` (2rem / 36px) untuk mencegah salah sentuh saat menggunakan perangkat tablet atau ponsel sentuh.
3. **Radius Sudut (*Border Radius*):**
   - Kartu: `rounded-2xl` (1rem / 16px) untuk kesan ramah anak namun tetap terdefinisi batasnya.
   - Tombol interaktif: `rounded-xl` (0.75rem / 12px).
4. **Elevasi dan Garis Batas:**
   - **Garis Batas Wajib (Bukan Hanya Bayangan):** Setiap kartu wajib memiliki `border-2 border-navy` (2px solid `#14294D`).
   - Bayangan lembut (*soft shadow*) boleh ditambahkan hanya sebagai penambah estetika visual (`box-shadow: 0 4px 16px rgba(20, 41, 77, 0.08)`), tetapi batas pembeda utama kartu adalah garis batas solid 2px.

---

### 4.4 Gaya Indikator Fokus (Focus Ring Specification)

Sesuai arahan sketsa desain Figma (*"kartu yang fokus diberi garis biru"*) serta ketentuan WCAG 2.2 Kriteria 2.4.13 (Focus Appearance), indikator fokus dirancang menggunakan **pola cincin dua-nada (*two-tone focus ring*) dengan garis biru tebal kontras tinggi**:

```css
/* resources/css/app.css - Aturan Indikator Fokus Aksesibel */
:focus-visible {
  /* Garis biru tebal 3px (Blue-700 #1D4ED8 memiliki kontras 6.70:1 terhadap latar putih) */
  outline: 3px solid #1d4ed8 !important;
  outline-offset: 3px !important;
  /* Cincin luar putih 3px untuk memastikan kontras bila berada di latar gelap/navy */
  box-shadow: 0 0 0 3px #ffffff, 0 0 0 6px #1d4ed8 !important;
}

/* Dukungan Windows High Contrast / Forced Colors */
@media (forced-colors: active) {
  :focus-visible {
    outline: 3px solid Highlight !important;
    outline-offset: 3px !important;
    box-shadow: none !important;
  }
}
```

Saat sebuah kartu beranda menerima fokus keyboard:
- Garis tepi kartu menebal menjadi 3px solid biru cerah (`#1D4ED8`).
- Latar belakang kartu bertransisi halus menjadi warna aksen lembut yang sangat kontras (misal `bg-blue-50/50` atau `bg-amber-50`), memberikan umpan balik visual ganda (garis + bidang).

---

### 4.5 Ukuran Kartu dan Tata Letak Beranda (Desktop & Mobile)

#### A. Beranda Desktop (Viewport ≥ 1024px)
- **Kontainer Utama:** Lebar maksimal `max-w-5xl mx-auto px-6 py-12`.
- **Header:** Logo TUTURNUSA di kiri atas (`h-10`), tombol Mode Suara di kanan atas (`min-h-[56px] min-w-[56px]`).
- **Judul Sapaan:** `<h1>Mau belajar apa hari ini?</h1>` (`text-3xl font-bold text-navy mb-10 text-center md:text-left`).
- **Susunan Kartu:** 3 kartu berjajar horisontal dalam grid 3 kolom:
  `grid grid-cols-1 md:grid-cols-3 gap-6 items-stretch`
- **Dimensi Kartu Desktop:**
  - Lebar per kartu: ~300px – 340px.
  - Tinggi kartu: minimal 220px (`min-h-[220px]`).
  - Padding dalam: `p-6 md:p-8 flex flex-col justify-between`.

```
┌────────────────────────────────────────────────────────────────────────┐
│ [Logo TUTURNUSA]                                       [Mode Suara 🎙️]│
│                                                                        │
│ <h1>Mau belajar apa hari ini?</h1>                                     │
│                                                                        │
│ ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐       │
│ │ [Ikon Chat 💬]   │  │ [Ikon Mic 🗣️]    │  │ [Ikon Buku 📖]   │       │
│ │                  │  │                  │  │                  │       │
│ │ <h2>Ngobrol</h2> │  │ <h2>Ucapan</h2>  │  │ <h2>Buku Saku</h2>│      │
│ │                  │  │                  │  │                  │       │
│ │ Bicara seru      │  │ Latih caramu     │  │ Dengarkan cerita │       │
│ │ bersama teman    │  │ berbicara kata   │  │ dan panduan      │       │
│ │ pintar tentang   │  │ demi kata dengan │  │ kegiatan sehari- │       │
│ │ apa saja.        │  │ jelas.           │  │ hari yang seru.  │       │
│ └──────────────────┘  └──────────────────┘  └──────────────────┘       │
│                                                                        │
│ [Bantuan & Aksesibilitas]                                              │
└────────────────────────────────────────────────────────────────────────┘
```

#### B. Beranda Mobile (Viewport 320px – 639px)
- **Susunan Vertikal (Reflow 1 Kolom):** Kartu ditumpuk ke bawah (`flex flex-col gap-4 w-full px-4`).
- **Dimensi Kartu Mobile:**
  - Lebar: 100% viewport.
  - Tinggi: minimal 120px.
  - Penataan Isi: Tata letak mendatar (*horizontal card layout*) dengan ikon di kiri (ukuran 48×48px), judul `<h2>` dan deskripsi di kanan, membuat jangkauan jempol anak sangat ergonomis.

#### C. Halaman Bacaan Buku Saku
- **Lebar Kolom Teks:** `max-w-2xl mx-auto px-4 py-8` (maksimal ~680px, memastikan panjang baris tidak melebihi 70 karakter).
- **Rata Kiri (*Left-Aligned / Ragged Right*):** Teks bacaan tidak boleh dibuat rata kanan-kiri (*justify*), karena perataan *justify* menimbulkan celah kosong antar-kata (*rivers of white space*) yang sangat membingungkan pembaca low vision dan disleksia.
- **Struktur Halaman Bab:**
  1. Tautan navigasi balik di paling atas: `← Kembali ke Daftar Isi` (target tap 48px).
  2. Judul Bab: `<h1>Bab 3: Membawa Gelas Berisi Air</h1>`.
  3. Keterangan Audio Lisan: `<p class="text-sm text-ink-soft">Dengarkan rekaman bab ini (durasi 3 menit 20 detik):</p>`.
  4. Komponen Pemutar Audio: `<audio controls preload="none" class="w-full my-4">` (tanpa *autoplay*).
  5. Isi Bacaan Semantik:
     - `<h2>Alat dan Bahan</h2>` diikuti daftar `<ul>`.
     - `<h2>Langkah-Langkah</h2>` diikuti daftar berurutan `<ol>`. Tiap langkah adalah satu `<li>` lengkap.
     - `<h2>Patokan Keberhasilan</h2>` diikuti paragraf penjelasan.
  6. Navigasi Bawah: Tautan bab sebelumnya dan bab berikutnya dalam wadah besar berjejer rapi.

---

## 5. Daftar Periksa (Checklist) QA Aksesibilitas

Daftar periksa ini dirancang untuk tim Quality Assurance (QA) dan pengembang sebelum setiap rilis kode Tuturnusa.

| No | Kategori | Item Pengujian | Metode / Alat Uji | Kriteria Lolos (Pass Criteria) |
|---|---|---|---|---|
| **1** | **Semantik** | Landmark Utama | DOM Inspector / WAVE | Terdapat tepat 1 `<header>`, 1 `<main id="konten">`, dan 1 `<footer>`. |
| **2** | **Semantik** | Struktur Heading | NVDA (`H`) / HeadingsMap | Tepat satu `<h1>` per halaman. Urutan berjenjang tanpa loncat (`h1` → `h2` → `h3`). |
| **3** | **Semantik** | Kartu Beranda | Pembaca Layar (NVDA/TalkBack) | Dibungkus dalam `<ul>` & `<li>`. Terbaca: *"Daftar, 3 item"*. |
| **4** | **Semantik** | Nama Aksesibel Kartu | NVDA / VoiceOver | Kartu dibacakan utuh: judul + deskripsi kalimat tanpa karakter aneh atau nama ikon. |
| **5** | **Semantik** | Tautan vs Tombol | Keyboard / Inspeksi Tab | Kartu beranda & navigasi bab menggunakan `<a>`. Aksi suara & kirim pesan memakai `<button>`. |
| **6** | **Visual** | Kontras Teks Normal | Colour Contrast Analyser (CCA) | Rasio kontras teks body terhadap latar minimal **7:1 (AAA)** (Navy `#14294D` on White = 14.44:1). |
| **7** | **Visual** | Larangan Teks Oranye | Inspeksi Visual & CCA | Tidak ada teks oranye `#F9B934` di atas putih. Teks pada latar oranye wajib Navy/Hitam. |
| **8** | **Visual** | Kontras Non-Teks | CCA (Kriteria 1.4.11) | Garis batas kartu (`border-2`) memiliki rasio kontras minimal **3:1** terhadap latar sekitarnya. |
| **9** | **Visual** | Zoom 200% - 400% | Peramban Ctrl/Cmd + (+) | Tata letak tidak tumpang tindih, tidak ada teks terpotong (*no clipping*), tidak ada scroll horizontal. |
| **10**| **Visual** | Reflow 320px | Chrome DevTools (320px lebar) | 3 kartu beranda bertumpuk vertikal menjadi 1 kolom, seluruh teks dan tombol tetap terbaca penuh. |
| **11**| **Visual** | Text Spacing (1.4.12) | Bookmarklet Text Spacing / `.teks-lega` | Konten tidak meluap saat `line-height: 1.8`, `letter-spacing: 0.12em`, `word-spacing: 0.16em` aktif. |
| **12**| **Fokus** | Skip Link | Tombol `Tab` dari awal muat | Tautan "Lewati ke konten utama" muncul di kiri atas pada tab pertama, Enter memindahkan fokus ke `<main>`. |
| **13**| **Fokus** | Indikator Fokus Tampak | Tombol `Tab` keliling halaman | Seluruh kontrol interaktif menampilkan garis biru 3px tebal (`#1D4ED8`) dengan cincin kontras. |
| **14**| **Fokus** | Fokus Tidak Tertutup | Tab melewati halaman panjang | Kontrol yang fokus tidak tertutup oleh header lengket (lolos WCAG 2.2 2.4.11 & 2.4.12). |
| **15**| **Motorik** | Target Sentuh Minimal | DevTools Element Inspection | Tombol audio dan navigasi memiliki luas klik minimal **44 × 44px** (rekomendasi **56 × 56px**). |
| **16**| **Sistem** | Windows Contrast Themes | Emulate `forced-colors: active` | Garis kartu tetap tampak tegas menggunakan warna sistem `ButtonBorder` / `CanvasText`. |
| **17**| **Sistem** | Reduced Motion | Emulate `prefers-reduced-motion` | Animasi dan transisi dinonaktifkan (durasi transisi ≤ 0.01ms). |
| **18**| **Media** | Larangan Autoplay | Muat halaman bab Buku Saku | Audio MP3 **TIDAK PERNAH** berputar otomatis. Memerlukan penekanan tombol Putar secara sengaja. |
| **19**| **Media** | Kontrol Audio Semantik | NVDA / TalkBack | Elemen `<audio controls preload="none">` terbaca sebagai pemutar media dengan tombol Putar/Jeda. |
| **20**| **Bahasa** | Keterbacaan Anak | Evaluasi Teks Manual | Kalimat maksimal 15 kata, kosa kata konkret, instruksi aktif, bebas istilah teknis/metafora visual. |

---

## 6. Rekomendasi untuk Tuturnusa

Berikut adalah daftar rekomendasi konkret bernomor yang siap dieksekusi oleh tim pengembang dan perancang antarmuka:

1. **Kunci Token Warna Aman Kontras:** Tetapkan Navy `#14294D` sebagai warna teks dan garis tepi utama (14.44:1 pada putih). Batasi pemakaian oranye `#F9B934` khusus sebagai latar belakang tombol/badge dengan teks Navy di dalamnya (8.26:1), dan hapus seluruh penggunaan teks oranye di atas latar terang.
2. **Standardisasi Indikator Fokus Cincin Dua-Nada:** Implementasikan aturan CSS global `:focus-visible` dengan ketebalan 3px warna biru `#1D4ED8`, offset 3px, dan cincin luar putih `#FFFFFF` untuk menjamin kepatuhan WCAG 2.2 Kriteria 2.4.13 di semua kondisi kontras.
3. **Bangun 3 Kartu Beranda dengan Semantik Daftar Murni:** Susun tiga kartu beranda dalam elemen `<ul>` dan `<li>`. Bungkus setiap kartu ke dalam satu elemen tautan `<a>` yang membawahi `<h2>` judul dan `<p>` deskripsi satu kalimat, tanpa kontrol interaktif bertingkat di dalamnya.
4. **Terapkan Target Sentuh Ramah Anak Minimal 56px:** Pastikan tombol Mode Suara di header, tombol kembali ke beranda, kontrol pemutar audio, serta seluruh area kartu beranda memiliki dimensi fisik interaktif minimal 56 × 56px dengan padding lega.
5. **Dukung Reflow Responsif 320px Penuh:** Pastikan layout beranda otomatis beralih dari 3 kolom di desktop menjadi 1 kolom vertikal fleksibel pada layar mobile atau saat peramban diperbesar (zoom) hingga 400%, tanpa memicu scrollbar horizontal.
6. **Pastikan Komponen Kartu Tahan Mode `forced-colors`:** Tambahkan garis tepi fisik eksplisit `border: 2px solid transparent` atau `border-navy` pada semua kartu dan tombol agar batas visual kartu tidak hilang ketika sistem operasi Windows beralih ke tema kontras tinggi (*High Contrast Mode*).
7. **Patuhi Batas Karakter dan Struktur Bacaan Buku Saku:** Format halaman bab Buku Saku dengan kontainer teks maksimal 65–75 karakter (`max-w-2xl`), teks rata kiri (*ragged right*), pemutar audio `<audio controls preload="none">` diletakkan tepat di bawah `<h1>`, dan langkah-langkah diurai dalam elemen semantik `<ol>` dan `<li>`.
8. **Pertahankan Kebijakan Anti-Autoplay:** Larang pemutaran audio otomatis di seluruh aplikasi, baik audio MP3 Buku Saku maupun suara sintetis AI Ngobrol, agar tidak bertabrakan dengan keluaran suara pembaca layar anak.
9. **Gunakan Bahasa Ramah Anak yang Konkret:** Tulis seluruh teks panduan dan deskripsi fitur dalam kalimat pendek (maksimal 10–15 kata), menggunakan kosa kata yang akrab bagi anak sekolah dasar dan bebas dari kiasan visual.
10. **Jalankan Matriks Uji Manual Berkala dengan Screen Reader Nyata:** Validasi setiap iterasi komponen UI menggunakan kombinasi NVDA di Windows, TalkBack di Android, dan VoiceOver di iOS/macOS sebelum menyatakan antarmuka siap digunakan oleh anak-anak tunanetra.
