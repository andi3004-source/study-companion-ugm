<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'TUTURNUSA') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-ink antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-cream">

            {{-- Logo & brand --}}
            <div class="flex items-center gap-2 mb-2">
                <a href="/" class="flex items-center gap-2">
                    <img src="{{ asset('images/landing/logo-tuturnusa.png') }}" alt="" width="286" height="185" class="w-16 h-auto">
                    <span class="font-display font-600 text-xl text-navy">TUTURNUSA</span>
                </a>
            </div>
            <p class="text-ink-soft text-sm mb-6">Belajar Mandarin & English setiap hari</p>

            {{-- Card --}}
            <div class="w-full sm:max-w-md px-6 py-7 bg-card shadow-sm overflow-hidden sm:rounded-3xl border border-line-card">
                {{ $slot }}
            </div>

            {{-- Back to home --}}
            <a href="/" class="mt-5 inline-flex items-center min-h-[44px] text-sm text-ink-soft hover:text-ink underline">
                ← Kembali ke beranda
            </a>
        </div>
    </body>
</html>
