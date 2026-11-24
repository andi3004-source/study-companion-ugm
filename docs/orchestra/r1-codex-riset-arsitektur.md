# Tugas R1 (Codex): riset arsitektur aplikasi web untuk anak tunanetra
Baca `docs/orchestra/00-konteks-bersama.md` dan rancangan di `docs/superpowers/specs/`. Lalu pelajari kodenya (`routes/web.php`, `app/`, `resources/views/layouts/study.blade.php`, `resources/js/voice/`).

Riset di web, lalu tulis HANYA file `docs/research/01-arsitektur.md`. Isinya:
1. Pola arsitektur web yang terbukti ramah pembaca layar: server-rendered vs SPA, manajemen fokus saat navigasi atau update Livewire, live region, penamaan judul halaman, skip link, dan masalah umum Livewire/Alpine dengan NVDA, JAWS, dan TalkBack. Sertakan bukti dan URL.
2. Arsitektur pipeline konten audio: Markdown → teks bacaan → edge-tts → MP3. Bahas granularitas file (per bab atau per langkah), metadata durasi, caching, preload, ukuran file, dan kontrol pemutar yang aksesibel (`<audio controls>` vs pemutar kustom). Apa risiko suara audio bertabrakan dengan suara pembaca layar?
3. Aplikasi tanpa akun: sesi anonim, rate limit per IP, privasi data anak (UU PDP Indonesia, COPPA sebagai pembanding), retensi 24 jam.
4. Mode suara: pola perintah suara yang tidak bentrok dengan pembaca layar (Web Speech API, push-to-talk, hotkey).
5. Kritik rancangan kita: apa yang kurang atau berisiko? Beri rekomendasi konkret yang bisa dieksekusi, diurutkan dari yang paling berdampak.
Akhiri dengan bagian "Rekomendasi untuk Tuturnusa" berupa daftar bernomor.
