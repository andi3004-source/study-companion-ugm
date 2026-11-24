@php
    // lang attribute for target-language text: BCP 47 'zh-Hans' (Simplified Chinese) / 'en'.
    $targetLang = $session->lang === 'mandarin' ? 'zh-Hans' : 'en';
    $errorCount = $this->analysisState === 'done' ? count($session->error_analysis['items'] ?? []) : null;
    $replyNumber = 0;
@endphp

@if (! $session->ended_at)
    @push('skip-links')
        <a href="#kolom-tulis" class="skip-link">Lewati ke kolom tulis</a>
    @endpush
@endif

<div data-voice-page="chat" class="max-w-2xl mx-auto px-4 sidebar:px-8 py-6 sidebar:py-8 flex flex-col" style="min-height: calc(100vh - 2rem);">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
            <h1 class="font-display font-600 text-xl text-ink">{{ $this->scenario['label'] }}</h1>
            <p class="text-xs text-secondary">{{ ucfirst($session->lang) }} · Sisa kuota hari ini: {{ $this->remainingToday }}/{{ $dailyLimit }}</p>
            @if ($this->levelLocked)
                <span class="inline-block mt-1 text-xs font-600 text-navy border-2 border-navy rounded-full px-2 py-0.5">Level: HSK 1 (terkunci)</span>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <label class="flex items-center gap-1.5 text-xs text-secondary">
                <input type="checkbox" wire:model.live="autoPlay" class="rounded text-mandarin focus:ring-mandarin">
                Auto-play balasan
            </label>
            @if (! $session->ended_at)
                {{-- Inline confirmation (no modal, no focus trap): the question and both buttons appear in place. --}}
                <div x-data="{ confirming: false }" class="flex items-center gap-2">
                    <button type="button" x-show="!confirming"
                            x-on:click="confirming = true; $nextTick(() => $refs.confirmYes.focus())"
                            class="text-sm text-secondary hover:text-ink underline">Selesai dan analisis</button>
                    <div x-show="confirming" x-cloak class="flex items-center gap-2" role="group" aria-labelledby="konfirmasi-selesai">
                        <span id="konfirmasi-selesai" class="text-sm text-ink">Selesaikan sesi ini dan analisis?</span>
                        <button type="button" x-ref="confirmYes" wire:click="finishSession"
                                class="text-sm font-700 text-white bg-navy rounded-lg px-3 py-1">Ya, selesaikan</button>
                        <button type="button"
                                x-on:click="confirming = false; $nextTick(() => $root.querySelector('button').focus())"
                                class="text-sm text-secondary underline">Batal</button>
                    </div>
                </div>
            @endif
            <a href="{{ route('conversation') }}" class="text-sm text-secondary"><span aria-hidden="true">←</span> Kembali</a>
        </div>
    </div>

    {{--
        Single polite status region for short announcements. It is always in the
        DOM (live regions must exist before their content changes) and polling
        only updates its text — it never moves focus.
    --}}
    <div role="status" id="status-percakapan" class="sr-only">
        <span wire:loading wire:target="sendMessage">Teman sedang membalas…</span>
        <span wire:loading.remove wire:target="sendMessage">
            @if ($errorMessage)
                {{ $errorMessage }}
            @elseif ($this->analysisState === 'pending')
                Analisis sedang diproses
            @elseif ($this->analysisState === 'done')
                Analisis selesai, {{ $errorCount }} kesalahan
            @elseif ($this->analysisState === 'failed')
                Analisis gagal
            @elseif ($this->analysisState === 'timeout')
                Analisis belum selesai
            @elseif ($statusMessage)
                {{ $statusMessage }}
            @endif
        </span>
    </div>

    @if ($summary)
        <div class="bg-navy text-white rounded-2xl p-5 mb-4">
            <h2 class="font-display font-600 text-lg">Sesi selesai</h2>
            <p class="text-white/80 text-sm mt-1">{{ $summary['exchange_count'] }} pertukaran pesan.</p>
            @if ($summary['notes'])
                <div class="mt-3 pt-3 border-t border-white/20 text-sm text-white/90 whitespace-pre-line">{{ $summary['notes'] }}</div>
            @endif
        </div>
    @endif

    @if ($this->analysisState)
        {{-- Polls only while an ended session's analysis is queued and unfinished (analyzed_at NULL), for at most Chat::ANALYSIS_POLL_SECONDS. --}}
        <div class="bg-white border-2 border-navy rounded-2xl p-5 mb-4" @if ($this->analysisState === 'pending') wire:poll.3s="pollAnalysis" @endif>
            <h2 class="font-display font-600 text-ink mb-2">Analisis kesalahan</h2>

            @if ($this->analysisState === 'pending')
                <p class="text-sm text-secondary">Analisis kesalahan sedang diproses…</p>
            @elseif ($this->analysisState === 'timeout')
                <p class="text-sm text-secondary">Analisis belum selesai. Hasilnya akan muncul di halaman Progress setelah selesai diproses.</p>
                <button type="button" wire:click="pollAnalysis" class="mt-2 text-xs font-700 text-mandarin underline">Muat ulang</button>
            @elseif ($this->analysisState === 'failed')
                <p class="text-sm text-secondary">Analisis kesalahan gagal untuk sesi ini. Coba lagi nanti.</p>
            @elseif (empty($session->error_analysis['items']))
                <p class="text-sm text-ink">Tidak ada kesalahan yang terdeteksi di sesi ini. Mantap! 🎉</p>
            @else
                <ul class="space-y-3">
                    @foreach ($session->error_analysis['items'] as $item)
                        <li class="text-sm">
                            <span class="inline-block text-xs font-600 text-navy border border-navy rounded-full px-2 py-0.5 mb-1">{{ \App\Enums\ErrorCategory::fromLoose($item['kategori'])->label() }}</span>
                            <p class="text-ink">
                                <span class="sr-only">Salah:</span>
                                <span class="line-through text-secondary" lang="{{ $targetLang }}">{{ $item['contoh'] }}</span>
                                <span aria-hidden="true">→</span>
                                <span class="sr-only">Benar:</span>
                                <span class="font-700" lang="{{ $targetLang }}">{{ $item['koreksi'] }}</span>
                            </p>
                            @if ($item['penjelasan'])
                                <p class="text-xs text-secondary mt-0.5">{{ $item['penjelasan'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($this->analysisState === 'done' && ! empty($session->error_analysis['catatan']))
                <p class="text-xs text-secondary mt-3">{{ $session->error_analysis['catatan'] }}</p>
            @endif

            {{-- Sessions analyzed before 'sumber' existed show no label. --}}
            @if ($this->analysisState === 'done' && ($session->error_analysis['sumber'] ?? null) === 'langflow')
                <p class="text-[10px] text-secondary mt-2">Dianalisis oleh IBM Langflow</p>
            @elseif ($this->analysisState === 'done' && ($session->error_analysis['sumber'] ?? null) === 'gemini')
                <p class="text-[10px] text-secondary mt-2">Dianalisis oleh Gemini (cadangan)</p>
            @endif
        </div>
    @endif

    <h2 class="sr-only" id="judul-percakapan">Percakapan</h2>
    <div class="flex-1 overflow-y-auto space-y-3 mb-4 pr-1" id="conversation-scroll" x-data
         role="log" aria-live="polite" aria-relevant="additions" aria-labelledby="judul-percakapan"
         x-init="$el.scrollTop = $el.scrollHeight"
         x-on:message-sent.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
        @forelse ($this->turns as $turn)
            @if ($turn->role === 'user')
                <div class="flex justify-end" wire:key="turn-{{ $turn->id }}">
                    <div class="max-w-[80%] break-words bg-navy text-white rounded-2xl rounded-br-sm px-4 py-2.5 text-sm">
                        <p class="text-xs font-700 text-white/80">Kamu:</p>
                        {{-- Learners type hanzi or tone-numbered pinyin; only tag hanzi as Chinese so pinyin isn't read with a Chinese voice. --}}
                        @php($userLang = preg_match('/\p{Han}/u', $turn->content) ? 'zh-Hans' : ($session->lang !== 'mandarin' ? 'en' : null))
                        <p {!! $userLang ? 'lang="'.$userLang.'"' : '' !!}>{{ $turn->content }}</p>
                    </div>
                </div>
            @else
                @php($replyNumber++)
                <div class="flex justify-start" wire:key="turn-{{ $turn->id }}">
                    <div class="max-w-[80%] break-words bg-white rounded-2xl rounded-bl-sm px-4 py-2.5 text-sm text-ink border-2 border-navy">
                        <p class="text-xs font-700 text-secondary">Teman bicara:</p>
                        <p lang="{{ $targetLang }}">{{ $turn->content }}</p>
                        @if ($turn->translation)
                            <p class="text-secondary text-xs mt-1"><span aria-hidden="true">🇮🇩</span> <span class="sr-only">Terjemahan:</span> <span lang="id">{{ $turn->translation }}</span></p>
                        @endif
                        <button type="button"
                                onclick="speak({{ Illuminate\Support\Js::from($turn->content) }}, '{{ $this->scenario['target_lang_code'] }}')"
                                aria-label="Putar suara balasan {{ $replyNumber }}"
                                class="mt-1.5 text-secondary hover:text-mandarin text-xs underline"><span aria-hidden="true">🔊</span> Dengarkan</button>
                    </div>
                </div>
            @endif
        @empty
            <div class="text-center text-secondary text-sm py-10">Mulai percakapan dengan mengetik pesan di bawah.</div>
        @endforelse
    </div>

    @if (! $session->ended_at)
        <div x-data="voiceInput({{ \Illuminate\Support\Js::from($this->scenario['target_lang_code']) }})"
             x-on:ai-replied.window="handleAiReply($event.detail.text, $event.detail.langCode)"
             x-on:message-sent.window="$nextTick(() => $refs.kolomTulis && $refs.kolomTulis.focus())">
            @if ($errorMessage)
                <p class="text-xs text-red-700 mb-2"><span aria-hidden="true">⚠</span> {{ $errorMessage }}</p>
            @endif

            <p class="text-xs text-secondary mb-2" x-show="state === 'listening'">
                <span x-show="interimText" x-text="interimText"></span>
                <span x-show="!interimText">Mendengarkan... bicara saja, tekan tombol Berhenti kalau sudah selesai.</span>
            </p>
            <p class="text-xs text-red-700 mb-2" x-show="micError" x-text="micError"></p>

            <form wire:submit="sendMessage" class="flex flex-col gap-2" x-ref="form">
                <label for="kolom-tulis" class="text-sm font-700 text-ink">Pesanmu</label>
                <p id="petunjuk-kolom-tulis" class="text-xs text-secondary">
                    @if ($session->lang === 'mandarin')
                        Tulis jawabanmu dalam bahasa Mandarin atau pinyin dengan angka nada, misalnya wo3 bu4 you3 qian2. Tekan Enter untuk kirim, Shift+Enter untuk baris baru.
                    @else
                        Tulis jawabanmu dalam bahasa Inggris. Tekan Enter untuk kirim, Shift+Enter untuk baris baru.
                    @endif
                </p>
                <div class="flex gap-2 items-end">
                    {{-- Enter submits; Shift+Enter is a newline; Enter while an IME is composing (isComposing / keyCode 229) is left alone. --}}
                    <textarea id="kolom-tulis" x-ref="kolomTulis" wire:model="newMessage" maxlength="500" rows="2"
                              aria-describedby="petunjuk-kolom-tulis"
                              x-on:keydown.enter="if ($event.shiftKey || $event.isComposing || $event.keyCode === 229) return; $event.preventDefault(); $refs.form.requestSubmit()"
                              class="flex-1 min-h-[3.5rem] rounded-xl border-2 border-navy bg-white text-base focus:border-navy focus:ring-navy resize-y"
                              @if ($this->remainingToday <= 0) disabled @endif></textarea>

                    <button type="button" x-show="supported" x-on:click="toggleListening"
                            x-bind:aria-pressed="state === 'listening' ? 'true' : 'false'"
                            x-bind:aria-label="state === 'listening' ? 'Berhenti' : 'Mulai bicara'"
                            aria-pressed="false" aria-label="Mulai bicara"
                            x-bind:disabled="(state === 'processing' || state === 'speaking') || {{ $this->remainingToday <= 0 ? 'true' : 'false' }}"
                            x-bind:class="{
                                'bg-navy text-white border-2 border-navy': state === 'idle',
                                'bg-red-700 text-white border-2 border-red-700 motion-safe:animate-pulse': state === 'listening',
                                'bg-white text-secondary border-2 border-navy': state === 'processing',
                                'bg-orange text-navy border-2 border-navy': state === 'speaking',
                            }"
                            class="min-h-[3.5rem] min-w-[3.5rem] shrink-0 rounded-full flex items-center justify-center transition disabled:opacity-70 border-2">
                        <span aria-hidden="true" x-show="state === 'processing'">⏳</span>
                        <span aria-hidden="true" x-show="state === 'speaking'">🔊</span>
                        <span aria-hidden="true" x-show="state === 'listening'">⏹️</span>
                        <span aria-hidden="true" x-show="state === 'idle'">🎤</span>
                    </button>

                    <button type="submit"
                            class="px-5 min-h-[3.5rem] rounded-xl border-2 border-navy bg-navy text-white font-600 text-base transition disabled:opacity-50"
                            @if ($this->remainingToday <= 0) disabled @endif
                            wire:loading.attr="disabled" wire:target="sendMessage">
                        Kirim
                    </button>
                </div>
            </form>

            <p class="text-[11px] text-secondary mt-1.5" x-show="!supported">Voice input butuh Chrome/Edge.</p>

            @include('components.microphone-privasi')
        </div>
    @endif
</div>
