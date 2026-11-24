<dialog x-ref="privasiMikrofon" class="w-[min(100%,28rem)] rounded-2xl border-2 border-navy bg-white p-6 shadow-lg backdrop:bg-white/80 open:mx-auto open:my-auto"
        aria-labelledby="privasi-mikrofon-judul" aria-describedby="privasi-mikrofon-isi"
        x-on:cancel.prevent="batalPrivasiMikrofon()" x-on:close="privasiMikrofonTerbuka = false">
    <h2 id="privasi-mikrofon-judul" class="font-display font-600 text-xl text-navy mb-3">Sebelum mikrofon dipakai</h2>
    <p id="privasi-mikrofon-isi" class="text-base text-ink mb-4">
        Suara atau rekaman kamu diproses oleh penyedia pengenalan suara atau pemeriksa ucapan. Teks percakapan dikirim ke penyedia AI untuk membalas atau menilai. Data dihapus dalam kira-kira 24 jam. Tuturnusa tidak memakai akun login.
    </p>
    <p id="privasi-mikrofon-pengumuman" role="status" class="sr-only" x-show="privasiMikrofonTerbuka" x-cloak>
        Pemberitahuan privasi mikrofon. Baca isi dialog, lalu pilih Saya mengerti untuk melanjutkan atau Batal.
    </p>
    <div class="flex flex-wrap gap-2">
        <button type="button" x-ref="setujuMikrofon" x-on:click="setujuPrivasiMikrofon()"
                class="min-h-[3.5rem] px-5 rounded-xl bg-navy text-white font-700">
            Saya mengerti, nyalakan mikrofon
        </button>
        <button type="button" x-on:click="batalPrivasiMikrofon()"
                class="min-h-[3.5rem] px-5 rounded-xl border-2 border-navy bg-white text-navy font-700">
            Batal
        </button>
    </div>
</dialog>