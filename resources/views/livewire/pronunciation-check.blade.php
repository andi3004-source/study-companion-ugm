<div data-voice-page="ucapan" class="max-w-xl mx-auto px-4 sidebar:px-8 py-6 sidebar:py-8" x-data="{ practiceChar: null }">
    <h1 class="font-display font-600 text-xl text-ink mb-1">Cek Pengucapan</h1>
    <p class="text-xs text-ink-soft mb-6">Pilih kata target dari kamus, rekam suaramu, dan lihat hasilnya.</p>

    <div class="bg-card rounded-2xl border border-line-card p-5 space-y-4">
        <div>
            <label for="ucapan-bahasa" class="text-sm font-700 text-ink block mb-1">Bahasa</label>
            <select id="ucapan-bahasa" wire:model.live="lang" class="w-full rounded-xl border-line-card text-sm focus:border-mandarin focus:ring-mandarin">
                <option value="english">English</option>
                <option value="mandarin">Mandarin</option>
            </select>
        </div>

        @if ($selectedWord)
            {{-- Always visible once a word is picked, before AND after recording. --}}
            <div data-kata-terpilih class="rounded-xl bg-card-alt p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="{{ $lang === 'mandarin' ? 'font-display text-2xl' : 'font-700 text-lg' }}">{{ $selectedWord['headword'] }}</p>
                        @if ($selectedWord['sub'])
                            <p class="text-xs text-ink-soft">{{ $selectedWord['sub'] }}</p>
                        @endif
                        @if ($selectedWord['meaning_id'])
                            <p class="text-xs text-ink-faint mt-1"><span aria-hidden="true">🇮🇩</span> <span class="sr-only">Indonesia:</span> {{ $selectedWord['meaning_id'] }}</p>
                        @endif
                        @if ($selectedWord['meaning_en'])
                            <p class="text-xs text-ink-faint"><span aria-hidden="true">🇬🇧</span> <span class="sr-only">Inggris:</span> {{ $selectedWord['meaning_en'] }}</p>
                        @endif
                    </div>
                    <button type="button" onclick="speak({{ \Illuminate\Support\Js::from($selectedWord['headword']) }}, '{{ $selectedWord['lang_code'] }}')"
                            class="w-9 h-9 shrink-0 rounded-full bg-navy/5 hover:bg-navy/10 flex items-center justify-center text-navy" aria-label="Dengarkan cara ucap {{ $selectedWord['headword'] }}"><span aria-hidden="true">🔊</span></button>
                </div>
                <button type="button" wire:click="clearSelection" class="text-sm text-ink-soft hover:text-ink mt-2"><span aria-hidden="true">←</span> Ganti kata</button>
            </div>
        @else
            <div>
                <label for="ucapan-cari" class="text-sm font-700 text-ink block mb-1">
                    Cari kata {{ $lang === 'mandarin' ? 'Mandarin' : 'English' }}
                </label>
                <input type="text" id="ucapan-cari" wire:model.live.debounce.300ms="query"
                       placeholder="{{ $lang === 'mandarin' ? 'contoh: 妈 atau mama' : 'contoh: beautiful' }}"
                       class="w-full rounded-xl border-line-card text-sm focus:border-mandarin focus:ring-mandarin">

                @if (trim($query) !== '')
                    <div class="mt-2 rounded-xl border border-line-card divide-y divide-line-card overflow-hidden">
                        @forelse ($this->suggestions as $entry)
                            <button type="button"
                                    wire:click="selectWord(@js($entry->headword), @js($entry->sub), @js($entry->meaning_id), @js($entry->meaning_en), @js($entry->lang_code))" data-saran data-lang-code="{{ $entry->lang_code }}"
                                    class="w-full text-left px-3 py-2 hover:bg-card-alt transition">
                                <span class="{{ $entry->lang === 'mandarin' ? 'font-display' : 'font-700' }} text-sm">{{ $entry->headword }}</span>
                                @if ($entry->sub)
                                    <span class="text-xs text-ink-soft ml-1">{{ $entry->sub }}</span>
                                @endif
                                @if ($entry->meaning_id)
                                    <span class="block text-xs text-ink-faint">{{ $entry->meaning_id }}</span>
                                @endif
                            </button>
                        @empty
                            <p class="px-3 py-2 text-sm text-ink-soft">Kata tidak ditemukan. Coba kata lain.</p>
                        @endforelse
                    </div>
                @endif
            </div>
        @endif

        @if ($selectedWord && ! $result)
            <div x-data="pronunciationRecorder({{ \Illuminate\Support\Js::from($lang) }})" wire:key="recorder-{{ $lang }}-{{ $selectedWord['headword'] }}">
                <p role="alert" class="text-xs text-red-700 mb-2" x-show="micError" x-text="micError"></p>

                <button type="button" x-on:click="toggleRecording"
                        x-bind:disabled="state === 'uploading'"
                        x-bind:class="{
                            'bg-navy text-white hover:bg-navy-soft': state === 'idle',
                            'bg-red-700 text-white motion-safe:animate-pulse': state === 'recording',
                            'bg-card-alt text-ink-faint': state === 'uploading',
                        }"
                        class="w-full py-2.5 rounded-xl font-700 text-sm transition disabled:opacity-70 flex items-center justify-center gap-2">
                    <span x-show="state === 'idle'"><span aria-hidden="true">🎤</span> Rekam suara</span>
                    <span x-show="state === 'recording'"><span aria-hidden="true">⏹️</span> Berhenti merekam</span>
                    <span x-show="state === 'uploading'"><span aria-hidden="true">⏳</span> Memproses...</span>
                </button>

                <p class="text-xs text-ink-soft mt-1.5" x-show="!navigator.mediaDevices">Rekam suara butuh browser modern dengan akses microphone.</p>
                <p role="status" class="sr-only"><span x-show="state === 'recording'">Merekam. Tekan Berhenti merekam kalau sudah selesai.</span><span x-show="state === 'uploading'">Memproses rekaman…</span></p>
            </div>
        @endif

        {{-- Always rendered so the error and the result are announced when they appear. --}}
        <div role="status" class="space-y-4">
            @if ($errorMessage)
                <p class="text-sm text-red-700"><span aria-hidden="true">⚠</span> {{ $errorMessage }}</p>
            @endif

            @if ($result)
                <div class="rounded-xl p-4 {{ $result['isCorrect'] ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' }}">
                    <p class="font-700 text-sm {{ $result['isCorrect'] ? 'text-green-700' : 'text-red-700' }}">
                        {{ $result['isCorrect'] ? '✓ Pengucapan tepat!' : '✗ Kurang tepat' }}
                    </p>
                    <p class="text-sm text-ink mt-1">{{ $result['feedback'] }}</p>
                </div>
            @endif
        </div>

        @if ($result)

            <div class="flex items-center gap-4 flex-wrap text-xs text-ink-soft">
                <span class="flex items-center gap-1.5">
                    Target
                    <button type="button" onclick="speak({{ \Illuminate\Support\Js::from($selectedWord['headword']) }}, '{{ $selectedWord['lang_code'] }}')"
                            class="w-7 h-7 rounded-full bg-navy/5 hover:bg-navy/10 flex items-center justify-center text-navy" aria-label="Dengarkan target {{ $selectedWord['headword'] }}"><span aria-hidden="true">🔊</span></button>
                </span>
                @if (filled($transcript))
                    <span class="flex items-center gap-1.5">
                        Yang terdengar (&ldquo;{{ $transcript }}&rdquo;)
                        <button type="button" onclick="speak({{ \Illuminate\Support\Js::from($transcript) }}, '{{ $selectedWord['lang_code'] }}')"
                                class="w-7 h-7 rounded-full bg-navy/5 hover:bg-navy/10 flex items-center justify-center text-navy" aria-label="Dengarkan yang terdengar"><span aria-hidden="true">🔊</span></button>
                    </span>
                @endif
            </div>

            <div class="flex gap-2 flex-wrap">
                <button type="button" wire:click="resetCheck"
                        class="flex-1 py-2.5 rounded-xl font-700 text-sm bg-card-alt text-ink hover:bg-line-card transition">
                    <span aria-hidden="true">🔁</span> Coba Lagi
                </button>
                @if ($lang === 'mandarin')
                    <button type="button" x-on:click="practiceChar = {{ \Illuminate\Support\Js::from($selectedWord['headword']) }}"
                            class="flex-1 py-2.5 rounded-xl font-700 text-sm bg-mandarin-soft text-mandarin hover:bg-mandarin/20 transition">
                        <span aria-hidden="true">✍️</span> Latihan Tulis
                    </button>
                @endif
                <button type="button" wire:click="clearSelection"
                        class="flex-1 py-2.5 rounded-xl font-700 text-sm bg-card-alt text-ink hover:bg-line-card transition">
                    <span aria-hidden="true">🔍</span> Cari Kata Lain
                </button>
            </div>
        @endif
    </div>

    {{-- Hanzi writing practice modal — same component Kamus uses (see resources/views/livewire/kamus/search.blade.php), reused as-is rather than rebuilt. --}}
    <template x-if="practiceChar">
        <div class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center px-4" x-on:click.self="practiceChar = null"
             x-data="modalDialog()" x-on:keydown.escape="practiceChar = null">
            <div role="dialog" aria-modal="true" aria-labelledby="judul-latihan-tulis" class="bg-card rounded-3xl p-5 w-full max-w-sm" x-data="hanziCanvas(practiceChar)" x-init="$nextTick(() => init())">
                <div class="flex items-center justify-between mb-3">
                    <h2 id="judul-latihan-tulis" class="font-display font-600 text-lg" x-text="'Latihan Tulis: ' + practiceChar"></h2>
                    <button type="button" x-on:click="practiceChar = null" aria-label="Tutup" data-autofocus class="text-ink-soft text-xl leading-none"><span aria-hidden="true">✕</span></button>
                </div>
                <canvas x-ref="canvas" role="img" aria-label="Kanvas latihan menulis hanzi dengan jari atau mouse"
                        x-on:pointerdown="startDraw($event)"
                        x-on:pointermove="draw($event)"
                        x-on:pointerup="endDraw"
                        x-on:pointerleave="endDraw"
                        class="w-full rounded-xl touch-none bg-white"></canvas>
                <button type="button" x-on:click="clear" class="w-full mt-3 py-2.5 rounded-xl bg-card-alt text-ink-soft font-700 text-sm hover:bg-line-card">Hapus & Ulangi</button>
            </div>
        </div>
    </template>
</div>
