<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Pengumuman — {{ config('app.name') }}</title>
        @include('partials.design-tokens')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    @php
        [$disematkan, $terbaru] = $announcements->getCollection()->partition(fn ($a) => $a->is_pinned);
    @endphp
    <body class="flex min-h-screen flex-col bg-paper font-sans text-ink antialiased">
        <header class="sticky top-0 z-20 border-b border-line bg-surface/95 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-2 sm:px-6">
                <a href="/" class="rk-focus text-ink" aria-label="Beranda {{ config('app.name') }}">@include('partials.wordmark')</a>
                <nav aria-label="Navigasi utama" class="flex items-center gap-1 sm:gap-2">
                    <a href="/" class="rk-focus inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-muted hover:text-ink">
                        Beranda
                    </a>
                    <a href="{{ route('paket-pt') }}" class="rk-focus inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-muted hover:text-ink">
                        Paket PT
                    </a>
                    <a href="{{ route('pengumuman') }}" aria-current="page" class="rk-focus inline-flex min-h-11 items-center border-b-4 border-brand-600 px-3 text-sm font-bold text-ink">
                        Pengumuman
                    </a>
                    <a href="/member/login" class="rk-focus ml-1 inline-flex min-h-11 items-center rounded-lg border border-ink px-4 text-sm font-semibold hover:bg-ink hover:text-surface">
                        Masuk
                    </a>
                </nav>
            </div>
        </header>

        <section class="rk-lanes bg-brand-600 text-white">
            <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 sm:py-14">
                <p class="text-sm font-semibold uppercase tracking-widest text-brand-100">Kabar dari gym</p>
                <h1 class="mt-2 font-display text-4xl font-extrabold italic leading-tight tracking-tight sm:text-5xl">Pengumuman</h1>
                <p class="mt-3 max-w-xl text-brand-50">Jam operasional, promo, dan perubahan jadwal terbaru. Cek di sini sebelum datang.</p>
            </div>
        </section>

        <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-10 sm:px-6 sm:py-12">
            @if ($announcements->isEmpty())
                <p class="rounded-xl border border-dashed border-line bg-surface p-10 text-center text-muted">Belum ada pengumuman saat ini.</p>
            @endif

            {{-- Penting: pengumuman yang disematkan tampil besar di atas. --}}
            @if ($disematkan->isNotEmpty())
                <div class="space-y-4">
                    @foreach ($disematkan as $announcement)
                        <article class="relative overflow-hidden rounded-xl bg-ink p-6 text-white sm:p-8">
                            <div class="absolute inset-y-0 left-0 w-2 bg-accent"></div>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-accent px-3 py-1 text-xs font-bold uppercase tracking-wide text-ink">
                                    <svg viewBox="0 0 20 20" class="size-3.5" fill="currentColor" aria-hidden="true"><path d="M11.5 2.5l6 6-2 .5-3 3 .5 3.5-1.5 1.5-3-3-4.5 4.5-1-1L8 13.5l-3-3 1.5-1.5L10 9.5l3-3z"/></svg>
                                    Penting
                                </span>
                                <time datetime="{{ $announcement->starts_at->toDateString() }}" class="text-brand-100">{{ $announcement->starts_at->translatedFormat('d M Y') }}</time>
                            </div>
                            <h2 class="mt-4 font-display text-2xl font-extrabold leading-tight sm:text-3xl">{{ $announcement->title }}</h2>
                            <p class="mt-3 max-w-2xl break-words text-lg text-brand-50">{!! nl2br(e($announcement->body)) !!}</p>
                            @if ($announcement->ends_at)
                                <p class="mt-5 text-sm text-brand-100">Berlaku sampai {{ $announcement->ends_at->translatedFormat('d M Y') }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif

            {{-- Kabar terbaru: urut waktu, tanggal di kiri seperti linimasa. --}}
            @if ($terbaru->isNotEmpty())
                <h2 class="mt-12 text-sm font-bold uppercase tracking-widest text-muted @if ($disematkan->isEmpty()) hidden @endif">Kabar terbaru</h2>
                <ol class="@if ($disematkan->isNotEmpty()) mt-4 @endif space-y-4 sm:space-y-0">
                    @foreach ($terbaru as $announcement)
                        <li class="relative sm:grid sm:grid-cols-[9rem_1fr] sm:gap-x-6">
                            <div class="pb-2 sm:pb-0 sm:pt-6 sm:text-right">
                                <time datetime="{{ $announcement->starts_at->toDateString() }}" class="block font-display text-lg font-extrabold tabular-nums">{{ $announcement->starts_at->translatedFormat('d M') }}</time>
                                <span class="text-sm text-muted">{{ $announcement->starts_at->format('Y') }}</span>
                            </div>
                            <div class="relative sm:border-l-2 sm:border-line sm:pb-6 sm:pl-8 sm:last:pb-0">
                                <span class="absolute -left-[0.6rem] top-7 hidden size-4 rounded-full border-4 border-paper bg-brand-600 sm:block" aria-hidden="true"></span>
                                <article class="rounded-xl border border-line bg-surface p-5 sm:p-6">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <h3 class="text-lg font-bold leading-snug">{{ $announcement->title }}</h3>
                                        @if ($announcement->starts_at->gt(now()->subDays(7)))
                                            <span class="rounded-full bg-brand-100 px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide text-brand-700">Baru</span>
                                        @endif
                                    </div>
                                    <p class="mt-2 break-words text-muted">{!! nl2br(e($announcement->body)) !!}</p>
                                    @if ($announcement->ends_at)
                                        <p class="mt-3 border-t border-line pt-3 text-sm text-muted">Berlaku sampai {{ $announcement->ends_at->translatedFormat('d M Y') }}</p>
                                    @endif
                                </article>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif

            <div class="mt-8">{{ $announcements->links() }}</div>
        </main>

        <section class="border-t border-line bg-surface">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-4 py-8 sm:px-6">
                <div>
                    <p class="font-display text-xl font-extrabold italic">Belum jadi member?</p>
                    <p class="text-muted">Pilih paket dan daftar dari HP.</p>
                </div>
                <a href="/#paket" class="rk-focus inline-flex min-h-12 items-center rounded-lg bg-brand-600 px-6 text-base font-bold text-white hover:bg-brand-700">Lihat paket</a>
            </div>
        </section>

        <footer class="bg-ink text-brand-100">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-x-8 gap-y-2 px-4 py-5 text-sm sm:px-6">
                <p><span class="font-semibold text-white">{{ config('app.name') }}</span> &copy; {{ now()->year }}</p>
                <nav aria-label="Tautan bawah" class="flex gap-6">
                    <a href="/" class="rk-focus-light inline-flex min-h-11 items-center underline underline-offset-4">Beranda</a>
                    <a href="/member/login" class="rk-focus-light inline-flex min-h-11 items-center underline underline-offset-4">Masuk member</a>
                </nav>
            </div>
        </footer>
    </body>
</html>
