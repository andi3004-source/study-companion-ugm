/**
 * "Mode suara": one shared voice layer for the whole app (Web Speech API,
 * Chrome or Edge). A complement only: the semantic markup and screen readers
 * stay the main path, and this never changes them.
 *
 * Turn taking: the microphone is never open while speech synthesis runs; a
 * short beep (Web Audio) plays right before it opens. Transcripts go only to
 * POST /voice/command as text; no audio, image or transcript is stored in
 * localStorage, sessionStorage or on the server. localStorage holds only the
 * one-time consent flag; sessionStorage holds only "aktif" so the mode
 * resumes after a page load. There are no global single-key shortcuts.
 * The last heard text lives only in memory (for "Saya dengar" and the
 * ?voicedebug=1 panel) and is dropped when the mode stops.
 */

const CONSENT_KEY = 'modeSuaraSetuju';
const SESSION_KEY = 'modeSuara';
const SILENCE_MS = 8000;
const MAX_REMINDERS = 2;
const MIN_CONFIDENCE = 0.5;
const GROUP_SIZE = 6;
const DANGEROUS = /hapus|reset|selesai dan analisis|keluar/i;

export const MESSAGES = {
    unsupported: 'Mode suara membutuhkan Chrome atau Edge',
    consent:
        'Mode suara memakai pengenalan suara bawaan browser (Chrome atau Edge). Suaramu dikirim ke penyedia browser untuk diubah menjadi teks. Aplikasi ini tidak menyimpan suara.',
    resume: 'Mode suara aktif. Tekan tombol Mode suara untuk satu perintah.',
    silenceStop: 'Mode suara berhenti karena tidak ada suara. Keyboard tetap bisa dipakai.',
    reminder: 'Saya masih mendengarkan. Katakan perintah untuk mendengar daftar perintah.',
    noZhVoice: 'Suara Mandarin belum terpasang di perangkat ini, jadi teks Mandarin mungkin tidak terbaca dengan benar.',
    errors: {
        'not-allowed': 'Izin mikrofon ditolak. Izinkan mikrofon di pengaturan browser, lalu coba lagi.',
        'service-not-allowed': 'Izin mikrofon ditolak. Izinkan mikrofon di pengaturan browser, lalu coba lagi.',
        'no-speech': 'Tidak ada suara yang terdengar.',
        network: 'Pengenalan suara butuh koneksi internet. Periksa koneksi, lalu coba lagi.',
        aborted: 'Mikrofon dihentikan.',
        'audio-capture': 'Mikrofon tidak ditemukan. Periksa mikrofon perangkat.',
    },
    help: 'Perintah: baca halaman. Daftar tindakan. Nomor satu sampai dua belas. Lanjut. Kembali. Ulangi. Buka lalu nama halaman: beranda, ngobrol, ucapan, buku saku, bantuan. Isi, untuk mengetik di kolom yang dipilih, dan selesai isi. Berhenti.',
    general: 'Di mana saja: baca halaman, daftar tindakan, nomor, buka lalu beranda, ngobrol, ucapan, buku saku, atau bantuan, kembali, ulangi, berhenti. Isi, untuk mengetik di kolom yang dipilih, dan selesai isi.',
    defaultHint: 'baca halaman, daftar tindakan, satu sampai dua belas, lanjut, kembali, ulangi, berhenti',
    unknown: 'Maaf, perintah tidak dikenali.',
};

/** "Di halaman ini Anda bisa berkata: ..." for the page's adapter (or the generic list). */
export function pageHint(adapter) {
    return `Di halaman ini Anda bisa berkata: ${adapter?.hint || MESSAGES.defaultHint}. Katakan perintah untuk daftar lengkap`;
}

/** Off unless the address has ?voicedebug=1: a small panel with what was heard and parsed, in memory only. */
const DEBUG = new URLSearchParams(window.location.search).get('voicedebug') === '1';

const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;

/** Lowercase, no punctuation, single spaces: for local matching only. */
export function normalize(text) {
    return String(text || '')
        .toLowerCase()
        .replace(/[^\p{L}\p{N}\s]+/gu, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

/** The accessible name of an element, close to what a screen reader announces. */
export function accessibleName(el) {
    if (!el) return '';
    const label = el.getAttribute('aria-label');
    if (label) return label.trim();
    const labelledby = el.getAttribute('aria-labelledby');
    if (labelledby) {
        const text = labelledby
            .split(/\s+/)
            .map((id) => document.getElementById(id)?.textContent || '')
            .join(' ');
        if (text.trim()) return text.replace(/\s+/g, ' ').trim();
    }
    if (el.id) {
        const forLabel = document.querySelector(`label[for="${CSS.escape(el.id)}"]`);
        if (forLabel) return forLabel.textContent.replace(/\s+/g, ' ').trim();
    }
    const wrapping = el.closest('label');
    if (wrapping) return wrapping.textContent.replace(/\s+/g, ' ').trim();
    const clone = el.cloneNode(true);
    clone.querySelectorAll('[aria-hidden="true"]').forEach((n) => n.remove());
    return clone.textContent.replace(/\s+/g, ' ').trim();
}

/** Visible, enabled, not inside a closed <details> or a <template>. */
export function isUsable(el) {
    if (!el || el.disabled || el.closest('[disabled], template, [aria-hidden="true"], [inert]')) return false;
    if (el.getAttribute('aria-disabled') === 'true') return false;
    if (el.closest('details:not([open])') && !el.matches('summary')) return false;
    return el.getClientRects().length > 0;
}

const VoiceMode = {
    adapters: {},
    active: false,
    listening: false,
    speaking: false,
    recognition: null,
    audioCtx: null,
    pending: null, // { text, onYes, onNo }
    actions: [],
    actionPage: 0,
    lastSpoken: [],
    reminders: 0,
    silenceTimer: null,
    dictating: null,
    warnedZh: false,
    endpoint: null,
    routes: {},
    lastHeard: '', // memory only, cleared on stop
    clarifying: null, // the two candidates after a 'clarify'
    capture: null, // { lang, onText } for the next utterance only (see captureNext)
    currentPage: null,
    observer: null,
    debugState: null,

    supported() {
        return !!Recognition && 'speechSynthesis' in window;
    },

    /** Registers a page adapter: { matches(), readPage?(), handleTranscript?(text, raw), handleCommand?(cmd), handleUnknown?(raw), onActivate?() }. */
    register(name, adapter) {
        this.adapters[name] = adapter;
    },

    pageName() {
        return document.querySelector('[data-voice-page]')?.getAttribute('data-voice-page') || null;
    },

    adapter() {
        const page = this.pageName();
        return (page && this.adapters[page]) || null;
    },

    /**
     * Calls the adapter's onActivate whenever the page under <main> changes
     * (page load, Livewire update or wire:navigate). Adapters themselves are
     * looked up from the DOM on every utterance, so they never go stale.
     */
    syncAdapter(force = false) {
        const page = this.pageName();
        if (!force && page === this.currentPage) return;
        this.currentPage = page;
        this.actions = [];
        if (this.active) this.adapter()?.onActivate?.();
    },

    /** One MutationObserver on <main>, created once, disconnected on stop and pagehide. */
    watch() {
        const main = document.querySelector('main');
        if (!main || !('MutationObserver' in window)) return;
        this.observer = this.observer || new MutationObserver(() => this.syncAdapter());
        this.observer.disconnect();
        this.observer.observe(main, { childList: true, subtree: true });
    },

    announce(text) {
        const region = document.getElementById('mode-suara-status');
        if (region) region.textContent = text;
    },

    consented() {
        try {
            return localStorage.getItem(CONSENT_KEY) === '1';
        } catch (e) {
            return false;
        }
    },

    giveConsent() {
        try {
            localStorage.setItem(CONSENT_KEY, '1');
        } catch (e) {}
    },

    remembered() {
        try {
            return sessionStorage.getItem(SESSION_KEY) === 'aktif';
        } catch (e) {
            return false;
        }
    },

    remember(on) {
        try {
            if (on) sessionStorage.setItem(SESSION_KEY, 'aktif');
            else sessionStorage.removeItem(SESSION_KEY);
        } catch (e) {}
    },

    /** Turns Mode pembaca layar off (they are mutually exclusive). */
    turnOffScreenReaderMode() {
        if (!document.documentElement.classList.contains('mode-pembaca-layar')) return false;
        document.documentElement.classList.remove('mode-pembaca-layar');
        try {
            const tampilan = JSON.parse(localStorage.getItem('tampilan') || '{}') || {};
            localStorage.setItem('tampilan', JSON.stringify({ ...tampilan, pembacaLayar: false }));
        } catch (e) {}
        window.dispatchEvent(new CustomEvent('mode-pembaca-layar-mati'));
        return true;
    },

    async start() {
        if (!this.supported()) {
            this.announce(MESSAGES.unsupported);
            return;
        }
        const srOff = this.turnOffScreenReaderMode();
        this.active = true;
        this.reminders = 0;
        this.remember(true);
        window.dispatchEvent(new CustomEvent('mode-suara', { detail: { aktif: true } }));
        const intro = (srOff ? 'Mode pembaca layar dimatikan karena Mode suara aktif. ' : '') + 'Mode suara aktif. Tekan tombol Mode suara untuk satu perintah. Katakan perintah untuk daftar perintah.';
        this.announce(intro);
        await this.say([{ text: intro, lang: 'id-ID' }]);
        this.watch();
        this.syncAdapter(true);
    },

    /** Stops in-app audio before opening the microphone (narasi bab, TTS aplikasi). */
    pauseAppAudio() {
        document.querySelectorAll('audio').forEach((el) => {
            try {
                el.pause();
            } catch (e) {}
        });
        if ('speechSynthesis' in window) window.speechSynthesis.cancel();
        this.speaking = false;
    },

    /** Stops everything: microphone, speech, timers. Safe to call any time. */
    stop(message = 'Mode suara mati.') {
        this.active = false;
        this.dictating = null;
        this.pending = null;
        this.clarifying = null;
        this.capture = null;
        this.lastHeard = '';
        this.debugState = null;
        this.remember(false);
        this.cleanup();
        window.dispatchEvent(new CustomEvent('mode-suara', { detail: { aktif: false } }));
        if (message) this.announce(message);
    },

    cleanup() {
        clearTimeout(this.silenceTimer);
        this.silenceTimer = null;
        this.observer?.disconnect();
        this.currentPage = null;
        if (this.recognition) {
            this.recognition.onresult = this.recognition.onerror = this.recognition.onend = null;
            try {
                this.recognition.abort();
            } catch (e) {}
            this.recognition = null;
        }
        this.listening = false;
        if ('speechSynthesis' in window) window.speechSynthesis.cancel();
        this.speaking = false;
        if (this.audioCtx) {
            this.audioCtx.close().catch(() => {});
            this.audioCtx = null;
        }
    },

    /** Speaks segments in order, switching voice language per segment. Resolves when done. */
    say(segments) {
        if (!('speechSynthesis' in window)) return Promise.resolve();
        this.stopListening();
        this.lastSpoken = segments;
        this.speaking = true;
        window.speechSynthesis.cancel();

        if (!this.warnedZh && segments.some((s) => s.lang === 'zh-CN')) {
            const voices = window.speechSynthesis.getVoices();
            if (voices.length && !voices.some((v) => v.lang.toLowerCase().startsWith('zh'))) {
                this.warnedZh = true;
                segments = [{ text: MESSAGES.noZhVoice, lang: 'id-ID' }, ...segments];
            }
        }

        return new Promise((resolve) => {
            const items = segments.filter((s) => s.text && String(s.text).trim());
            if (!items.length) {
                this.speaking = false;
                resolve();
                return;
            }
            items.forEach((segment, i) => {
                const u = new SpeechSynthesisUtterance(String(segment.text));
                u.lang = segment.lang || 'id-ID';
                if (u.lang.startsWith('zh')) u.rate = 0.85;
                if (i === items.length - 1) {
                    u.onend = u.onerror = () => {
                        this.speaking = false;
                        resolve();
                    };
                }
                window.speechSynthesis.speak(u);
            });
        });
    },

    /** Says text in Indonesian and also puts it in the status region. */
    async tell(text) {
        this.announce(text);
        await this.say([{ text, lang: 'id-ID' }]);
    },

    beep() {
        try {
            this.audioCtx = this.audioCtx || new (window.AudioContext || window.webkitAudioContext)();
            const osc = this.audioCtx.createOscillator();
            const gain = this.audioCtx.createGain();
            osc.frequency.value = 880;
            gain.gain.value = 0.08;
            osc.connect(gain).connect(this.audioCtx.destination);
            osc.start();
            osc.stop(this.audioCtx.currentTime + 0.12);
            return new Promise((r) => setTimeout(r, 160));
        } catch (e) {
            return Promise.resolve();
        }
    },

    stopListening() {
        clearTimeout(this.silenceTimer);
        if (this.recognition) {
            this.recognition.onresult = this.recognition.onerror = this.recognition.onend = null;
            try {
                this.recognition.abort();
            } catch (e) {}
            this.recognition = null;
        }
        this.listening = false;
    },

    /** Opens the microphone for one utterance (never while speaking). */
    async listen() {
        if (!this.active || this.speaking || this.listening) return;
        this.pauseAppAudio();
        await this.beep();
        if (!this.active || this.speaking) return;

        const rec = new Recognition();
        rec.lang = 'id-ID';
        // A feature can ask for one answer in another language, e.g. hanzi in Latihan Isian.
        if (this.capture?.lang) rec.lang = this.capture.lang;
        rec.maxAlternatives = 3;
        rec.interimResults = false;
        rec.continuous = false;
        this.recognition = rec;
        let gotResult = false;

        rec.onresult = (event) => {
            gotResult = true;
            clearTimeout(this.silenceTimer);
            const result = event.results[0];
            const alternatives = Array.from({ length: result.length }, (_, i) => ({
                transcript: result[i].transcript,
                confidence: result[i].confidence,
            }));
            this.listening = false;
            this.recognition = null;
            this.reminders = 0;
            this.onUtterance(alternatives);
        };

        rec.onerror = (event) => {
            if (event.error === 'no-speech' || event.error === 'aborted') return; // handled by the silence timer / onend
            this.stop(MESSAGES.errors[event.error] || 'Mode suara berhenti karena galat. Keyboard tetap bisa dipakai.');
        };

        rec.onend = () => {
            this.listening = false;
            this.recognition = null;
            if (!gotResult && this.active && !this.speaking) this.onSilence();
        };

        try {
            rec.start();
            this.listening = true;
            this.silenceTimer = setTimeout(() => {
                if (this.recognition === rec) rec.stop();
            }, SILENCE_MS);
        } catch (e) {
            // Browsers can refuse to open the microphone without a user gesture after a page load.
            this.listening = false;
            this.announce(MESSAGES.resume);
            document.getElementById('tombol-mode-suara')?.focus();
        }
    },

    async onSilence() {
        this.reminders++;
        if (this.reminders > MAX_REMINDERS) {
            await this.say([{ text: MESSAGES.silenceStop, lang: 'id-ID' }]);
            this.stop(MESSAGES.silenceStop);
            return;
        }
        await this.tell(MESSAGES.reminder);
    },

    /**
     * Every alternative is parsed by the server (one request); raw texts are
     * never compared. Known and consistent: act. Two different known commands:
     * numbered choice. Only the top one unknown but another known: "Saya dengar:
     * <that one>. Benar?". Low confidence on an irreversible action: confirm.
     */
    async onUtterance(alternatives) {
        const best = alternatives[0] || { transcript: '', confidence: 0 };
        const heard = best.transcript.trim();
        this.debug({ alternatives, parsed: null });
        const texts = alternatives.map((a) => String(a.transcript || '').trim().slice(0, 200)).filter(Boolean).slice(0, 3);

        // An answer the page asked for (captureNext): hand it over as text, not as a command.
        if (this.capture) {
            const { onText } = this.capture;
            this.capture = null;
            this.lastHeard = heard;
            await onText(heard, this);
            return;
        }

        // Dictation, a pending yes/no and a numbered choice take the top alternative as is.
        if (this.dictating || this.pending || this.clarifying || !texts.length) {
            await this.handle(heard);
            return;
        }

        await this.decide(texts, typeof best.confidence === 'number' && best.confidence > 0 && best.confidence < MIN_CONFIDENCE);
    },

    async decide(texts, lowConfidence) {
        const heard = texts[0];
        this.lastHeard = heard;
        const adapter = this.adapter();
        if (adapter?.handleTranscript && (await adapter.handleTranscript(normalize(heard), heard, this))) return;

        const { decision, heard_index: heardIndex, ...cmd } = await this.parse(heard, texts);
        this.debug({ parsed: { decision, ...cmd } });

        if (decision === 'choose') return this.clarify(cmd.candidates || []);
        if (decision === 'confirm' && texts[heardIndex]) return this.confirmHeard(texts[heardIndex], cmd, adapter);
        if (decision === 'accept' && lowConfidence && this.irreversible(cmd)) return this.confirmHeard(heard, cmd, adapter);

        return this.handleParsed(cmd, heard, adapter);
    },

    /** "Saya dengar: X. Benar?"; "ya" runs the command (an irreversible one still asks "Yakin?" by name). */
    confirmHeard(heard, cmd, adapter) {
        this.pending = {
            onYes: () => this.handleParsed(cmd, heard, adapter),
            onNo: () => this.tell('Baik, silakan ulangi.'),
        };
        return this.tell(`Saya dengar: ${heard}. Benar? Katakan ya atau tidak`);
    },

    /** A numbered choice that would press Hapus, Reset, Selesai dan analisis or Keluar. */
    irreversible(cmd) {
        if (cmd.type !== 'choose_number') return false;
        const actions = this.actions.length ? this.actions : this.collectActions();
        return DANGEROUS.test(actions[cmd.index]?.name || '');
    },

    async handle(raw) {
        const text = normalize(raw);
        this.lastHeard = String(raw || '').trim();

        if (this.dictating) {
            if (['selesai isi', 'berhenti isi', 'selesai ketik', 'berhenti ketik'].includes(text)) {
                const name = accessibleName(this.dictating);
                this.dictating = null;
                await this.tell(`Selesai mengisi ${name}. Teks tidak dikirim otomatis.`);
                return;
            }
            const field = this.dictating;
            field.value = (field.value ? field.value + ' ' : '') + raw.trim();
            field.dispatchEvent(new Event('input', { bubbles: true }));
            await this.tell('Ditambahkan.');
            return;
        }

        const adapter = this.adapter();
        if (adapter?.handleTranscript && (await adapter.handleTranscript(text, raw, this))) return;

        const cmd = await this.parse(raw);
        this.debug({ parsed: cmd });

        // After "Maksud Anda satu, A, atau dua, B?": "satu" or "dua" picks one.
        if (this.clarifying) {
            const options = this.clarifying;
            this.clarifying = null;
            if (cmd.type === 'choose_number' && options[cmd.index]) {
                const { label, ...chosen } = options[cmd.index];
                return this.handleParsed(chosen, raw, adapter);
            }
        }

        return this.handleParsed(cmd, raw, adapter);
    },

    async handleParsed(cmd, raw, adapter) {
        if (this.pending && (cmd.type === 'confirm_yes' || cmd.type === 'confirm_no')) {
            const pending = this.pending;
            this.pending = null;
            await (cmd.type === 'confirm_yes' ? pending.onYes() : pending.onNo());
            return;
        }
        this.pending = null;

        if (adapter?.handleCommand && (await adapter.handleCommand(cmd, this))) return;
        if ((cmd.type === 'unknown' || cmd.type === 'clarify') && adapter?.handleUnknown && (await adapter.handleUnknown(raw, this))) return;

        await this.run(cmd);
    },

    async parse(raw, alternatives = null) {
        const body = { transcript: String(raw).slice(0, 200) };
        if (alternatives) body.alternatives = alternatives;
        try {
            const response = await fetch(this.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(body),
            });
            if (!response.ok) return { type: 'unknown', target: null, index: null };
            return await response.json();
        } catch (e) {
            return { type: 'unknown', target: null, index: null };
        }
    },

    async run(cmd) {
        switch (cmd.type) {
            case 'navigate':
                if (this.routes[cmd.target]) {
                    await this.tell('Membuka halaman.');
                    window.location.href = this.routes[cmd.target];
                }
                return;
            case 'not_available':
                return this.tell('Halaman itu belum tersedia.');
            case 'back':
                await this.tell('Kembali.');
                window.history.back();
                return;
            case 'repeat':
                return this.say(this.lastSpoken.length ? this.lastSpoken : [{ text: 'Belum ada yang dibacakan.', lang: 'id-ID' }]);
            case 'read_page':
                return this.readPage();
            case 'list_actions':
                this.actions = this.collectActions();
                this.actionPage = 0;
                return this.readActions();
            case 'more':
                this.actionPage++;
                return this.readActions();
            case 'choose_number':
                return this.choose(cmd.index);
            case 'confirm_yes':
            case 'confirm_no':
                return this.tell('Tidak ada yang perlu dikonfirmasi.');
            case 'stop':
                await this.say([{ text: 'Mode suara mati. Keyboard tetap bisa dipakai.', lang: 'id-ID' }]);
                this.stop('Mode suara mati. Keyboard tetap bisa dipakai.');
                return;
            case 'help':
                return this.tell(`${pageHint(this.adapter())}. ${MESSAGES.general}`);
            case 'start':
                return this.startMain(cmd.target);
            case 'clarify':
                return this.clarify(cmd.candidates || []);
            case 'dictate_start':
                return this.startDictation();
            case 'dictate_stop':
                return this.tell('Tidak sedang mengisi kolom.');
            default:
                return this.notUnderstood();
        }
    },

    /** "Saya dengar: <teks>" plus what can be said on this page, read aloud and shown in role="status". */
    notUnderstood(prefix = MESSAGES.unknown) {
        const heard = this.lastHeard ? ` Saya dengar: ${this.lastHeard}.` : '';
        return this.tell(`${prefix}${heard} ${pageHint(this.adapter())}`);
    },

    clarify(candidates) {
        if (candidates.length < 2) return this.notUnderstood();
        this.clarifying = candidates.slice(0, 2);
        const heard = this.lastHeard ? `Saya dengar: ${this.lastHeard}. ` : '';
        return this.tell(`${heard}Maksud Anda satu, ${candidates[0].label}, atau dua, ${candidates[1].label}? Katakan satu atau dua.`);
    },

    /**
     * "mulai": with a game named, open that game. Otherwise press the page's
     * start control ([data-voice-start] = its short spoken name, else a
     * "Mulai"/"Main lagi" button); with several, read them as a numbered list.
     */
    async startMain(target) {
        if (target && this.routes[target]) {
            await this.tell('Membuka game.');
            window.location.href = this.routes[target];
            return;
        }
        const marked = Array.from(this.main().querySelectorAll('[data-voice-start]'))
            .filter(isUsable)
            .map((el) => ({ el, name: el.getAttribute('data-voice-start') || accessibleName(el) }));
        const starts = marked.length ? marked : this.collectActions().filter((a) => /^(mulai|main lagi)\b/i.test(a.name));
        if (starts.length === 1) return this.activate(starts[0].el, starts[0].name);
        if (starts.length > 1) return this.offer(starts, 'pilihan');
        const status = this.statusTexts().join('. ');
        return this.notUnderstood(`Tidak ada yang bisa dimulai di halaman ini.${status ? ` ${status}` : ''}`);
    },

    /** Reads a numbered list and makes "nomor N" pick from it. */
    offer(actions, noun) {
        this.actions = actions;
        this.actionPage = 0;
        const lines = actions.map((a, i) => `${i + 1}. ${a.name}`);
        return this.tell(`Ada ${actions.length} ${noun}: ${lines.join('. ')}. Katakan nomornya.`);
    },

    /** ?voicedebug=1 only: last heard text, alternatives, confidence and parse. Nothing is stored. */
    debug(update) {
        if (!DEBUG) return;
        this.debugState = { ...(this.debugState || {}), ...update };
        let panel = document.getElementById('mode-suara-debug');
        if (!panel) {
            panel = document.createElement('pre');
            panel.id = 'mode-suara-debug';
            panel.setAttribute('aria-hidden', 'true');
            panel.style.cssText =
                'position:fixed;bottom:0.5rem;right:0.5rem;z-index:60;max-width:22rem;max-height:40vh;overflow:auto;margin:0;padding:0.5rem;font:12px/1.4 monospace;white-space:pre-wrap;background:#111;color:#eee;border-radius:0.5rem;opacity:0.9';
            document.body.appendChild(panel);
        }
        const { alternatives = [], parsed = null } = this.debugState;
        panel.textContent = [
            'voicedebug (hanya di memori)',
            ...alternatives.map((a, i) => `${i ? 'alternatif' : 'terdengar'}: "${a.transcript}", confidence ${typeof a.confidence === 'number' ? a.confidence.toFixed(2) : '-'}`),
            `parse: ${parsed ? JSON.stringify(parsed) : '-'}`,
        ].join('\n');
    },

    main() {
        return document.querySelector('main') || document.body;
    },

    /** h1, intro paragraph, section headings in <main>, and any non-empty status text. */
    readPage() {
        const adapter = this.adapter();
        if (adapter?.readPage) return adapter.readPage(this);

        const main = this.main();
        const parts = [];
        const h1 = main.querySelector('h1');
        if (h1) {
            parts.push(h1.textContent.trim());
            const intro = h1.parentElement.querySelector('h1 ~ p');
            if (intro && isUsable(intro)) parts.push(intro.textContent.trim());
        }
        main.querySelectorAll('h2, h3').forEach((h) => {
            if (isUsable(h) && !h.classList.contains('sr-only')) parts.push(h.textContent.trim());
        });
        parts.push(...this.statusTexts());
        return this.tell(parts.filter(Boolean).join('. ') || 'Halaman ini belum punya judul.');
    },

    statusTexts() {
        return Array.from(this.main().querySelectorAll('[role="status"], [role="alert"]'))
            .map((el) => el.textContent.replace(/\s+/g, ' ').trim())
            .filter(Boolean);
    },

    /** Numbered list of usable controls in <main>: buttons, links, radios (as options), labelled fields. */
    collectActions() {
        const selector = 'a[href], button, summary, input:not([type="hidden"]):not([type="file"]), textarea, select, [role="button"][tabindex]';
        return Array.from(this.main().querySelectorAll(selector))
            .filter((el) => isUsable(el) || (el.matches('input[type="radio"]') && isUsable(el.closest('label') || el)))
            .map((el) => ({ el, name: accessibleName(el) }))
            .filter((a) => a.name);
    },

    readActions() {
        if (!this.actions.length) return this.tell('Tidak ada tindakan di halaman ini.');
        const start = this.actionPage * GROUP_SIZE;
        const group = this.actions.slice(start, start + GROUP_SIZE);
        if (!group.length) {
            this.actionPage = 0;
            return this.tell('Itu semua tindakan. Katakan daftar tindakan untuk mendengar dari awal.');
        }
        const lines = group.map((a, i) => `${start + i + 1}. ${this.describe(a)}`);
        const more = start + GROUP_SIZE < this.actions.length ? ' Katakan lanjut untuk berikutnya.' : '';
        const intro = start === 0 ? `Ada ${this.actions.length} tindakan. ` : '';
        return this.tell(`${intro}${lines.join('. ')}. Katakan nomornya.${more}`);
    },

    describe(action) {
        const el = action.el;
        if (el.matches('input[type="radio"]')) return `pilihan ${action.name}${el.checked ? ', terpilih' : ''}`;
        if (el.matches('input, textarea, select')) return `kolom ${action.name}`;
        if (el.matches('a')) return `tautan ${action.name}`;
        return action.name;
    },

    async choose(index) {
        if (!this.actions.length) this.actions = this.collectActions();
        const action = this.actions[index];
        if (!action || !isUsable(action.el.matches('input[type="radio"]') ? action.el.closest('label') || action.el : action.el)) {
            return this.tell('Nomor itu tidak ada. Katakan daftar tindakan.');
        }
        return this.activate(action.el, action.name);
    },

    /** Click, select, or focus; irreversible actions ask first. */
    async activate(el, name = accessibleName(el)) {
        if (el.matches('input[type="radio"], input[type="checkbox"]')) {
            el.click();
            return this.afterAction(`${name} dipilih.`);
        }
        if (el.matches('input, textarea, select')) {
            el.focus();
            return this.tell(`Kolom ${name}. Katakan isi untuk mulai mengisi.`);
        }
        if (DANGEROUS.test(name)) {
            this.pending = {
                onYes: async () => {
                    el.click();
                    await this.afterAction(`${name} ditekan.`);
                },
                onNo: () => this.tell('Dibatalkan.'),
            };
            return this.tell('Yakin? Katakan ya atau tidak');
        }
        el.click();
        return this.afterAction(`${name} ditekan.`);
    },

    /** Waits for Livewire to update, then reads what appeared in the status regions. */
    async afterAction(prefix) {
        await new Promise((r) => setTimeout(r, 900));
        this.actions = [];
        const status = this.statusTexts().join('. ');
        return this.tell([prefix, status].filter(Boolean).join(' '));
    },

    /**
     * The next utterance is an answer, not a command: it is recognised in `lang`
     * (e.g. 'zh-CN' for hanzi) and passed to onText(text, engine). Saying "batal"
     * (in Indonesian) cancels instead. Only for the one utterance that follows.
     */
    captureNext(lang, onText) {
        this.capture = {
            lang,
            onText: async (text, engine) => {
                if (normalize(text) === 'batal') return engine.tell('Dibatalkan.');
                return onText(text, engine);
            },
        };
    },

    startDictation() {
        const field = document.activeElement;
        if (!field || !field.matches('input:not([type="radio"]):not([type="checkbox"]), textarea')) {
            return this.tell('Pilih kolom isian dulu lewat daftar tindakan.');
        }
        this.dictating = field;
        return this.tell(`Mengisi ${accessibleName(field)}. Bicaralah. Katakan selesai isi untuk berhenti.`);
    },
};

window.VoiceMode = VoiceMode;

// Stop the microphone and speech whenever the page goes away.
window.addEventListener('pagehide', () => VoiceMode.cleanup());

// wire:navigate swaps <main> without a page load: watch the new one.
document.addEventListener('livewire:navigated', () => {
    if (!VoiceMode.active) return;
    VoiceMode.watch();
    VoiceMode.syncAdapter();
});

// Mutually exclusive with Mode pembaca layar: switching that on stops this.
window.addEventListener('mode-pembaca-layar-aktif', () => {
    if (VoiceMode.active) VoiceMode.stop('Mode suara dimatikan karena Mode pembaca layar aktif.');
});

/** Alpine component for the global "Mode suara" controls in layouts/study.blade.php. */
window.voiceModeBar = function (endpoint, routes) {
    return {
        aktif: false,
        didukung: VoiceMode.supported(),
        mintaSetuju: false,

        init() {
            VoiceMode.endpoint = endpoint;
            VoiceMode.routes = routes;
            window.addEventListener('mode-suara', (e) => (this.aktif = e.detail.aktif));
            if (!this.didukung) return;
            if (VoiceMode.remembered()) {
                // Resume after a page load; browsers may need a tap to reopen the microphone.
                this.aktif = true;
                VoiceMode.active = true;
                VoiceMode.announce(MESSAGES.resume);
                this.$nextTick(() => this.$refs.tombol.focus());
            }
        },

        toggle() {
            if (!this.didukung) {
                VoiceMode.announce(MESSAGES.unsupported);
                return;
            }
            if (this.aktif && (VoiceMode.listening || VoiceMode.speaking)) {
                VoiceMode.stopListening();
                if ('speechSynthesis' in window) window.speechSynthesis.cancel();
                VoiceMode.speaking = false;
                return;
            }
            if (!VoiceMode.consented()) {
                this.mintaSetuju = true;
                this.$nextTick(() => this.$refs.setuju.focus());
                return;
            }
            if (!this.aktif) {
                VoiceMode.start();
                return;
            }
            VoiceMode.listen();
        },

        setuju() {
            VoiceMode.giveConsent();
            this.mintaSetuju = false;
            VoiceMode.start();
        },

        batal() {
            this.mintaSetuju = false;
            this.$nextTick(() => this.$refs.tombol.focus());
        },

        berhenti() {
            VoiceMode.stop();
            this.$nextTick(() => this.$refs.tombol.focus());
        },
    };
};

export default VoiceMode;
