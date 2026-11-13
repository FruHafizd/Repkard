<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Paket Personal Trainer — {{ config('app.name') }}</title>
        @include('partials.design-tokens')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen flex-col bg-paper font-sans text-ink antialiased">
        <header class="sticky top-0 z-20 border-b border-line bg-surface/95 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-2 sm:px-6">
                <a href="/" class="rk-focus text-ink" aria-label="Beranda {{ config('app.name') }}">@include('partials.wordmark')</a>
                <nav aria-label="Navigasi utama" class="flex items-center gap-1 sm:gap-2">
                    <a href="/" class="rk-focus inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-muted hover:text-ink">Beranda</a>
                    <a href="{{ route('paket-pt') }}" aria-current="page" class="rk-focus inline-flex min-h-11 items-center border-b-4 border-brand-600 px-3 text-sm font-bold text-ink">Paket PT</a>
                    <a href="{{ route('pengumuman') }}" class="rk-focus inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-muted hover:text-ink">Pengumuman</a>
                    <a href="/member/login" class="rk-focus ml-1 inline-flex min-h-11 items-center rounded-lg border border-ink px-4 text-sm font-semibold hover:bg-ink hover:text-surface">Masuk</a>
                </nav>
            </div>
        </header>

        <section class="rk-lanes bg-brand-600 text-white">
            <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
                <p class="text-sm font-semibold uppercase tracking-widest text-brand-100">Latihan dibimbing</p>
                <h1 class="mt-2 font-display text-4xl font-extrabold italic leading-tight tracking-tight sm:text-5xl">Paket Personal Trainer</h1>
                <p class="mt-3 max-w-xl text-brand-50">Latihan satu lawan satu bersama pelatih. Paket PT dibeli tambahan di atas membership.</p>
            </div>
        </section>

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-10 sm:px-6 sm:py-12">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($ptPackages as $package)
                    @include('partials.pt-package-card', ['package' => $package])
                @empty
                    <p class="text-muted sm:col-span-2 lg:col-span-3">Belum ada paket Personal Trainer tersedia.</p>
                @endforelse
            </div>

            <div class="mt-10 rounded-xl border border-line bg-surface p-5">
                <h2 class="text-lg font-bold">Cara membeli</h2>
                <p class="mt-1 text-muted">Jadi member dulu, lalu datang ke meja depan dan pilih paket PT. Petugas akan mencatat pembayaran dan menjadwalkan sesi pertama Anda.</p>
                <a href="/#paket" class="rk-focus mt-4 inline-flex min-h-12 items-center rounded-lg bg-brand-600 px-6 text-base font-bold text-white hover:bg-brand-700">Lihat paket membership</a>
            </div>
        </main>

        <footer class="bg-ink text-brand-100">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-8 gap-y-2 px-4 py-5 text-sm sm:px-6">
                <p><span class="font-semibold text-white">{{ config('app.name') }}</span> &copy; {{ now()->year }}</p>
                <nav aria-label="Tautan bawah" class="flex gap-6">
                    <a href="/" class="rk-focus-light inline-flex min-h-11 items-center underline underline-offset-4">Beranda</a>
                    <a href="/member/login" class="rk-focus-light inline-flex min-h-11 items-center underline underline-offset-4">Masuk member</a>
                </nav>
            </div>
        </footer>
    </body>
</html>
