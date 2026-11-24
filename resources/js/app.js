import './bootstrap';
import './pwa';
import '@fontsource-variable/inter';
import '@fontsource/caveat/600.css';
import './voice/engine';
import './voice/adapters';

/**
 * Web Speech API text-to-speech. lang: 'zh-CN' for Mandarin, 'en-US' for English.
 * Browser-only API — no backend round-trip (spec section 8/10).
 */
window.speak = function (text, lang = 'en-US', onEnd = null, rate = null) {
    if (!('speechSynthesis' in window) || !text) {
        if (onEnd) onEnd();
        return;
    }
    window.speechSynthesis.cancel();
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = lang;
    utterance.rate = rate ?? (lang.startsWith('zh') ? 0.85 : 1);
    if (onEnd) {
        utterance.onend = onEnd;
        utterance.onerror = onEnd;
    }
    window.speechSynthesis.speak(utterance);
};

/**
 * Alpine component: voice input for the Conversation Partner, built on the
 * (Chrome/Edge-only) Web Speech API. Manual stop (continuous: true) — the
 * user taps the mic to start and taps it again to stop and send, so a
 * mid-sentence pause never cuts them off or sends early. Whatever final
 * transcript comes back is sent untouched: a consistently mis-heard word is
 * itself useful signal about a pronunciation the user should drill.
 *
 * States: idle -> listening -> processing -> idle, with a separate
 * `speaking` state driven by the 'ai-replied' event dispatched from the
 * Livewire component after every reply (both voice- and text-originated).
 */
window.voiceInput = function (langCode) {
    return {
        langCode,
        supported: false,
        state: 'idle', // idle | listening | processing | speaking
        interimText: '',
        micError: null,
        recognition: null,
        finalTranscript: '',

        init() {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            this.supported = !!SpeechRecognition;
            if (!this.supported) return;

            this.recognition = new SpeechRecognition();
            this.recognition.lang = this.langCode;
            this.recognition.interimResults = true;
            this.recognition.continuous = true;

            this.recognition.onresult = (event) => {
                let interim = '';
                for (let i = event.resultIndex; i < event.results.length; i++) {
                    const transcript = event.results[i][0].transcript;
                    if (event.results[i].isFinal) {
                        this.finalTranscript += (this.finalTranscript ? ' ' : '') + transcript;
                    } else {
                        interim += transcript;
                    }
                }
                this.interimText = interim;
            };

            this.recognition.onerror = (event) => {
                // 'aborted' fires from our own stopListening() call — not a real error.
                if (event.error === 'not-allowed') {
                    this.micError = 'Izin microphone ditolak. Aktifkan izin mic di pengaturan browser untuk pakai voice input.';
                } else if (event.error === 'no-speech') {
                    this.micError = 'Gak kedengeran suara. Coba lagi.';
                } else if (event.error !== 'aborted') {
                    this.micError = 'Voice input gagal, coba lagi.';
                }
                this.finalTranscript = '';
                this.interimText = '';
                this.state = 'idle';
            };

            this.recognition.onend = () => {
                if (this.state !== 'listening') return; // already handled by onerror
                this.interimText = '';
                const text = this.finalTranscript.trim();
                this.finalTranscript = '';

                if (!text) {
                    this.state = 'idle';
                    return;
                }

                this.state = 'processing';
                this.$wire.sendMessage(text).then(() => {
                    this.state = 'idle';
                });
            };
        },

        startListening() {
            if (!this.supported || this.state !== 'idle') return;
            this.micError = null;
            this.interimText = '';
            this.finalTranscript = '';
            this.state = 'listening';
            this.recognition.start();
        },

        stopListening() {
            if (this.state !== 'listening') return;
            this.recognition.stop();
        },

        toggleListening() {
            this.state === 'listening' ? this.stopListening() : this.startListening();
        },

        handleAiReply(text, replyLangCode) {
            // Screen reader mode: never talk over the screen reader; replies play only via their play button.
            if (!this.$wire.autoPlay || window.screenReaderMode()) return;
            this.state = 'speaking';
            speak(text, replyLangCode, () => {
                this.state = 'idle';
            });
        },
    };
};

/**
 * Alpine component for a modal dialog element: on open, remembers the control
 * that opened it and moves focus inside; on removal (Alpine destroy), returns
 * focus to that control. Escape handling is wired per dialog in the markup.
 */
window.modalDialog = function () {
    return {
        trigger: null,
        init() {
            this.trigger = document.activeElement;
            this.$nextTick(() => {
                const target = this.$el.querySelector('[data-autofocus]') || this.$el.querySelector('button, a[href], input, select, textarea');
                if (target) target.focus();
            });
        },
        destroy() {
            if (this.trigger && document.contains(this.trigger)) this.trigger.focus();
        },
    };
};

/** True when the "Mode pembaca layar" display setting is on (class set on <html>). */
window.screenReaderMode = function () {
    return document.documentElement.classList.contains('mode-pembaca-layar');
};

/**
 * Alpine component: header "Tampilan" panel. Persists to localStorage key
 * 'tampilan' and mirrors the settings as classes on <html> (the same classes
 * the inline <head> script in layouts/study.blade.php applies on page load).
 */
/** Alpine: kontrol audio bab Buku Saku (tanpa kontrol native). */
window.chapterAudio = function (src, durasiAwal = '0:00') {
    return {
        memutar: false,
        status: '',
        posisiTeks: '',
        progres: 0,
        durasiAwal,
        init() {
            const el = this.$refs.pemutar;
            const perbarui = () => {
                const total = Number.isFinite(el.duration) && el.duration > 0 ? el.duration : 0;
                const totalTeks = total > 0 ? this.format(total) : this.durasiAwal;
                this.posisiTeks = `${this.format(el.currentTime)} dari ${totalTeks}`;
                this.progres = total > 0 ? Math.min(100, (el.currentTime / total) * 100) : 0;
            };
            this.posisiTeks = `0:00 dari ${this.durasiAwal}`;
            el.addEventListener('timeupdate', perbarui);
            el.addEventListener('loadedmetadata', perbarui);
            el.addEventListener('play', () => {
                this.memutar = true;
                this.status = 'Narasi diputar.';
            });
            el.addEventListener('pause', () => {
                this.memutar = false;
                if (!el.ended) {
                    this.status = 'Narasi dijeda.';
                }
            });
            el.addEventListener('ended', () => {
                this.memutar = false;
                this.status = 'Narasi selesai.';
            });
            if (src) {
                el.src = src;
            }
        },
        toggle() {
            const el = this.$refs.pemutar;
            if (el.paused) {
                el.play();
            } else {
                el.pause();
            }
        },
        mundur() {
            const el = this.$refs.pemutar;
            el.currentTime = Math.max(0, el.currentTime - 10);
        },
        maju() {
            const el = this.$refs.pemutar;
            el.currentTime = Math.min(el.duration || el.currentTime + 10, el.currentTime + 10);
        },
        format(detik) {
            if (!Number.isFinite(detik)) {
                return '0:00';
            }
            const m = Math.floor(detik / 60);
            const s = Math.floor(detik % 60);

            return `${m}:${String(s).padStart(2, '0')}`;
        },
    };
};

window.displaySettings = function () {
    let saved = {};
    try {
        saved = JSON.parse(localStorage.getItem('tampilan') || '{}');
    } catch (e) {}

    return {
        ukuran: saved.ukuran || 'normal',
        pembacaLayar: !!saved.pembacaLayar,
        lega: !!saved.lega,

        init() {
            // Mode suara and Mode pembaca layar are mutually exclusive (see voice/engine.js).
            window.addEventListener('mode-pembaca-layar-mati', () => (this.pembacaLayar = false));
        },

        simpan() {
            if (this.pembacaLayar) window.dispatchEvent(new CustomEvent('mode-pembaca-layar-aktif'));
            const kelas = document.documentElement.classList;
            kelas.toggle('teks-besar', this.ukuran === 'besar');
            kelas.toggle('teks-sangat-besar', this.ukuran === 'sangat-besar');
            kelas.toggle('mode-pembaca-layar', this.pembacaLayar);
            kelas.toggle('teks-lega', this.lega);
            if (this.pembacaLayar && 'speechSynthesis' in window) window.speechSynthesis.cancel();
            try {
                // Merge into what is already stored so other keys in 'tampilan' survive.
                let current = {};
                try {
                    current = JSON.parse(localStorage.getItem('tampilan') || '{}') || {};
                } catch (e) {}
                localStorage.setItem('tampilan', JSON.stringify({
                    ...current,
                    ukuran: this.ukuran,
                    pembacaLayar: this.pembacaLayar,
                    lega: this.lega,
                }));
            } catch (e) {}
        },
    };
};

/**
 * Alpine component: records a mic clip for the Pronunciation Checker page
 * (App\Livewire\PronunciationCheck) via MediaRecorder, uploads the raw audio
 * blob to the Livewire component's $audioFile property, and — best-effort,
 * only when the browser supports it — also captures a Web Speech API
 * transcript alongside it. The transcript is passed to checkPronunciation()
 * purely so SttMatchPronunciationChecker has something to compare against
 * when it's used as a fallback; the ML checker ignores it and analyzes the
 * uploaded audio directly.
 *
 * mediaRecorder.onstop and recognition's final onresult/onend are two
 * independent async event streams with no ordering guarantee — logs showed
 * onstop firing and reading finalTranscript before the engine's own onresult
 * had delivered it. upload() is therefore gated on BOTH having fired
 * (readyToUpload()), not on mediaRecorder.onstop alone. A timeout guards the
 * case where recognition never fires onend at all (unsupported browser,
 * engine glitch) so the UI can't get stuck on "Memproses...".
 */
window.pronunciationRecorder = function (lang) {
    const langCode = lang === 'mandarin' ? 'zh-CN' : 'en-US';
    const RECOGNITION_END_TIMEOUT_MS = 2000;

    return {
        state: 'idle', // idle | recording | uploading
        micError: null,
        mediaRecorder: null,
        chunks: [],
        recognition: null,
        finalTranscript: '',
        recorderStopped: false,
        recognitionEnded: false,
        recognitionEndTimer: null,

        init() {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SpeechRecognition) return;

            this.recognition = new SpeechRecognition();
            this.recognition.lang = langCode;
            this.recognition.interimResults = false;
            this.recognition.continuous = true;
            this.recognition.onresult = (event) => {
                for (let i = event.resultIndex; i < event.results.length; i++) {
                    if (event.results[i].isFinal) {
                        const transcript = event.results[i][0].transcript;
                        this.finalTranscript += (this.finalTranscript ? ' ' : '') + transcript;
                    }
                }
            };
            this.recognition.onend = () => {
                this.recognitionEnded = true;
                this.maybeUpload();
            };
            this.recognition.onerror = () => {
                // Treat as "ended" so a recognition failure never blocks the
                // upload — the recording itself still gets checked.
                this.recognitionEnded = true;
                this.maybeUpload();
            };
        },

        async startRecording() {
            if (this.state !== 'idle') return;
            clearTimeout(this.recognitionEndTimer);
            this.micError = null;
            this.finalTranscript = '';
            this.chunks = [];
            this.recorderStopped = false;
            // No recognition support means there's nothing to wait for.
            this.recognitionEnded = !this.recognition;

            let stream;
            try {
                stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch (e) {
                this.micError = 'Izin microphone ditolak. Aktifkan izin mic di pengaturan browser.';
                return;
            }

            this.mediaRecorder = new MediaRecorder(stream);
            this.mediaRecorder.ondataavailable = (e) => {
                if (e.data.size > 0) this.chunks.push(e.data);
            };
            this.mediaRecorder.onstop = () => {
                stream.getTracks().forEach((track) => track.stop());
                this.recorderStopped = true;
                this.maybeUpload();
            };
            this.mediaRecorder.onerror = () => {
                stream.getTracks().forEach((track) => track.stop());
                clearTimeout(this.recognitionEndTimer);
                this.micError = 'Perekaman gagal, coba lagi.';
                this.state = 'idle';
            };
            this.mediaRecorder.start();
            // Set before starting recognition: Chrome throws "recognition has
            // already started" if a previous session hasn't fully torn down
            // yet, and recording must still work (via MediaRecorder alone)
            // even when that happens — the button must never get stuck
            // because a Web Speech API call threw.
            this.state = 'recording';
            if (this.recognition) {
                try {
                    this.recognition.start();
                } catch (e) {
                    this.recognitionEnded = true;
                }
            }
        },

        stopRecording() {
            if (this.state !== 'recording') return;
            this.state = 'uploading';
            if (this.recognition && !this.recognitionEnded) {
                this.recognitionEndTimer = setTimeout(() => {
                    this.recognitionEnded = true;
                    this.maybeUpload();
                }, RECOGNITION_END_TIMEOUT_MS);
                try {
                    this.recognition.stop();
                } catch (e) {
                    clearTimeout(this.recognitionEndTimer);
                    this.recognitionEnded = true;
                }
            }
            this.mediaRecorder.stop();
        },

        toggleRecording() {
            // 'uploading' is deliberately not a valid toggle target: a second
            // tap while the first attempt is still being checked is ignored
            // rather than starting a new recording on top of it.
            if (this.state === 'uploading') return;
            this.state === 'recording' ? this.stopRecording() : this.startRecording();
        },

        maybeUpload() {
            if (!this.recorderStopped || !this.recognitionEnded) return;
            clearTimeout(this.recognitionEndTimer);
            this.upload();
        },

        upload() {
            const file = new File(this.chunks, 'recording.webm', { type: 'audio/webm' });
            this.$wire.upload(
                'audioFile',
                file,
                () => {
                    this.$wire.checkPronunciation(this.finalTranscript.trim())
                        .then(() => {
                            this.state = 'idle';
                        })
                        .catch(() => {
                            // Without this, a failed component call (network hiccup, expired
                            // session, ...) left the button stuck on "Memproses..." forever
                            // with no feedback at all — the recording finished, but the user
                            // never saw a verdict or an error.
                            this.micError = 'Gagal memverifikasi pengucapan, coba lagi.';
                            this.state = 'idle';
                        });
                },
                () => {
                    this.micError = 'Upload rekaman gagal, coba lagi.';
                    this.state = 'idle';
                },
            );
        },
    };
};


/** Alpine component: Hanzi writing canvas with a 田字格 grid + faint reference character. */
window.hanziCanvas = function (character) {
    return {
        character,
        drawing: false,
        init() {
            this.ctx = this.$refs.canvas.getContext('2d');
            this.resize();
            this.drawGrid();
        },
        resize() {
            const canvas = this.$refs.canvas;
            const size = canvas.parentElement.clientWidth;
            canvas.width = size;
            canvas.height = size;
            this.drawGrid();
        },
        drawGrid() {
            const ctx = this.ctx;
            const size = this.$refs.canvas.width;
            ctx.clearRect(0, 0, size, size);

            ctx.strokeStyle = '#E7E0CC';
            ctx.lineWidth = 1;
            ctx.strokeRect(0, 0, size, size);
            ctx.beginPath();
            ctx.moveTo(size / 2, 0); ctx.lineTo(size / 2, size);
            ctx.moveTo(0, size / 2); ctx.lineTo(size, size / 2);
            ctx.setLineDash([4, 4]);
            ctx.stroke();
            ctx.setLineDash([]);

            if (this.character) {
                ctx.fillStyle = 'rgba(43, 54, 72, 0.15)';
                ctx.font = `${size * 0.75}px sans-serif`;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(this.character, size / 2, size / 2 + size * 0.03);
            }

            ctx.strokeStyle = '#2B3648';
            ctx.lineWidth = 3;
            ctx.lineCap = 'round';
        },
        pos(e) {
            const rect = this.$refs.canvas.getBoundingClientRect();
            const point = e.touches ? e.touches[0] : e;
            return { x: point.clientX - rect.left, y: point.clientY - rect.top };
        },
        startDraw(e) {
            this.drawing = true;
            const { x, y } = this.pos(e);
            this.ctx.beginPath();
            this.ctx.moveTo(x, y);
        },
        draw(e) {
            if (!this.drawing) return;
            e.preventDefault();
            const { x, y } = this.pos(e);
            this.ctx.lineTo(x, y);
            this.ctx.stroke();
        },
        endDraw() {
            this.drawing = false;
        },
        clear() {
            this.drawGrid();
        },
    };
};

