{{-- Only links that exist: there are no "Tentang", "Kebijakan Privasi" or "Kontak" pages yet. --}}
<footer class="max-w-6xl mx-auto px-4 pb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-sm text-ink-soft">
    <p>&copy; {{ now()->year }} TUTURNUSA. Dibangun untuk pembelajar Indonesia.</p>
    <p><a href="{{ route('bantuan') }}" class="inline-flex items-center min-h-[44px] text-brand-navy font-semibold underline underline-offset-4">Bantuan</a> <span>(perlu masuk dulu)</span></p>
</footer>
