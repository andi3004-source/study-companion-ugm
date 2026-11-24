/**
 * Page adapters for "Mode suara". Each page marks its root with
 * data-voice-page="<name>"; the engine asks the matching adapter first and
 * falls back to the generic actions (baca halaman, daftar tindakan, nomor N).
 * Adapters only press the controls that already exist (same click path as
 * the mouse and keyboard), so validation stays on the server.
 */
import VoiceMode, { accessibleName, isUsable, normalize } from './engine';

const LANG = { indonesia: 'id-ID', inggris: 'en-US', mandarin: 'zh-CN' };
const wait = (ms) => new Promise((r) => setTimeout(r, ms));
const text = (el) => (el ? el.textContent.replace(/\s+/g, ' ').trim() : '');
const root = () => document.querySelector('[data-voice-page]');
const buttonNamed = (pattern) =>
    Array.from(root()?.querySelectorAll('button, a[href]') || []).find((b) => isUsable(b) && pattern.test(accessibleName(b)));

/** Text a screen reader would hear: aria-hidden parts (emoji, arrows) left out. */
const spoken = (el) => {
    if (!el) return '';
    const clone = el.cloneNode(true);
    clone.querySelectorAll('[aria-hidden="true"]').forEach((n) => n.remove());
    return clone.textContent.replace(/\s+/g, ' ').trim();
};

/** Hanzi read by a Mandarin voice, everything else in Indonesian ("Arti dari 你好?"). */
export function mixedSegments(value) {
    return String(value || '')
        .split(/([\p{Script=Han}，。？！、]+)/u)
        .filter((part) => part.trim())
        .map((part) => ({ text: part, lang: /\p{Script=Han}/u.test(part) ? 'zh-CN' : 'id-ID' }));
}

const ANGKA = { nol: 0, satu: 1, dua: 2, tiga: 3, empat: 4, lima: 5, enam: 6, tujuh: 7, delapan: 8, sembilan: 9, sepuluh: 10, sebelas: 11, seratus: 100 };

/** "12", "dua belas", "dua puluh tiga", "ketiga" → number; anything else → null. */
export function toNumber(value) {
    const t = normalize(value).replace(/^(nomor|soal|ke)\s*/, '').replace(/^ke/, '');
    if (/^\d+$/.test(t)) return Number(t);
    let total = 0;
    let current = 0;
    for (const word of t.split(' ')) {
        if (word in ANGKA) {
            const v = ANGKA[word];
            if (v >= 10) {
                total += current + v;
                current = 0;
            } else {
                current += v;
            }
        } else if (word === 'belas') {
            total += current + 10;
            current = 0;
        } else if (word === 'puluh') {
            total += current * 10;
            current = 0;
        } else if (word === 'ratus') {
            total += current * 100;
            current = 0;
        } else {
            return null;
        }
    }
    return total + current || null;
}

/** Sets a field the way typing does, so Livewire (wire:model) and Alpine pick it up. */
function fill(field, value) {
    field.value = value;
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
}

/** Picks an <option> whose text matches, then fires change (wire:model.live on <select>). */
function choose(select, pattern) {
    const option = Array.from(select?.options || []).find((o) => pattern.test(o.textContent));
    if (!option) return null;
    fill(select, option.value);
    return option.textContent.trim();
}
VoiceMode.register(
    'ucapan',
    (() => {
        const RECORD_MS = 3500;
        const suggestions = () => Array.from(root().querySelectorAll('[data-saran]'));
        const selected = () => root().querySelector('[data-kata-terpilih]');
        const resultText = () => spoken(root().querySelector('div[role="status"]'));

        const readSelected = (engine) => {
            const box = selected();
            if (!box) return engine.tell('Belum ada kata dipilih. Katakan cari, lalu katanya.');
            const word = text(box.querySelector('p'));
            const lang = /[\p{Script=Han}]/u.test(word) ? 'zh-CN' : 'en-US';
            const details = Array.from(box.querySelectorAll('p')).slice(1).map(spoken).filter(Boolean);
            return engine.say([
                { text: 'Kata target:', lang: 'id-ID' },
                { text: word, lang },
                { text: `${details.join('. ')}. Katakan rekam untuk mulai, atau dengarkan untuk contoh.`, lang: 'id-ID' },
            ]);
        };

        const record = async (engine) => {
            const button = buttonNamed(/rekam suara/i);
            if (!button) {
                return engine.tell(selected() ? 'Hasil sudah ada. Katakan coba lagi untuk merekam ulang.' : 'Pilih kata dulu. Katakan cari, lalu katanya.');
            }
            await engine.tell('Setelah bunyi, ucapkan katanya. Rekaman sekitar tiga detik.');
            await engine.beep();
            button.click();
            await wait(RECORD_MS);
            buttonNamed(/berhenti merekam/i)?.click();
            for (let waited = 0; waited < 20000 && !resultText(); waited += 500) await wait(500);
            const error = text(root().querySelector('[role="alert"]'));
            return engine.tell(`${resultText() || error || 'Hasil belum keluar. Coba lagi.'} Katakan coba lagi, dengarkan, atau ganti kata.`);
        };

        return {
            hint: 'cari lalu katanya, nomor saran, rekam, dengarkan, coba lagi, ganti kata, bahasa mandarin, bahasa inggris, kembali, berhenti',
            readPage: (engine) => readSelected(engine),
            async handleTranscript(t, raw, engine) {
                const cari = raw.trim().match(/^cari\s+(.+)$/i);
                if (cari) {
                    if (!document.getElementById('ucapan-cari')) buttonNamed(/ganti kata|cari kata lain/i)?.click();
                    await wait(600);
                    const field = document.getElementById('ucapan-cari');
                    if (!field) return false;
                    fill(field, cari[1].replace(/[.?!。？！]+$/u, ''));
                    await wait(1500);
                    const list = suggestions();
                    if (!list.length) {
                        await engine.tell('Kata tidak ditemukan. Coba kata lain.');
                        return true;
                    }
                    const segments = [{ text: `Ada ${list.length} saran.`, lang: 'id-ID' }];
                    list.slice(0, 5).forEach((b, i) => {
                        segments.push({ text: `${i + 1}.`, lang: 'id-ID' }, ...mixedSegments(spoken(b)));
                    });
                    segments.push({ text: 'Katakan nomor kata yang mau dilatih.', lang: 'id-ID' });
                    await engine.say(segments);
                    return true;
                }
                const bahasa = t.match(/^bahasa (mandarin|inggris|english)$/);
                if (bahasa) {
                    const chosen = choose(document.getElementById('ucapan-bahasa'), bahasa[1] === 'mandarin' ? /^mandarin$/i : /^english$/i);
                    await wait(1000);
                    await engine.tell(chosen ? `Bahasa ${chosen}. Katakan cari, lalu katanya.` : 'Pilihan bahasa tidak ada.');
                    return true;
                }
                if (t === 'rekam' || t === 'mulai rekam') {
                    await record(engine);
                    return true;
                }
                if (t === 'dengarkan' || t === 'contoh' || t === 'dengarkan contoh') {
                    const speak = root().querySelector('button[aria-label^="Dengarkan cara ucap"], button[aria-label^="Dengarkan target"]');
                    if (!speak) {
                        await engine.tell('Pilih kata dulu.');
                        return true;
                    }
                    engine.stopListening();
                    speak.click();
                    await wait(1500);
                    return true;
                }
                if (t === 'coba lagi') {
                    const again = buttonNamed(/coba lagi/i);
                    if (!again) return false;
                    again.click();
                    await wait(1000);
                    await engine.tell('Siap merekam ulang. Katakan rekam.');
                    return true;
                }
                if (t === 'ganti kata' || t === 'cari kata lain') {
                    buttonNamed(/ganti kata|cari kata lain/i)?.click();
                    await wait(1000);
                    await engine.tell('Katakan cari, lalu katanya.');
                    return true;
                }
                return false;
            },
            async handleCommand(cmd, engine) {
                if (cmd.type === 'choose_number' && suggestions().length) {
                    const pick = suggestions()[cmd.index];
                    if (!pick) {
                        await engine.tell('Nomor itu tidak ada di saran.');
                        return true;
                    }
                    pick.click();
                    await wait(1200);
                    await readSelected(engine);
                    return true;
                }
                if (cmd.type === 'repeat' || cmd.type === 'read_page') {
                    await readSelected(engine);
                    return true;
                }
                if (cmd.type === 'start') {
                    await record(engine);
                    return true;
                }
                return false;
            },
        };
    })(),
);

/** Bantuan: the section titles as a numbered list; "nomor N" or "lanjut" reads a whole section. */
VoiceMode.register(
    'bantuan',
    (() => {
        let current = -1;
        const sections = () => Array.from(root().querySelectorAll('section'));

        const readSection = (engine, index) => {
            const all = sections();
            if (index >= all.length) return engine.tell('Itu bagian terakhir. Katakan baca halaman untuk daftar bagian.');
            current = Math.max(0, index);
            return engine.tell(`Bagian ${current + 1} dari ${all.length}. ${spoken(all[current])}. Katakan lanjut untuk bagian berikutnya.`);
        };

        return {
            hint: 'baca halaman untuk daftar bagian, nomor bagian, lanjut, ulangi, kembali, berhenti',
            readPage(engine) {
                current = -1;
                const titles = sections().map((s, i) => `${i + 1}. ${text(s.querySelector('h2'))}`);
                return engine.tell(`Bantuan. Ada ${titles.length} bagian: ${titles.join('. ')}. Katakan nomor bagian untuk mendengarnya.`);
            },
            async handleCommand(cmd, engine) {
                if (cmd.type === 'choose_number') {
                    await readSection(engine, cmd.index);
                    return true;
                }
                if (cmd.type === 'more') {
                    await readSection(engine, current + 1);
                    return true;
                }
                if (cmd.type === 'repeat' && current >= 0) {
                    await readSection(engine, current);
                    return true;
                }
                return false;
            },
        };
    })(),
);
VoiceMode.register('chat-picker', {
    hint: 'mulai, lalu nomor skenario, daftar tindakan, kembali, berhenti',
});
VoiceMode.register('chat', {
    hint: 'selesai, ulangi, baca halaman, kembali, berhenti. Ucapan lain dikirim sebagai pesan',
    onActivate() {
        // Read every new AI reply, then the engine reopens the microphone.
        if (this.terpasang) return;
        this.terpasang = true;
        window.addEventListener('ai-replied', (event) => {
            if (!VoiceMode.active) return;
            // Wait for Livewire to render the new reply, then read it (reply in its own language, translation in Indonesian).
            setTimeout(async () => {
                const translations = Array.from(document.querySelectorAll('#conversation-scroll span[lang="id"]'));
                const langCode = event.detail?.langCode || 'zh-CN';
                VoiceMode.stopListening();
                await VoiceMode.say([
                    { text: event.detail?.text || '', lang: langCode },
                    { text: langCode.startsWith('zh') ? text(translations.pop()) : '', lang: 'id-ID' },
                ]);
            }, 400);
        });
    },
    async handleTranscript(t, raw, engine) {
        if (t === 'selesai') {
            const finish = buttonNamed(/^selesai dan analisis$/i);
            if (!finish) {
                await engine.tell('Sesi ini sudah selesai.');
                return true;
            }
            engine.pending = {
                onYes: async () => {
                    finish.click();
                    await wait(300);
                    buttonNamed(/^ya, selesaikan$/i)?.click();
                    await engine.tell('Sesi diselesaikan. Analisis sedang diproses.');
                    await wait(6000);
                    const analysis = Array.from(root().querySelectorAll('h2')).find((h) => /analisis kesalahan/i.test(h.textContent));
                    if (analysis) await engine.tell(text(analysis.parentElement));
                },
                onNo: () => engine.tell('Dibatalkan.'),
            };
            await engine.tell('Yakin? Katakan ya atau tidak');
            return true;
        }
        return false;
    },
    async handleUnknown(raw, engine) {
        // Anything that isn't a command is the learner's message, sent through the existing form.
        const field = document.getElementById('kolom-tulis');
        if (!field || field.disabled) return false;
        field.value = raw.trim();
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.form.requestSubmit();
        await engine.tell('Pesan dikirim.');
        return true;
    },
});

export { accessibleName, normalize };
