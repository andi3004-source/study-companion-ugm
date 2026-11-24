<div data-voice-page="chat-picker" class="max-w-xl mx-auto px-4 sidebar:px-8 py-6 sidebar:py-8">
    <h1 class="font-display font-600 text-2xl text-ink mb-1">Teman Ngobrol AI</h1>
    <p class="text-secondary text-sm mb-6">Latihan ngobrol bebas sesuai skenario. AI akan tetap dalam karakter dan menyisipkan koreksi kalau ada kesalahan.</p>

    {{-- Always in the DOM so screen readers announce the message when it appears. --}}
    <div role="status" class="mb-4">
        <span wire:loading wire:target="startRandom" class="sr-only">Menyiapkan obrolan…</span>
        @if ($errorMessage)
            <p class="rounded-xl border-2 border-red-700 bg-white px-4 py-3 text-sm text-red-700" role="alert">{{ $errorMessage }}</p>
        @endif
    </div>

    <div class="flex gap-2 mb-5" role="group" aria-label="Bahasa">
        <button type="button" wire:click="$set('lang', 'mandarin')" aria-pressed="{{ $lang === 'mandarin' ? 'true' : 'false' }}"
                class="flex-1 min-h-[3.5rem] py-2.5 rounded-xl font-600 text-sm border-2 transition {{ $lang === 'mandarin' ? 'bg-navy text-white border-navy' : 'bg-white text-navy border-navy' }}">
            Mandarin
        </button>
        <button type="button" wire:click="$set('lang', 'english')" aria-pressed="{{ $lang === 'english' ? 'true' : 'false' }}"
                class="flex-1 min-h-[3.5rem] py-2.5 rounded-xl font-600 text-sm border-2 transition {{ $lang === 'english' ? 'bg-navy text-white border-navy' : 'bg-white text-navy border-navy' }}">
            English
        </button>
    </div>

    <div class="space-y-3">
        @foreach ($this->scenarios as $key => $scenario)
            <button type="button" wire:click="selectScenario('{{ $key }}')" data-voice-start="{{ $scenario['label'] }}"
                    class="w-full text-left block rounded-2xl bg-white px-5 py-4 transition border-2 border-navy min-h-[3.5rem]">
                <span class="font-display font-600 text-ink">{{ $scenario['label'] }}</span>
                <span class="block text-xs text-secondary mt-0.5">Mulai sesi percakapan baru</span>
            </button>
        @endforeach

        <button type="button" wire:click="startRandom" data-voice-start="Ngobrol Bebas" wire:loading.attr="disabled" wire:target="startRandom"
                class="w-full text-left block rounded-2xl bg-white px-5 py-4 transition border-2 border-dashed border-navy disabled:opacity-60 min-h-[3.5rem]">
            <span class="font-display font-600 text-navy" wire:loading.remove wire:target="startRandom">Ngobrol Bebas</span>
            <span class="font-display font-600 text-navy" wire:loading wire:target="startRandom">Menyiapkan obrolan...</span>
            <span class="block text-xs text-secondary mt-0.5" wire:loading.remove wire:target="startRandom">Topik acak, AI mulai duluan — gak perlu mikir dulu</span>
        </button>
    </div>
</div>
