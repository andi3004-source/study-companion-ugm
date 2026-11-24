# Konteks bersama untuk semua agent (Codex, Antigravity/agy, Cursor Composer)

Kamu bekerja sebagai anggota tim. Claude adalah lead yang memeriksa setiap hasil kerjamu.

## Proyek
Tuturnusa: aplikasi web Laravel 12 + Livewire 3 + Tailwind + SQLite, berbahasa Indonesia.
Rancangan yang sudah disetujui ada di `docs/superpowers/specs/2026-11-24-tuturnusa-tunanetra-refactor-design.md`. BACA DULU file itu.

## Pengguna
Anak-anak tunanetra (buta total dan low vision). Pendengaran dan fungsi kognitifnya normal.
Mereka memakai pembaca layar: NVDA atau JAWS (Windows), TalkBack (Android), juga VoiceOver (iOS/macOS).
Pengguna low vision memakai zoom/pembesaran dan kontras tinggi.

## Keputusan yang sudah final (jangan dibuka lagi)
- Hanya 3 fitur: Ngobrol (percakapan dengan AI), Ucapan (cek pelafalan), Buku Saku (bacaan + audio).
- Tidak ada landing page, login, atau daftar. Tanpa akun sama sekali.
- Buku Saku: PDF di root repo diubah menjadi Markdown, audio dibuat sekali dengan edge-tts (id-ID-GadisNeural), lalu disimpan sebagai MP3.
- Tetap Laravel (tidak pindah ke Rust).

## Aturan kerja
- Jawab dalam Bahasa Indonesia.
- Setiap klaim riset WAJIB menyertakan URL sumber. Utamakan sumber primer: W3C/WCAG, WAI-ARIA APG, dokumentasi NVDA/JAWS/TalkBack/VoiceOver, Apple/Google accessibility guidelines, GOV.UK, WebAIM, dan riset akademik.
- Jangan mengubah file di luar yang disebut di tugasmu.
- Jangan commit atau push. Claude yang memeriksa dan meng-commit.
- Skill riset tersedia di folder skills agent-mu (brainstorming-research-ideas, creative-thinking-for-research, autoresearch). Pakai kalau membantu.
