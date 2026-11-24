# QA desain Bab 1 — Claude (skill better-accessibility)

Objek: mockup Figma "Buku Saku — Bab 1 (Desktop)" (`docs/orchestra/qa/bab1-desktop.png`) dan urutan DOM di `docs/orchestra/qa-d1-desain-bab1.md`. Ini review desain, jadi belum ada kode yang diperiksa. Jalur "jadi app" dinilai oleh agent lain dengan sumber web.

## Temuan

| Severity | Lokasi | Sebelum | Sesudah | Kenapa |
| --- | --- | --- | --- | --- |
| HIGH | Kotak audio | Mockup menggambar tombol Putar 56px dan linimasa kustom, padahal spesifikasinya `<audio controls>` bawaan browser. Kontrol bawaan Chrome dan Safari kecil (sekitar 32px), bentuknya beda tiap browser, dan tidak bisa dibuat 56px. | Pilih satu. Usulan: `<audio preload="none">` **tanpa** `controls`, lalu kontrol sendiri berupa `<button>` asli: "Putar narasi" (berganti jadi "Jeda narasi", `aria-pressed`), "Mundur 10 detik", "Maju 10 detik", masing-masing ≥56px. Waktu ditampilkan sebagai teks biasa, tidak di live region (supaya tidak berisik). Hanya saat putar dan jeda yang diumumkan lewat satu `role="status"`. | Target sentuh untuk low vision (rancangan menetapkan 56px) dan konsistensi antara desain dan implementasi. Tanpa keputusan ini, implementer akan menebak. |
| MEDIUM | Meta "Bab 1 dari 15 · Bagian 1…" | Diletakkan **sebelum** `<h1>`. Pengguna pembaca layar yang lompat dengan H akan mendarat di h1 dan melewatkan posisi bab. | Pindahkan meta tepat **setelah** `<h1>`. | Struktur adalah navigasi: info posisi harus terbaca sesudah judul. |
| MEDIUM | Tombol "Mode suara: mati" | Status jadi bagian dari teks tombol. Nama aksesibelnya berubah tiap kali di-toggle. | Teks tetap "Mode suara", status lewat `aria-pressed="false/true"`, dan tampilan "mati/nyala" sebagai teks kecil di samping yang juga ada di nama (`Mode suara, mati` → biarkan `aria-pressed` yang membawa status). | Nama kontrol harus stabil; status dibawa oleh atribut, bukan nama. |
| MEDIUM | Ruang kosong (keluhan user) | Kolom 720px rata kiri di layar 1440px menyisakan ±640px kosong di kanan. Halamannya terasa berat sebelah. | Kolom dibuat di **tengah**, lebar **800px**, dan teks isi dinaikkan ke **22px** (line-height 1.6, sekitar 65–70 karakter per baris). Judul 48px. Tidak menambah kolom samping: sidebar menambah satu blok baca lagi dan memperumit urutan Tab. Lihat sketsa. | Low vision mendapat teks lebih besar dan beban mata seimbang, tanpa merusak panjang baris dan urutan baca. |
| MEDIUM | Judul "Patokan indra" | Istilah dewasa, abstrak untuk anak. | Ganti menjadi "**Cara tahu sudah benar**" (isinya tetap). | Bahasa ramah anak (riset R2 §3). Ini mengubah teks dari buku, jadi butuh persetujuan pemilik konten. |
| LOW | Kalimat penjelasan audio | 17px abu-abu `#4F5968`. | Naikkan ke 18px. | Ukuran minimal isi teks di sistem ini 18px. |
| LOW | Dua tautan "Daftar isi" (atas dan bawah) | Sama nama, sama tujuan. | Boleh tetap. Nama yang sama untuk tujuan yang sama memang sesuai WCAG 3.2.4. | Tidak masalah, dicatat supaya tidak "diperbaiki" jadi nama berbeda. |

## Sketsa desktop yang diusulkan (1440px)

```
| header: [logo TUTURNUSA]                               [Mode suara  mati] |
|                                                                          |
|            ← Beranda    ≡ Daftar isi                                     |
|            Cara Menyikat Gigi Mandiri                (h1 48px)           |
|            Bab 1 dari 15 · Kebersihan dan Perawatan Diri   (20px)        |
|            ┌──────────── Audio narasi · 3 menit 12 detik ───────────┐    |
|            │ [▶ Putar narasi] [« 10 dtk] [10 dtk »]     0:00 / 3:12  │    |
|            │ Dengarkan narasi, atau baca teks di bawah …             │    |
|            └─────────────────────────────────────────────────────────┘    |
|            Alat dan bahan                              (h2 30px)          |
|            Sikat gigi, pasta gigi …                    (22px)             |
|            Langkah-langkah                                                |
|            1. …  2. …                                                     |
|            ┌ Cara tahu sudah benar ─────────────────────────────────┐     |
|            └────────────────────────────────────────────────────────┘     |
|            [≡ Daftar isi]                 [Bab 2: Botol sabun dan sampo ›]|
|<--- 320px --->|<------------------ 800px ------------------>|<--- 320px --->|
```

## Belum diverifikasi
- Layout halaman bab di mobile 390/320px belum digambar.
- Perilaku nyata di pembaca layar (nama yang dihitung, urutan pengumuman) baru bisa dicek setelah ada kode, di tahap QA E2E dan QA manual.

**Keputusan: Block.** Satu temuan HIGH (kontrol audio) harus diputuskan sebelum W1-C mengimplementasikan halaman bab.
