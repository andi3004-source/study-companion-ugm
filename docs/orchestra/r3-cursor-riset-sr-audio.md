# Tugas R3 (Cursor Composer): riset kompatibilitas pembaca layar, audio, dan konten anak
Baca `docs/orchestra/00-konteks-bersama.md` dan rancangan di `docs/superpowers/specs/`. Lihat 2 PDF Buku Saku di root repo (pakai `pdftotext`).

Riset di web, lalu tulis HANYA file `docs/research/03-pembaca-layar-audio-konten.md`. Isinya:
1. Perilaku NVDA, JAWS, TalkBack, dan VoiceOver terhadap: `<audio controls>`, daftar `<ol>`, heading, tabel, `aria-live`, tombol vs tautan, dan elemen Livewire yang diperbarui. Apa yang dibacakan dan di mana masalahnya? Sertakan URL.
2. Cara menguji aplikasi dengan pembaca layar secara praktis (NVDA + Firefox/Chrome, TalkBack + Chrome Android, VoiceOver + Safari), plus alat otomatis (axe-core, Playwright + @axe-core/playwright, Lighthouse) dan batasannya.
3. edge-tts untuk Bahasa Indonesia: daftar suara id-ID, parameter rate/pitch/volume, apakah SSML didukung, cara membaca langkah bernomor dan singkatan dengan natural, kebijakan penggunaan atau risikonya, dan alternatif cadangan.
4. Cara menulis konten bacaan yang ramah didengar (audio-first writing): panjang kalimat, struktur bab, memberi tahu posisi ("langkah 3 dari 8").
5. Konteks Indonesia: pedoman aksesibilitas digital pemerintah/Kemendikbud, SLB, serta perangkat dan pembaca layar yang paling umum dipakai anak tunanetra di Indonesia.
Akhiri dengan "Rekomendasi untuk Tuturnusa" berupa daftar bernomor.
