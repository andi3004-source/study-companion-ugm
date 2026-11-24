# Brief W2-A (Codex)
1. Baca `docs/orchestra/00-konteks-bersama.md`, rancangan `docs/superpowers/specs/2026-11-24-tuturnusa-tunanetra-refactor-design.md` (seluruhnya), rancangan sistem `...-rancangan-sistem.md`, dan tugas **W2-A** di `docs/superpowers/plans/2026-11-24-tuturnusa-refactor.md`. W0 dan W1 (A, C, G) sudah ter-merge di branch dasarmu.
2. Catatan integrasi dari Claude (berlaku di atas rencana):
   - View baru (`resources/views/home.blade.php`, `resources/views/buku-saku/{index,book,chapter}.blade.php`) adalah **isi halaman tanpa layout**. Rendernya harus seperti di `tests/Feature/BerandaViewTest.php`: `view('layouts.study', ['navRingkas' => true, 'slot' => new HtmlString(view('home', $data)->render())])`. Buat satu cara yang rapi untuk ini (mis. helper/controller kecil atau komponen layout), lalu pakai untuk `/` dan ketiga route Buku Saku. Jangan ubah view itu (milik Cursor, gelombang W3); kalau butuh perubahan view, catat di ringkasan.
   - `BukuSakuController` (W1-A) sekarang harus merender view asli dengan layout di atas. `tests/Feature/BukuSakuControllerTest.php` memakai view fixture dengan `prependLocation`; tambahkan `BukuSakuTest` yang menguji route asli `buku-saku`, `buku-saku.book`, `buku-saku.chapter` terhadap **materi asli** (2 buku, 16 + 14 halaman, h1, `<ol>`, `<audio preload="none">` tanpa `autoplay`, 404 untuk slug asing dan traversal).
   - Method `BukuSakuCatalog::chapter()` tidak dipakai controller: hapus atau pakai, jangan biarkan dua jalur.
   - `/` = beranda tiga kartu (nama route `home`), `/dashboard` → redirect 302 ke `/`.
3. TDD: tes gagal dulu, lalu implementasi.
4. Gerbang: `bash scripts/ci.sh` exit 0 di worktree ini (dengan tiga JSON besar ada) DAN saat tiga JSON besar dipindah sementara (kembalikan setelahnya). Jalankan juga verifikasi persis W2-A di rencana.
5. Jangan commit, jangan push. Akhiri dengan ringkasan: file dibuat/diubah/dihapus, angka tes, hasil `rg` sisa referensi, dan hal yang perlu diputuskan Claude.
