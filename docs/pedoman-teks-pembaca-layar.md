# Pedoman teks untuk pengguna pembaca layar

Protokol menulis kata-kata di TUTURNUSA supaya enak didengar lewat
pembaca layar (NVDA, JAWS, Narator, TalkBack, VoiceOver). Pertama dipakai di
halaman awal (`resources/views/welcome.blade.php` dan
`resources/views/components/landing/`). Halaman lain sebaiknya ikut.

Pedoman ini disusun tim, **belum diuji dengan pengguna tunanetra**. Ubah bila
hasil uji berkata lain.

## 1. Judul bagian menyebut isinya

Pengguna pembaca layar sering melompat dari judul ke judul (tombol `H` di
NVDA). Judul seperti "Mudah, terstruktur, efektif." tidak memberi tahu bagian
apa itu.

- Bila label kecil di atas judul (badge "Cara Kerja") hanya hiasan, sembunyikan
  label itu dari pembaca layar (`aria-hidden="true"`) dan taruh namanya di depan
  judul dengan `sr-only`.
- Contoh: `<h2><span class="sr-only">Cara kerja: </span>Mudah, terstruktur, efektif.</h2>`
  dibacakan "Cara kerja: Mudah, terstruktur, efektif. Judul tingkat 2".
- Satu `h1` per halaman, tingkat judul tidak dilompati.

## 2. Teks tautan dan tombol bisa dipahami tanpa melihat sekitarnya

- Hindari "klik di sini", "selengkapnya", "lihat ini".
- Tombol ikon wajib punya `aria-label` yang menyebut tindakan **dan** objeknya:
  "Dengarkan kalimat contoh Mandarin", bukan "Speaker" atau "Putar".
- Tautan logo ke beranda dibacakan "TUTURNUSA, beranda".
- Bila dua tautan berteks sama menuju tempat berbeda, bedakan teksnya.

## 3. Jangan bergantung pada posisi, warna, atau bentuk

Pembaca layar tidak tahu "di samping", "di kanan", "tombol kuning", atau "titik
hijau" (WCAG 1.3.3 dan 1.4.1).

- Tulis "contoh berikut", bukan "contoh di samping". Di layar kecil, letaknya
  juga berubah ke bawah.
- Status selalu ditulis sebagai kata ("Status: Berjalan"); titik warna hanya
  tambahan dan disembunyikan (`aria-hidden="true"`).
- "Lihat" sebagai ungkapan umum ("Lihat cara kerja") boleh, karena maknanya
  "pelajari", bukan perintah untuk memakai mata.

## 4. Simbol dan angka ditulis seperti diucapkan

Pembaca layar membaca simbol apa adanya: "~" jadi "tilde", "/" jadi "garis
miring", "&" jadi "and" dengan suara bahasa Inggris, dan "4.991" bisa dibaca
"empat koma sembilan sembilan satu" oleh suara non-Indonesia.

| Tampil | Dibacakan |
| --- | --- |
| `~16%` | sekitar 16 persen |
| `0 / 4.991` | 0 dari 4991 |
| `HSK 1–3` | HSK 1 sampai 3 |
| `Dengarkan / Ketik` | Dengarkan atau ketik |
| `Mandarin & Inggris` | Mandarin dan Inggris |

- Di teks yang kita tulis sendiri, pakai kata ("dan", "sampai", "sekitar")
  langsung.
- Bila desain memerlukan simbolnya tampil, pakai komponen
  `<x-landing.ucap tampil="~16%" baca="sekitar 16 persen" />`.
- Panah dekoratif (→) dan ikon selalu `aria-hidden="true"`.

## 5. Singkatan dijelaskan saat pertama muncul

Tulis kepanjangannya atau artinya sekali, di tempat singkatan itu pertama
muncul: "HSK (standar ujian bahasa Mandarin)", "AI (kecerdasan buatan)".
Setelah itu singkatan saja sudah cukup.

## 6. Bahasa ditandai

- Hanzi diberi `lang="zh-Hans"` supaya pembaca layar memakai suara Mandarin.
- Kalimat bahasa Inggris diberi `lang="en"`.
- **Pinyin tidak diberi `lang="zh"`.** Suara Mandarin membaca huruf Latin
  dengan kacau; suara Indonesia membaca pinyin lebih mendekati.
- Teks alt tidak bisa diberi `lang`, jadi di alt tulis pinyin dan artinya,
  bukan hanzi.

## 7. Teks alt: isi dan tujuan, singkat

- Foto yang memberi informasi: jelaskan apa yang penting dalam satu sampai dua
  kalimat, tanpa awalan "Gambar" atau "Foto".
- Ilustrasi hiasan dan logo yang sudah ditemani teks: `alt=""`.
- Tulisan yang tercetak di gambar ikut dimasukkan ke alt bila penting.

## 8. Contoh jelas disebut contoh

Bagian yang hanya memperagakan (widget "Coba Langsung") harus dibacakan sebagai
contoh, supaya pengguna tidak mengira jawabannya sendiri yang dinilai: judul
tersembunyi "Contoh tampilan latihan" dan label "Jawabanmu (contoh)".

## 9. Perubahan diumumkan, tidak diam-diam

Tombol yang mengubah sesuatu (A, A+, kontras) memakai `aria-pressed` dan
mengumumkan hasilnya lewat `role="status"`: "Ukuran teks besar.", "Mode
kontras tinggi aktif."

## 10. Jujur, tanpa klaim berlebihan

Tidak menulis "ramah disabilitas", "sepenuhnya aksesibel", atau "terbukti
membantu tunanetra" sebelum ada uji dengan pengguna. Tulis apa adanya: "Aplikasi
ini belum diuji dengan pengguna tunanetra." (Dicek oleh `WelcomePageTest`.)

## Cara mengecek

1. Nyalakan NVDA (gratis) atau Narator (Windows: Ctrl+Win+Enter).
2. Tekan `H` untuk melompat antarjudul: setiap judul harus menyebut bagiannya.
3. Tekan `Tab` dari atas: setiap tombol dan tautan harus jelas tanpa konteks.
4. Dengarkan angka, simbol, dan teks Mandarin: apakah dibacakan dengan benar?
5. Catat yang janggal, lalu perbarui pedoman ini.
