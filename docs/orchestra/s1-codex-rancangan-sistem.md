# Tugas S1 (Codex): rancangan sistem Tuturnusa yang baru
Baca `docs/orchestra/00-konteks-bersama.md`, rancangan `docs/superpowers/specs/2026-11-24-tuturnusa-tunanetra-refactor-design.md` (termasuk bagian "Masukan riset"), dan ketiga riset di `docs/research/`. Pelajari kode yang ada.

Tulis HANYA `docs/superpowers/specs/2026-11-24-tuturnusa-rancangan-sistem.md`. Belum ada kode dan belum ada rencana tugas. Isinya:
1. Peta sistem setelah refactor: route (tabel lengkap: method, path, nama, komponen, middleware), komponen Livewire/controller, servis, model, job, command, jadwal, config/env, aset JS, dan file konten (`resources/buku-saku/`, `public/audio/buku-saku/`, `scripts/buku_saku/`).
2. Alur data per fitur (Ngobrol tanpa akun, Ucapan, Buku Saku, Mode suara), termasuk batas kepercayaan (input pengguna, AI, file konten) dan titik rate limit.
3. Daftar penghapusan yang LENGKAP: setiap file/kelas/view/JS/tes/config/seeder/route yang dihapus, dengan bukti grep bahwa tidak dipakai lagi oleh 3 fitur yang tersisa. Juga daftar yang DIPERTAHANKAN karena masih dipakai, beserta pemakainya.
4. Kontrak antarmuka: struktur front-matter Markdown Buku Saku, format nama file MP3 dan metadata durasi (mis. `public/audio/buku-saku/manifest.json`), API `ConversationService` yang baru (signature), dan kunci session.
5. Struktur halaman dan aksesibilitas per rute: `<title>`, landmark, urutan heading, urutan fokus, live region, serta token design system (dari riset R2) yang dipetakan ke `tailwind.config.js`.
6. Strategi tes: unit/feature PHPUnit, Playwright E2E + `@axe-core/playwright`, dan uji manual pembaca layar.
7. Risiko dan mitigasinya.
Tandai setiap keputusan yang kamu buat sendiri (yang tidak ada di rancangan) dengan "[usulan Codex]".
