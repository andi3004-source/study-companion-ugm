<div data-voice-page="ucapan" class="max-w-xl mx-auto px-4 sidebar:px-8 py-6 sidebar:py-8" x-data="{ practiceChar: null }">
    <h1 class="font-display font-600 text-2xl text-navy mb-2">Cek Ucapan</h1>
    <p class="text-lg text-secondary mb-8">Pilih kata dari kamus, rekam suaramu, lalu dengar hasilnya.</p>

    <div class="bg-white rounded-2xl border-2 border-navy p-6 space-y-5">
        <div>
            <label for="ucapan-bahasa" class="text-base font-600 text-navy block mb-2">Bahasa</label>
            <select id="ucapan-bahasa" wire:model.live="lang"
                    class="w-full min-h-[3.5rem] rounded-xl border-2 border-navy bg-white text-navy text-base focus:border-navy focus:ring-navy">
                <option value="english">English</option>
                <option value="mandarin">Mandarin</option>
            </select>
        </div>

        @if ($selectedWord)
            <div data-kata-terpilih class="rounded-2xl border-2 border-navy bg-white p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="{{ $lang === 'mandarin' ? 'font-display text-2xl text-navy' : 'font-600 text-xl text-navy' }}">{{ $selectedWord['headword'] }}</p>
                        @if ($selectedWord['sub'])
                            <p class="text-base text-secondary">{{ $selectedWord['sub'] }}</p>
                        @endif
                        @if ($selectedWord['meaning_id'])
                            <p class="text-base text-secondary mt-1"><span class="sr-only">Indonesia:</span> {{ $selectedWord['meaning_id'] }}</p>
                        @endif
                        @if ($selectedWord['meaning_en'])
                            <p class="text-base text-secondary"><span class="sr-only">Inggris:</span> {{ $selectedWord['meaning_en'] }}</p>
                        @endif
                    </div>
                    <button type="button"
                            onclick="speak({{ \Illuminate\Support\Js::from($selectedWord['headword']) }}, '{{ $selectedWord['lang_code'] }}')"
                            class="min-h-[3.5rem] min-w-[3.5rem] shrink-0 rounded-full border-2 border-navy bg-white flex items-center justify-center text-navy font-600"
                            aria-label="Dengarkan cara ucap {{ $selectedWord['headword'] }}">Dengar</button>
                </div>
                <button type="button" wire:click="clearSelection" class="text-base text-navy font-600 underline mt-3">Ganti kata</button>
            </div>
        @else
            <div>
                <label for="ucapan-cari" class="text-base font-600 text-navy block mb-2">
                    Cari kata {{ $lang === 'mandarin' ? 'Mandarin' : 'English' }}
                </label>
                <input type="text" id="ucapan-cari" wire:model.live.debounce.300ms="query"
                       placeholder="{{ $lang === 'mandarin' ? 'contoh: 妈 atau mama' : 'contoh: beautiful' }}"
                       class="w-full min-h-[3.5rem] rounded-xl border-2 border-navy bg-white text-base focus:border-navy focus:ring-navy">

                @if (trim($query) !== '')
                    <div class="mt-3 rounded-2xl border-2 border-navy divide-y-2 divide-navy overflow-hidden bg-white">
                        @forelse ($this->suggestions as $entry)
                            <button type="button"
                                    wire:click="selectWord(@js($entry->headword), @js($entry->sub), @js($entry->meaning_id), @js($entry->meaning_en), @js($entry->lang_code))"
                                    data-saran data-lang-code="{{ $entry->lang_code }}"
                                    class="w-full text-left min-h-[3.5rem] px-4 py-3 bg-white hover:text-navy transition text-navy">
                                <span class="{{ $entry->lang === 'mandarin' ? 'font-display' : 'font-600' }} text-base">{{ $entry->headword }}</span>
                                @if ($entry->sub)
                                    <span class="text-base text-secondary ml-1">{{ $entry->sub }}</span>
                                @endif
                                @if ($entry->meaning_id)
                                    <span class="block text-base text-secondary">{{ $entry->meaning_id }}</span>
                                @endif
                            </button>
                        @empty
                            <p class="px-4 py-3 text-base text-secondary">Kata tidak ditemukan. Coba kata lain.</p>
                        @endforelse
                    </div>
                @endif
            </div>
        @endif

        @if ($selectedWord && ! $result)
            <div x-data="pronunciationRecorder({{ \Illuminate\Support\Js::from($lang) }})" wire:key="recorder-{{ $lang }}-{{ $selectedWord['headword'] }}">
                <p role="alert" class="text-base text-red-700 mb-2" x-show="micError" x-text="micError"></p>

                <button type="button" x-on:click="toggleRecording"
                        x-bind:disabled="state === 'uploading'"
                        x-bind:class="{
                            'bg-navy text-white border-navy': state === 'idle',
                            'bg-red-700 text-white border-red-700 motion-safe:animate-pulse': state === 'recording',
                            'bg-white text-secondary border-navy': state === 'uploading',
                        }"
                        class="w-full min-h-[3.5rem] rounded-xl border-2 font-600 text-base transition disabled:opacity-70 flex items-center justify-center gap-2">
                    <span x-show="state === 'idle'">Rekam suara</span>
                    <span x-show="state === 'recording'">Berhenti merekam</span>
                    <span x-show="state === 'uploading'">Memproses…</span>
                </button>

                <p class="text-base text-secondary mt-2" x-show="!navigator.mediaDevices">Rekam suara butuh browser modern dengan akses mikrofon.</p>
                <p role="status" class="sr-only"><span x-show="state === 'recording'">Merekam. Tekan Berhenti merekam kalau sudah selesai.</span><span x-show="state === 'uploading'">Memproses rekaman…</span></p>
            </div>
        @endif

        <div role="status" class="space-y-4">
            @if ($errorMessage)
                <p class="text-base text-red-700 border-2 border-red-700 rounded-xl bg-white px-4 py-3" role="alert">{{ $errorMessage }}</p>
            @endif

            @if ($result)
                <div class="rounded-2xl border-2 p-5 bg-white {{ $result['isCorrect'] ? 'border-good' : 'border-red-700' }}">
                    <p class="font-600 text-base {{ $result['isCorrect'] ? 'text-good' : 'text-red-700' }}">
                        {{ $result['isCorrect'] ? 'Pengucapan tepat' : 'Kurang tepat' }}
                    </p>
                    <p class="text-base text-navy mt-2">{{ $result['feedback'] }}</p>
                </div>
            @endif
        </div>

        @if ($result)
            <div class="flex items-center gap-4 flex-wrap text-base text-secondary">
                <span class="flex items-center gap-2">
                    Target
                    <button type="button"
                            onclick="speak({{ \Illuminate\Support\Js::from($selectedWord['headword']) }}, '{{ $selectedWord['lang_code'] }}')"
                            class="min-h-[3.5rem] min-w-[3.5rem] rounded-full border-2 border-navy bg-white text-navy font-600"
                            aria-label="Dengarkan target {{ $selectedWord['headword'] }}">Dengar</button>
                </span>
                @if ($transcript)
                    <span class="flex items-center gap-2">
                        Yang terdengar: {{ $transcript }}
                        <button type="button"
                                onclick="speak({{ \Illuminate\Support\Js::from($transcript) }}, '{{ $selectedWord['lang_code'] }}')"
                                class="min-h-[3.5rem] min-w-[3.5rem] rounded-full border-2 border-navy bg-white text-navy font-600"
                                aria-label="Dengarkan yang terdengar">Dengar</button>
                    </span>
                @endif
            </div>

            <div class="flex gap-3 flex-wrap">
                <button type="button" wire:click="resetCheck"
                        class="flex-1 min-h-[3.5rem] rounded-xl border-2 border-navy bg-white font-600 text-base text-navy">
                    Coba lagi
                </button>
                @if ($lang === 'mandarin')
                    <button type="button" x-on:click="practiceChar = {{ \Illuminate\Support\Js::from($selectedWord['headword']) }}"
                            class="flex-1 min-h-[3.5rem] rounded-xl border-2 border-navy bg-white font-600 text-base text-navy">
                        Latihan tulis
                    </button>
                @endif
                <button type="button" wire:click="clearSelection"
                        class="flex-1 min-h-[3.5rem] rounded-xl border-2 border-navy bg-white font-600 text-base text-navy">
                    Cari kata lain
                </button>
            </div>
        @endif
    </div>

    <template x-if="practiceChar">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-white/80 px-4" x-on:click.self="practiceChar = null"
             x-data="modalDialog()" x-on:keydown.escape="practiceChar = null">
            <div role="dialog" aria-modal="true" aria-labelledby="judul-latihan-tulis"
                 class="bg-white rounded-3xl border-2 border-navy p-6 w-full max-w-sm"
                 x-data="hanziCanvas(practiceChar)" x-init="$nextTick(() => init())">
                <div class="flex items-center justify-between mb-4">
                    <h2 id="judul-latihan-tulis" class="font-display font-600 text-xl text-navy" x-text="'Latihan tulis: ' + practiceChar"></h2>
                    <button type="button" x-on:click="practiceChar = null" aria-label="Tutup" data-autofocus
                            class="min-h-[3.5rem] min-w-[3.5rem] border-2 border-navy rounded-full text-navy font-600">Tutup</button>
                </div>
                <canvas x-ref="canvas" role="img" aria-label="Kanvas latihan menulis hanzi"
                        x-on:pointerdown="startDraw($event)"
                        x-on:pointermove="draw($event)"
                        x-on:pointerup="endDraw"
                        x-on:pointerleave="endDraw"
                        class="w-full rounded-xl border-2 border-navy touch-none bg-white min-h-[12rem]"></canvas>
                <button type="button" x-on:click="clear"
                        class="w-full mt-4 min-h-[3.5rem] rounded-xl border-2 border-navy bg-white text-navy font-600 text-base">
                    Hapus dan ulangi
                </button>
            </div>
        </div>
    </template>
</div>