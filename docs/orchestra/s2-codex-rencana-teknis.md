# Tugas S2 (Codex): rencana teknis eksekusi multi-agent
Rancangan sistem S1-mu (`docs/superpowers/specs/2026-11-24-tuturnusa-rancangan-sistem.md`) DITERIMA oleh Claude dengan koreksi berikut, yang mengalahkan isi S1:
1. **Font dan ikon**: user memilih **Inter Variable** self-host (`@fontsource-variable/inter`) menggantikan Plus Jakarta Sans. Isi teks 400, judul minimal 600. Ikon outline **Lucide** (MIT), hanya yang dipakai, sebagai sprite SVG inline `<svg class="o-ic" aria-hidden="true"><use href="#i-NAME"/></svg>` (lihat akhir rancangan `...-refactor-design.md`).
2. **Data seed**: `storage/app/seed-data/{mandarin_hsk1to6_complete,irregular_verbs_183_complete,english_vocab_cefr_10674}.json` tidak ada di git (ada di `files (1)/`, 1.6MB / 35KB / 12MB). Dengan file itu, 724 tes lolos; tanpanya 48 tes gagal. Ucapan bergantung pada tabel ini. Rencanakan solusi supaya CI GitHub Actions bisa lulus tanpa commit file 12MB (mis. fixture kecil untuk tes, dan tes yang benar-benar butuh data penuh di-skip dengan pesan jelas bila file tidak ada). Pilih yang paling sederhana dan jelaskan.
3. Selebihnya S1 diikuti, termasuk semua [usulan Codex].

## Yang harus kamu tulis
HANYA `docs/superpowers/plans/2026-11-24-tuturnusa-refactor.md`, sebuah rencana implementasi untuk TIGA agent yang bekerja paralel di worktree terpisah lalu di-merge oleh Claude:
- **Codex** (kamu): pekerjaan backend yang paling rumit/berisiko.
- **Cursor Composer**: pekerjaan UI/Blade/CSS/JS dan konten.
- **Antigravity (Gemini)**: pekerjaan terisolasi seperti script Python Buku Saku dan tes.

Format:
- Bagi menjadi **gelombang (wave)**. Tugas dalam satu gelombang TIDAK BOLEH menyentuh file yang sama (tulis daftar file yang dimiliki tiap tugas) supaya merge bebas konflik. Gelombang berikutnya mulai setelah Claude merge dan CI hijau.
- Tiap tugas: id (mis. W1-A), agent, tujuan, file yang dibuat/diubah/dihapus, langkah TDD (tes gagal dulu → implementasi → tes lulus), perintah verifikasi persis, dan kriteria selesai.
- Gelombang terakhir sebelum QA: E2E Playwright + `@axe-core/playwright` (rencanakan file `tests/e2e/`, config, dan `scripts/e2e.sh`), lalu tambahkan job e2e ke `.github/workflows/ci.yml`.
- Gerbang setiap tugas: `bash scripts/ci.sh` hijau di worktree agent.
- Jangan menulis kode produk sekarang. Jangan commit.
