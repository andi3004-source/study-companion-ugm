export const MIKROFON_PRIVASI_KEY = 'mikrofonFiturSetuju';

export const PRIVASI_MIKROFON_TEKS =
    'Suara atau rekaman kamu diproses oleh penyedia pengenalan suara atau pemeriksa ucapan. Teks percakapan dikirim ke penyedia AI untuk membalas atau menilai. Data dihapus dalam kira-kira 24 jam. Tuturnusa tidak memakai akun login.';

/** Alpine mixin: one-time consent before Ngobrol/Ucapan open the microphone. */
export function microphonePrivasiMixin() {
    return {
        privasiMikrofonTerbuka: false,
        aksiMikrofonTertunda: null,

        sudahSetujuMikrofon() {
            try {
                return localStorage.getItem(MIKROFON_PRIVASI_KEY) === '1';
            } catch (e) {
                return false;
            }
        },

        mintaSetujuMikrofon(aksi) {
            if (this.sudahSetujuMikrofon()) {
                aksi();
                return;
            }
            this.aksiMikrofonTertunda = aksi;
            this.privasiMikrofonTerbuka = true;
            this.$nextTick(() => {
                const dialog = this.$refs.privasiMikrofon;
                if (dialog && typeof dialog.showModal === 'function' && !dialog.open) {
                    dialog.showModal();
                }
                this.$refs.setujuMikrofon?.focus();
            });
        },

        setujuPrivasiMikrofon() {
            try {
                localStorage.setItem(MIKROFON_PRIVASI_KEY, '1');
            } catch (e) {}
            this.privasiMikrofonTerbuka = false;
            const dialog = this.$refs.privasiMikrofon;
            if (dialog?.open) dialog.close();
            const aksi = this.aksiMikrofonTertunda;
            this.aksiMikrofonTertunda = null;
            if (aksi) aksi();
        },

        batalPrivasiMikrofon() {
            this.privasiMikrofonTerbuka = false;
            this.aksiMikrofonTertunda = null;
            const dialog = this.$refs.privasiMikrofon;
            if (dialog?.open) dialog.close();
        },
    };
}