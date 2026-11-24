# Riset: pembaca layar, audio TTS, dan konten untuk Tuturnusa

**Tanggal:** 24 November 2026  
**Cakupan:** aplikasi web Tuturnusa (anak tunanetra, Bahasa Indonesia) — fitur Ngobrol (Livewire), Ucapan, Buku Saku (HTML + `edge-tts` + MP3 pragenerated).  
**Sumber konten aplikasi:** dua Buku Saku PDF di root repo (15 bab kegiatan sehari-hari, 12 bab senam dasar), konversi ke Markdown + audio per bab.

Dokumen ini merangkum perilaku pembaca layar utama, cara uji, opsi TTS Indonesia, prinsip penulisan audio-first, konteks Indonesia, dan rekomendasi implementasi.

---

## 1. Perilaku pembaca layar terhadap elemen HTML

Urutan baca pembaca layar mengikuti **pohon aksesibilitas / urutan DOM**, bukan tata letak visual ([web.dev: Navigating content](https://web.dev/articles/navigating-content)). Untuk Tuturnusa, implikasinya: beranda (`<ul>` berisi tiga `<a>` kartu), halaman bab Buku Saku (`<h1>` → `<audio>` → teks → `<ol>`), dan Livewire Ngobrol harus punya DOM yang logis saat dibaca dari atas.

### 1.1 Ringkasan per pembaca layar dan elemen

| Elemen | NVDA (FF/Chrome) | JAWS (Chrome) | TalkBack (Chrome Android) | VoiceOver (Safari iOS / Mac) |
|--------|------------------|---------------|---------------------------|------------------------------|
| `<audio controls>` | Nama aksesibel dibacakan jika ada; kontrol pemutar dapat dinavigasi sebagai tombol ([TetraLogical NVDA](https://tetralogical.github.io/screen-reader-HTML-support/NVDA.html)). Tanpa `controls`, tidak diumumkan ([RAT media](https://jonneal.dev/rat/media.html)). | Mengumumkan “Audio start/end” dan kontrol (play, seek, mute) ([TetraLogical JAWS](https://tetralogical.github.io/screen-reader-HTML-support/JAWS.html)); `aria-label` membantu nama kelompok. | **Tidak** mengumumkan elemen `audio` sendiri; kontrol pemutar dapat dijangkau lewat navigasi “Controls” ([TetraLogical TalkBack](https://tetralogical.github.io/screen-reader-HTML-support/TalkBack-android.html)). | iOS: “audio” + tombol pemutar; scrubber waktu kadang tidak stabil dengan VO ([TetraLogical VO iOS](https://tetralogical.github.io/screen-reader-HTML-support/VO-ios.html)). Mac: “Audio Controls, group” ([TetraLogical VO Mac](https://tetralogical.github.io/screen-reader-HTML-support/VO-mac.html)). |
| `<ol>` / `<li>` | Daftar: “List with *n* items”; tiap item membacakan angka/huruf urutan ([NVDA](https://tetralogical.github.io/screen-reader-HTML-support/NVDA.html)). | Posisi item dalam daftar terurut ([JAWS](https://tetralogical.github.io/screen-reader-HTML-support/JAWS.html)); pintasan `L` / `I`. | Hanya `<li>` yang masuk urutan geser; posisi “1 of X” ([TalkBack](https://tetralogical.github.io/screen-reader-HTML-support/TalkBack-android.html)). | “list, X items” (Mac); iOS: “X, list start” — **daftar perlu ditambahkan ke Rotor** di pengaturan VO ([VO iOS](https://tetralogical.github.io/screen-reader-HTML-support/VO-ios.html)). |
| Heading `h1–h6` | “… heading level *n*”; navigasi `H` / daftar `INSERT+F7` ([NVDA](https://tetralogical.github.io/screen-reader-HTML-support/NVDA.html)). | Level + teks; `H`, daftar `INSERT+F6` ([JAWS](https://tetralogical.github.io/screen-reader-HTML-support/JAWS.html)). | Reading control “Headings” di Chrome ([Google TalkBack + Chrome](https://support.google.com/accessibility/android/answer/2633135?hl=en)). | Rotor Headings; urutan heading mengikuti DOM ([web.dev](https://web.dev/articles/navigating-content)). |
| `<table>` data | “table with X rows and Y columns”; `T` untuk lompat tabel ([NVDA](https://tetralogical.github.io/screen-reader-HTML-support/NVDA.html)). | Navigasi sel dengan `Ctrl+Alt+panah`; caption dibacakan ([JAWS](https://tetralogical.github.io/screen-reader-HTML-support/JAWS.html)). | Caption + “X rows, Y columns”; navigasi baris/kolom ([TalkBack](https://tetralogical.github.io/screen-reader-HTML-support/TalkBack-android.html)). | Mirip; tabel perlu diaktifkan di Rotor (iOS) ([VO iOS](https://tetralogical.github.io/screen-reader-HTML-support/VO-ios.html)). |
| `aria-live` | Region hidup mengumumkan perubahan **tanpa memindahkan fokus**; `polite` vs `assertive` ([MDN aria-live](https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Reference/Attributes/aria-live)). | Sama (mengikuti spesifikasi ARIA). | Sama; TalkBack kadang berbeda dari VO dalam timing live region ([accessibility.build TalkBack](https://accessibility.build/guides/talkback-screen-reader-testing)). | Sama. |
| Tautan `<a href>` vs tombol | Teks + “link”; `K` daftar tautan ([NVDA](https://tetralogical.github.io/screen-reader-HTML-support/NVDA.html)). Aktivasi: Enter. | Teks + “link, tekan Enter” ([JAWS](https://tetralogical.github.io/screen-reader-HTML-support/JAWS.html)). | Teks + “link, ketuk dua kali” ([TalkBack](https://tetralogical.github.io/screen-reader-HTML-support/TalkBack-android.html)). | Teks + “link”; double-tap aktivasi ([VO](https://tetralogical.github.io/screen-reader-HTML-support/VO-ios.html)). |
| `<button>` | Label + “button”; Space/Enter ([APG Button](https://www.w3.org/WAI/ARIA/apg/patterns/button/)). | Label + “button” ([JAWS](https://tetralogical.github.io/screen-reader-HTML-support/JAWS.html)). | Label + “button” ([TalkBack](https://tetralogical.github.io/screen-reader-HTML-support/TalkBack-android.html)). | Label + “button” ([VO](https://tetralogical.github.io/screen-reader-HTML-support/VO-ios.html)). |

**Tautan vs tombol (desain):** W3C APG memisahkan **link = navigasi ke sumber**, **button = aksi** ([Link pattern](https://www.w3.org/WAI/ARIA/apg/patterns/link/), [Button pattern](https://www.w3.org/WAI/ARIA/apg/patterns/button/)). Pengguna membangun ekspektasi dari kata “link” vs “tombol” ([WebAIM links vs buttons](https://www.washington.edu/accesstech/guide/links-buttons/)). Tuturnusa: kartu beranda dan navigasi bab = `<a>`; Mode suara / kirim pesan = `<button>`.

### 1.2 `<audio controls>` — apa yang didengar dan masalah umum

**Yang dibacakan**

- **JAWS / Narrator (Windows):** kelompok pemutar, tombol play/pause, slider seek, mute, volume; JAWS kadang membacakan karakter aneh pada beberapa label kontrol bawaan browser ([RAT: media](https://jonneal.dev/rat/media.html)).
- **NVDA:** kontrol dapat dijangkau sebagai tombol; elemen `audio` sendiri minim pengumuman kecuali ada nama aksesibel ([NVDA](https://tetralogical.github.io/screen-reader-HTML-support/NVDA.html)).
- **TalkBack:** fokus ke kontrol individual, bukan label “pemutar audio bab” kecuali `aria-label` / `aria-describedby` ([TalkBack](https://tetralogical.github.io/screen-reader-HTML-support/TalkBack-android.html)).
- **VoiceOver:** pengumuman bervariasi antar browser ([VO Mac](https://tetralogical.github.io/screen-reader-HTML-support/VO-mac.html)).

**Masalah untuk Tuturnusa (Buku Saku)**

1. **Autoplay** bertabrakan dengan suara pembaca layar (keputusan desain menolak autoplay — selaras [desain Tuturnusa](../superpowers/specs/2026-11-24-tuturnusa-tunanetra-refactor-design.md)).
2. **`aria-label` pada `<audio>` tanpa kontrol** sering **tidak terdengar sama sekali** dalam mode baca (91 kombinasi bermasalah di uji PowerMapper) ([PowerMapper audio aria-label](https://www.powermapper.com/tests/screen-readers/media/audio-aria-label/)). Gunakan **`controls`** + label yang bermakna, atau dekripsi di teks sebelum pemutar.
3. **Dua sumber suara:** TTS Buku Saku (MP3) + pembaca layar — pengguna harus menghentikan satu sebelum mendengar yang lain; tidak ada standar “ducking” otomatis di web.
4. Rekomendasi praktis: `<audio controls preload="none">` + `<h1>` judul bab terdengar dulu; teks langkah tetap tersedia di `<ol>` untuk yang tidak memutar audio.

### 1.3 Daftar `<ol>` — langkah bernomor

- Pembaca layar umumnya mengumumkan **posisi item** (“item 2 dari 8”) pada `<li>` dalam `<ol>` ([web.dev: structure](https://web.dev/learn/accessibility/structure)).
- **TalkBack** tidak memasukkan `<ol>` ke urutan geser — hanya item ([TalkBack](https://tetralogical.github.io/screen-reader-HTML-support/TalkBack-android.html)); posisi tetap dibacakan per item.
- **Hindari memecah satu langkah menjadi beberapa `<li>`**; satu aksi = satu item ([GOV.UK: steps](https://guidance.publishing.service.gov.uk/writing-to-gov-uk-standards/style-guides/a-to-z-style-guide/)).
- Untuk Buku Saku, mengganti tabel dua kolom menjadi daftar + heading (`###`) sudah selaras dengan kebutuhan ini ([desain Tuturnusa](../superpowers/specs/2026-11-24-tuturnusa-tunanetra-refactor-design.md)).

### 1.4 Heading dan tabel

- **Heading:** urutan level harus logis (jangan loncat `h1` → `h3`); pembaca layar membangun peta halaman dari heading ([web.dev](https://web.dev/learn/accessibility/structure)). Struktur Buku Saku: `h1` judul bab, `h2` “Alat dan bahan”, `h2` langkah/patokan.
- **Tabel data:** butuh `<th>`, caption jika perlu, hindari tabel layout untuk konten instruksional ([W3C Table pattern](https://www.w3.org/WAI/ARIA/apg/patterns/table/)). Tabel layout harus disembunyikan dari AT ([web.dev](https://web.dev/learn/accessibility/structure)).

### 1.5 `aria-live`

- **`polite`:** mengumumkan setelah jeda bicara — cocok untuk hasil pencarian, konfirmasi simpan, pesan status Ngobrol ([MDN](https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Reference/Attributes/aria-live)).
- **`assertive`:** memotong antrian suara — hanya error mendesak ([MDN](https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Reference/Attributes/aria-live)).
- **`aria-atomic="true"`:** seluruh isi region dibacakan ulang saat berubah ([Twenty One Media: aria-live wrapper](https://www.twenty1-media.com/blog/aria-live-wrapper-key-remount)).
- **Peringatan:** region live harus **sudah ada di DOM** sebelum teks berubah; jangan mount/unmount wrapper yang memegang `aria-live` ([panduan Livewire + aksesibilitas](https://skillsmp.com/creators/nasrulhazim/claude/skills-ui-accessibility)).

### 1.6 Pembaruan Livewire (Ngobrol)

Livewire memperbarui DOM tanpa reload penuh; pembaca layar **tidak otomatis** tahu konten baru ([diskusi Flux + live region](https://github.com/livewire/flux/discussions/441)).

**Pola yang berhasil:**

- Wrapper **stabil** dengan `aria-live="polite"` + `aria-atomic="true"`, `wire:key` pada wrapper (bukan pada wrapper live region yang diganti) ([Twenty One Media](https://www.twenty1-media.com/blog/aria-live-wrapper-key-remount)).
- Pesan error validasi: bungkus pesan error dalam live region ([GitHub #441](https://github.com/livewire/flux/discussions/441)).
- **Risiko fokus:** setelah `wire:click`, fokus bisa loncat — uji manual dengan NVDA/TalkBack; pertimbangkan `wire:ignore` hanya bila perlu (bukan default).
- **Buku Saku** (statis Blade): minim kebutuhan `aria-live`; hanya Ngobrol/Ucapan yang dinamis.

### 1.7 Masalah lintas-pembaca yang relevan untuk Tuturnusa

| Masalah | Dampak | Mitigasi |
|---------|--------|----------|
| Perilaku `<audio>` tidak konsisten | Anak TalkBack tidak mendengar “pemutar audio” | Judul bab di `<h1>`; instruksi singkat di teks: “Gunakan kontrol audio di bawah untuk mendengarkan bab.” |
| Dua TTS bersamaan | Kelelahan kognitif | Tanpa autoplay; `preload="none"` |
| Link dikira tombol (atau sebaliknya) | Ekspektasi aktivasi salah | Kartu = link; aksi = button ([APG](https://www.w3.org/WAI/ARIA/apg/patterns/button/)) |
| Livewire tanpa live region | Balasan AI / error tidak terdengar | Satu–dua region live per halaman ([ui-accessibility](https://skillsmp.com/creators/nasrulhazim/claude/skills-ui-accessibility)) |
| Tabel prinsip di PDF | Sulit dinavigasi | Sudah direncanakan → list + heading ([desain](../superpowers/specs/2026-11-24-tuturnusa-tunanetra-refactor-design.md)) |

---

## 2. Menguji aplikasi: manual dan otomatis

### 2.1 Kombinasi pembaca layar + browser (prioritas Tuturnusa)

Sesuai arahan tim dan survei pengguna ([WebAIM teknik SR](https://webaim.org/techniques/screenreader/), [Access Armada](https://www.accessarmada.com/blog/how-to-choose-a-screen-reader-for-testing-your-site/)):

| Platform | Pembaca layar | Browser | Kapan |
|----------|---------------|---------|-------|
| Windows | **NVDA** | **Firefox** (dan/u atau Chrome) | Desktop utama; ~38% pengguna SR primer ([WebAIM Survey #10](https://webaim.org/projects/screenreadersurvey10/)) |
| Windows | **JAWS** | **Chrome** | Sekolah/lab Windows; ~41% primer ([Survey #10](https://webaim.org/projects/screenreadersurvey10/)) |
| Android | **TalkBack** | **Chrome** | HP murah/SLB; TalkBack bawaan ([Google TalkBack](https://support.google.com/accessibility/android/answer/6007100?hl=en)) |
| iOS / macOS | **VoiceOver** | **Safari** | VO + Safari paling stabil di Mac ([WebAIM VoiceOver](http://www.webaim.org/articles/voiceover)) |

**Survei #10 (2024):** 86% responden desktop memakai Windows; pengguna SR di Asia lebih sering memakai NVDA daripada JAWS ([WebAIM Survey #10](https://webaim.org/projects/screenreadersurvey10/)). Di mobile, **70,6%** pengguna SR memakai iOS sebagai platform mobile utama — tetapi Android tetap penting untuk populasi Indonesia ([Survey #10](https://webaim.org/projects/screenreadersurvey10/)).

### 2.2 Alur uji manual (checklist Tuturnusa)

**Persiapan NVDA** ([WebAIM NVDA](https://webaim.org/articles/nvda/)):

- Instal NVDA; `Ctrl+Alt+N` untuk mulai; Speech Viewer untuk melihat teks yang dibacakan.
- Jangan ubah pengaturan yang mengubah volume informasi (mis. level tanda baca).

**Beranda `/`:**

1. Tab dari atas: skip link (jika ada) → toggle Mode suara → logo → tiga tautan kartu ([desain](../superpowers/specs/2026-11-24-tuturnusa-tunanetra-refactor-design.md)).
2. `INSERT+F7` → daftar tautan: tepat tiga kartu + footer.
3. `H` → satu `h1` “Mau belajar apa hari ini?” + `h2` per kartu.
4. Setiap kartu terdengar sebagai **link**, bukan tombol.

**Buku Saku — halaman bab:**

1. `H` → hierarki `h1` → `h2`.
2. Tab ke `<audio>` → kontrol play terdengar; putar audio **manual**; jeda pembaca layar jika perlu.
3. `L` atau geser ke daftar → `<ol>` langkah; dengarkan “3 dari 8” (atau setara).
4. Tautan “Bab sebelumnya / berikutnya” deskriptif di daftar link.

**Ngobrol (Livewire):**

1. Kirim pesan → balasan AI muncul di region dengan `aria-live` atau dapat dijangkau dengan navigasi.
2. Picu error validasi → pesan error **diumumkan** ([contoh perbaikan](https://github.com/livewire/flux/discussions/441)).
3. Uji batas pesan harian → pesan ramah terdengar.

**TalkBack + Chrome Android** ([WebAIM TalkBack](https://webaim.org/articles/talkback/), [Android testing](https://developer.android.com/guide/topics/ui/accessibility/testing)):

- Aktifkan TalkBack (volume ganda atau Settings).
- Reading control **Headings** → geser bawah: outline halaman.
- Reading control **Links** / **Controls** untuk beranda dan bab.
- Workflow berulang: [accessibility.build](https://accessibility.build/guides/talkback-screen-reader-testing).

**VoiceOver + Safari (iOS)** ([WebAIM mobile VO](https://webaim.org/articles/voiceover/mobile)):

- Aktifkan Rotor: Headings, Links, Form Controls.
- Dua jari geser bawah: baca dari atas.
- Uji tanpa melihat layar (Screen Curtain opsional) ([WET checklist](https://wet-boew.github.io/wet-boew/demos/checklist/checklist-en.html)).

### 2.3 Alat otomatis

| Alat | Kegunaan | Batasan |
|------|----------|---------|
| **axe-core** | Label hilang, kontras, ARIA invalid, alt kosong ([Playwright a11y](https://playwright.dev/docs/accessibility-testing)) | ~30–50% masalah WCAG yang terdeteksi otomatis; tidak menilai makna alt atau urutan baca bermakna ([ZeroExp](https://zeroexp.dev/library/accessibility-testing-axe), [FEE-1006](https://alivedise.github.io/frontend-engineering-essentials/1006)) |
| **@axe-core/playwright** | Scan per route/state dalam `php artisan test` + Playwright; `withTags(['wcag2a','wcag2aa'])` ([Playwright](https://playwright.dev/docs/accessibility-testing)) | Sama seperti axe; tunggu UI (mis. menu terbuka) sebelum `analyze()` |
| **Lighthouse** (Chrome DevTools) | Regresi cepat di CI lokal | Skor 100 ≠ aksesibel; tidak mensimulasikan SR, fokus, atau alur tugas ([WCAG.World](https://www.wcag.world/blog/why-your-perfect-lighthouse-score-still-fails-real-users), [Chrome Lighthouse 11](https://developer.chrome.com/blog/lighthouse-11-0)) |
| **WAVE** (ekstensi) | Inspeksi visual + ikon masalah ([WebAIM evaluation guide](https://webaim.org/articles/evaluationguide/)) | Tetap butuh uji keyboard + SR |

**Yang tidak bisa diganti otomatis:** apakah link “Bab 3” jelas, apakah `aria-live` mengumumkan pada waktu tepat, apakah MP3 + NVDA bisa dipakai bersamaan tanpa kebingungan ([Guidepup vs axe](https://www.guidepup.dev/docs/introduction/guidepup-vs-other-accessibility-testing)).

### 2.4 Strategi uji bertingkat untuk Tuturnusa

1. **CI:** axe pada `/`, satu halaman Buku Saku, satu halaman Ngobrol (state awal).
2. **PR / sebelum rilis:** 30–45 menit NVDA Firefox + 20 menit TalkBack Chrome pada tiga alur inti.
3. **Berkala:** VoiceOver Safari pada beranda + satu bab; uji keyboard-only (Tab, Enter, Escape).

---

## 3. edge-tts untuk Bahasa Indonesia

### 3.1 Suara `id-ID`

Hanya **dua** suara neural native Indonesia di layanan Edge Read Aloud / edge-tts:

| ShortName | Gender | Catatan |
|-----------|--------|---------|
| `id-ID-GadisNeural` | Perempuan | Dipilih di desain Tuturnusa |
| `id-ID-ArdiNeural` | Laki-laki | Alternatif nada |

Daftar lengkap: `edge-tts --list-voices | grep id-ID` ([PyPI edge-tts](https://pypi.org/project/edge-tts/6.1.18/), [APIPedia](https://apipedia.id/blog/edge-tts-text-to-speech-gratis)).

Varian regional lain (Jawa, Sunda) ada di katalog Edge tetapi **bukan** target Buku Saku ([APIPedia](https://apipedia.id/blog/edge-tts-text-to-speech-gratis)).

### 3.2 Parameter `rate`, `pitch`, `volume`

Format CLI (wajib pakai `=`):

```bash
edge-tts --voice id-ID-GadisNeural --rate=-10% --pitch=+0Hz --volume=+0% \
  --text "Langkah satu. Pegang gagang sikat gigi." --write-media contoh.mp3
```

- **rate:** string persen, mis. `-10%`, `+15%` ([PyPI](https://pypi.org/project/edge-tts/6.1.18/)).
- **pitch:** Hz, mis. `-5Hz`, `+10Hz` ([APIPedia](https://apipedia.id/blog/edge-tts-text-to-speech-gratis)).
- **volume:** persen relatif ([PyPI](https://pypi.org/project/edge-tts/6.1.18/)).

Untuk anak: rate sedikit lebih pelan (`-8%` sampai `-12%`) umum dipakai; uji dengar di headphone ([contoh tuning](https://github.com/closofdev/tts-testings)).

### 3.3 SSML

**SSML kustom dihapus** sejak edge-tts 5.0.0 — Microsoft memblokir SSML di luar template internal ([PyPI edge-tts](https://pypi.org/project/edge-tts/6.1.18/), [README rany2/edge-tts](https://github.com/rany2/edge-tts/)). Hanya kendali lewat `rate`/`pitch`/`volume` dan **teks biasa**.

Implikasi untuk skrip `tts.py`:

- Jangan mengandalkan `<break>`, `<prosody>`, atau `<say-as>`.
- Gunakan tanda baca, koma, dan baris baru untuk jeda.
- Langkah bernomor: tulis **“Langkah satu,”**, **“Langkah dua,”** di teks (bukan hanya `1.` di Markdown mentah) agar TTS tidak membaca “satu titik” aneh.

### 3.4 Singkatan dan istilah (Buku Saku)

Mengikuti [GOV.UK / WebAIM tentang akronim](https://accessibility.blog.gov.uk/2017/02/08/advice-for-creating-content-that-works-well-with-screen-readers/):

- Jelaskan singkatan pertama kali: “odol (pasta gigi)”.
- Hindari spasi paksa antar huruf (`T U T U R`) kecuali untuk kontrak nama merek yang memang perlu dieja.
- Istilah seperti “jam 3 / jam 9” (metode arah jam di Buku Saku): tulis “jam tiga” dan “jam sembilan” dalam teks TTS.
- **Jangan** menulis fonetik tersembunyi untuk SR; MP3 dan pembaca layar beda saluran.

### 3.5 Kebijakan, risiko, dan alternatif cadangan

| Aspek | Fakta | Sumber |
|-------|-------|--------|
| Status layanan | Client Python **tidak resmi** ke endpoint Bing Read Aloud (`speech.platform.bing.com`) | [GitHub Discussion #261](https://github.com/rany2/edge-tts/discussions/261) |
| Komersial | Microsoft **tidak** mengonfirmasi hak redistribusi komersial audio Edge Read Aloud; GPL pada repo ≠ izin layanan Microsoft | [MS Q&A](https://learn.microsoft.com/en-us/answers/questions/2088770/are-opensource-edge-tts-free-for-commercial-use), [MS Q&A komersial 2025](https://learn.microsoft.com/en-us/answers/questions/6007729/commercial-use-of-microsoft-edge-text-to-speech-vi) |
| Stabilitas | Bisa berhenti/kuat dibatasi tanpa pemberitahuan; tanpa SLA | [Discussion #261](https://github.com/rany2/edge-tts/discussions/261), [Issue #199](https://github.com/rany2/edge-tts/issues/199) |
| Kasus Tuturnusa | Pre-generate MP3 sekali, commit ke repo — **bukan** TTS runtime; risiko operasional rendah, risiko lisensi tetap abu-abu untuk produk komersial skala besar | Konteks desain |

**Alternatif cadangan** (sesuai desain `ponytail:`):

1. **Google Cloud Text-to-Speech** — `id-ID-GadisNeural` / `ArdiNeural` (neural), Standard/Wavenet ([daftar suara](https://github.com/playht/text-to-speech-api/blob/master/Voices.md)); berbayar, SLA jelas.
2. **Gemini TTS** — bahasa `id-ID` GA di Cloud TTS ([Gemini TTS docs](https://docs.cloud.google.com/text-to-speech/docs/gemini-tts)); butuh API key & kepatuhan Google Cloud.
3. **Chirp 3 HD** — mendukung `id-ID` ([Chirp 3 HD](https://docs.cloud.google.com/text-to-speech/docs/chirp3-hd)).

Untuk proyek pendidikan nirlaba internal, edge-tts + MP3 tersimpan tetap masuk akal; dokumentasikan cadangan di `scripts/buku_saku/requirements.txt`.

---

## 4. Menulis konten ramah didengar (audio-first)

Prinsip ini berlaku untuk **teks Buku Saku**, **skrip edge-tts**, dan **label UI** yang dibaca pembaca layar.

### 4.1 Kalimat dan paragraf

- Target **≤ 25 kata per kalimat**; pecah kalimat panjang ([GOV.UK clear language](https://guidance.publishing.service.gov.uk/writing-to-gov-uk-standards/writing-guidelines/clear-language/), [Publishing accessible documents](https://www.gov.uk/guidance/publishing-accessible-documents)).
- Paragraf pendek (≈ ≤ 5 kalimat) ([GOV.UK clear language](https://guidance.publishing.service.gov.uk/writing-to-gov-uk-standards/writing-guidelines/clear-language/)).
- **Kalimat aktif:** “Pegang gagang sikat gigi dengan tangan kanan” bukan “Gagang sikat gigi harus dipegang…” ([GOV.UK](https://guidance.publishing.service.gov.uk/writing-to-gov-uk-standards/writing-guidelines/clear-language/)).
- Satu instruksi = satu kalimat dalam satu langkah bernomor ([GOV.UK steps](https://guidance.publishing.service.gov.uk/writing-to-gov-uk-standards/style-guides/a-to-z-style-guide/)).

### 4.2 Struktur bab (selaras PDF Buku Saku)

Contoh alur dari *Buku Saku Mandiri*:

1. **Pembuka** (opsional di `00-pengantar.md`): cara memakai buku — alat, langkah, patokan indra.
2. **Alat dan bahan** — daftar pendek atau paragraf; hindari tabel dua kolom.
3. **Langkah** — `<ol>`; 5–10 langkah ideal untuk materi baru ([Redish via GOV.UK](https://www.gov.uk/government/publications/govuk-content-principles-conventions-and-research-background/govuk-content-principles-conventions-and-research-background)).
4. **Patokan indra** — satu paragraf penutup.

Di MP3, pertahankan urutan yang sama agar pendengar bisa mengikuti tanpa melihat layar.

### 4.3 Memberi tahu posisi (“langkah 3 dari 8”)

- **HTML:** `<ol>` sudah memberi konteks posisi via pembaca layar ([web.dev](https://web.dev/learn/accessibility/structure)).
- **MP3 (tanpa SSML):** di awal blok langkah, opsional satu kalimat: “Bagian langkah. Delapan langkah.” Lalu tiap item diawali “Langkah tiga,” dalam teks TTS.
- **UI aplikasi:** untuk alur multi-langkah di luar Buku Saku (mis. Ucapan), pertimbangkan teks status di `aria-live`: “Langkah 3 dari 8” — jangan mengandalkan warna saja.

### 4.4 Pembaca layar vs audio pra-rekam

- Tulis konten **jelas untuk keduanya**; jangan mengoptimalkan hanya untuk TTS ([GOV.UK SR advice](https://accessibility.blog.gov.uk/2017/02/08/advice-for-creating-content-that-works-well-with-screen-readers/)).
- Hindari petunjuk visual murni (“lihat gambar”); Buku Saku sudah berbasis raba/pendengaran ([PDF Buku Saku di repo]).
- Tautan deskriptif: “Bab berikutnya: Membedakan botol sabun dan sampo” bukan “klik di sini” ([GOV.UK](https://www.gov.uk/guidance/publishing-accessible-documents)).

---

## 5. Konteks Indonesia: kebijakan, SLB, dan perangkat

### 5.1 Kebijakan dan pedoman aksesibilitas digital

| Topik | Ringkasan | Sumber |
|-------|-----------|--------|
| Dasar hukum | UUD 1945 Pasal 28F; UU No. 8/2016 hak informasi melalui media mudah diakses | [InfoPublik 2025](https://infopublik.id/kategori/nasional-sosial-budaya/940152/menuju-layanan-digital-inklusif-pemerintah-dorong-website-ramah-disabilitas) |
| WCAG di pemerintahan | Acuan **ISO/IEC 40500 / WCAG 2.2** untuk website layanan publik | [InfoPublik](https://infopublik.id/kategori/nasional-sosial-budaya/940152/menuju-layanan-digital-inklusif-pemerintah-dorong-website-ramah-disabilitas), [JDIH Komdigi 2025](https://jdih.komdigi.go.id/berita/view/111) |
| Regulasi khusus digital | **Belum** ada UU/PP yang mewajibkan WCAG untuk swasta; pedoman Kominfo/Komdigi dalam penyusunan (OGP commitment) | [Aksesibel.id](https://aksesibel.id/aksesibilitas/penerapan/digital), [OGP ID0141](https://www.opengovpartnership.org/members/indonesia/commitments/ID0141/) |
| Pendidikan inklusif | Panduan Kemendikdasmen: materi digital + pembaca layar, Braille, audiobook | [Panduan Pendidikan Inklusif (PDF)](https://kurikulum.kemendikdasmen.go.id/file/Panduan%20Penyelenggaraan%20Pendidikan%20Inklusif%20(Final%20Result).pdf) |

Tuturnusa tidak berstatus layanan publik pemerintah, tetapi mengikuti WCAG 2.1/2.2 AA adalah selaras dengan arah Kemendikdasmen dan praktik TKA (soal dengan screen reader, minim gambar) ([ANTARA TKA](https://www.antaranews.com/berita/5376470/kemendikdasmen-siapkan-teknologi-agar-murid-disabilitas-bisa-ikut-tka)).

### 5.2 SLB dan pendidikan tunanetra

- **SLB Negeri / SLB-A** (contoh Pembina Jakarta, Trituna Subang) memakai **Interactive Flat Panel (IFP)** Android dengan **TalkBack** untuk murid tunanetra ([Kemendikdasmen PKPLK](https://pkplk.kemendikdasmen.go.id/fitur-ramah-disabilitas-papan-interaktif-digital-mudahkan-belajar-murid-tunanetra/), [SLB Trituna](https://pkplk.kemendikdasmen.go.id/digitalisasi-ifp-dukung-proses-pembelajaran-di-slb-negeri-trituna-subang/)).
- Panduan **MDVI 2025** menyebut komputer bicara (screen reader), JAWS di PC, dan TalkBack di layar sentuh ([PDF MDVI](https://pkplk.kemendikdasmen.go.id/wp-content/uploads/2025/12/6.-PM_MDVI.pdf)).
- Kebutuhan belajar tunanetra: deskripsi verbal, waktu tambahan, strategi multisensori ([Panduan Inklusif PDF](https://kurikulum.kemendikdasmen.go.id/file/Panduan%20Penyelenggaraan%20Pendidikan%20Inklusif%20(Final%20Result).pdf)).

### 5.3 Perangkat dan pembaca layar yang umum dipakai anak di Indonesia

Data empiris **khusus Indonesia** jarang; gabungan konteks lokal + survei global:

| Konteks | Perkiraan |
|---------|-----------|
| **Windows di lab/sekolah** | NVDA (gratis) dan JAWS (lisensi sekolah/CSR) — disebut eksplisit di panduan Kemendikdasmen ([MDVI PDF](https://pkplk.kemendikdasmen.go.id/wp-content/uploads/2025/12/6.-PM_MDVI.pdf)) |
| **Android (HP murah, IFP sekolah)** | TalkBack + Chrome — dominan di perangkat Android sekolah ([PKPLK IFP](https://pkplk.kemendikdasmen.go.id/fitur-ramah-disabilitas-papan-interaktif-digital-mudahkan-belajar-murid-tunanetra/)) |
| **iOS** | VoiceOver + Safari — pengguna SR global lebih sering memakai iPhone ([WebAIM Survey #10](https://webaim.org/projects/screenreadersurvey10/)) |
| **Low vision** | Zoom sistem, kontras tinggi — bukan fokus Tuturnusa (target buta), tetapi tetap hindari kontras buruk |

Untuk anak tunanetra Indonesia, **uji minimal: TalkBack + Chrome (Android) dan NVDA + Firefox (Windows)**; tambahkan VoiceOver jika sekolah memakai iPad.

---

## Rekomendasi untuk Tuturnusa

1. **Pertahankan pola Buku Saku:** `h1` judul → `<audio controls preload="none">` tanpa autoplay → teks dengan `<ol>` langkah; beri `aria-label` bermakna pada audio (mis. “Audio narasi bab: Menyikat gigi mandiri”).
2. **Ganti semua tabel instruksional** (prinsip, persiapan) menjadi heading + paragraf/list, sesuai desain — tabel hanya jika benar-benar data tabular dengan `<th>`.
3. **Beranda:** tepat tiga `<a>` dalam `<ul>`; judul kartu di `<h2>`; nama tautan unik dan deskriptif (cocok untuk daftar link NVDA/TalkBack).
4. **Pisahkan peran:** navigasi = link; Mode suara, kirim pesan, putar ulang = button — selaras [W3C APG](https://www.w3.org/WAI/ARIA/apg/patterns/button/).
5. **Livewire Ngobrol:** satu wrapper `aria-live="polite"` `aria-atomic="true"` yang **tidak** di-unmount saat update; daftar pesan baru/error validasi masuk ke region itu ([Twenty One Media](https://www.twenty1-media.com/blog/aria-live-wrapper-key-remount)).
6. **Jangan autoplay MP3** saat halaman bab dibuka; instruksikan lewat teks satu kalimat agar anak memilih audio atau pembaca layar.
7. **Skrip TTS:** suara `id-ID-GadisNeural`, `rate` −8% s.d. −12%; teks plain dengan “Langkah satu,”; tanpa SSML; review manual 2–3 bab per buku sebelum commit MP3.
8. **Uji manual wajib** sebelum rilis: NVDA+Firefox dan TalkBack+Chrome pada beranda, satu bab Buku Saku, satu sesi Ngobrol; Tab order sesuai desain (skip link → mode suara → logo → kartu).
9. **CI:** `@axe-core/playwright` pada rute utama; Lighthouse hanya sebagai regresi, bukan gate tunggal ([Playwright](https://playwright.dev/docs/accessibility-testing), [WCAG.World](https://www.wcag.world/blog/why-your-perfect-lighthouse-score-still-fails-real-users)).
10. **Dokumentasikan risiko edge-tts** di skrip Python; sediakan jalur migrasi ke Cloud TTS/Gemini jika layanan Bing berubah ([rany2 #261](https://github.com/rany2/edge-tts/discussions/261)).
11. **Halaman `/bantuan`:** daftar pintasan SR umum (NVDA/TalkBack) dan penjelasan dua saluran audio (pembaca layar vs narasi bab).
12. **Konten Bahasa Indonesia:** kalimat pendek, langkah bernomor, jelaskan singkatan; hindari petunjuk visual; uji dengan Speech Viewer NVDA untuk memastikan tidak ada kata serapan yang terbaca aneh.

---

### Referensi desain internal

- [Konteks orkestra](../orchestra/00-konteks-bersama.md)
- [Spesifikasi refactor tunanetra](../superpowers/specs/2026-11-24-tuturnusa-tunanetra-refactor-design.md)
- PDF: *Buku Saku Mandiri* (15 bab), *Buku Saku Senam Dasar* (12 gerakan) — root repository