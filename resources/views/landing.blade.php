<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }} — Membership gym</title>
        @include('partials.design-tokens')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-paper font-sans text-ink antialiased">
        <a href="#paket" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-4 focus:py-3 focus:font-semibold">
            Langsung ke daftar paket
        </a>

        <header class="border-b border-line bg-surface">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3 sm:px-6">
                <h1 class="text-ink">@include('partials.wordmark')</h1>
                <nav aria-label="Navigasi utama" class="flex items-center gap-1 sm:gap-2">
                    <a href="/" aria-current="page" class="rk-focus inline-flex min-h-11 items-center border-b-4 border-brand-600 px-3 text-sm font-bold text-ink">
                        Beranda
                    </a>
                    <a href="{{ route('pengumuman') }}" class="rk-focus inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-muted hover:text-ink">
                        Pengumuman
                    </a>
                    <a href="/member/login" class="rk-focus ml-1 inline-flex min-h-11 items-center rounded-lg border border-ink px-4 text-sm font-semibold hover:bg-ink hover:text-surface">
                        Masuk
                    </a>
                </nav>
            </div>
        </header>

        <main>
            <section class="rk-lanes bg-brand-600 text-white">
                <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-12 sm:px-6 sm:py-16 lg:grid-cols-[1.2fr_1fr] lg:py-20">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-widest text-brand-100">Membership gym</p>
                        <p class="mt-3 font-display text-4xl font-extrabold italic leading-[1.02] tracking-tight text-balance sm:text-5xl lg:text-6xl">
                            Datang, scan, langsung latihan.
                        </p>
                        <p class="mt-5 max-w-xl text-lg text-brand-50">
                            Pilih paket dan buat akun dari HP. Aktivasi sekali di meja depan, kartu member berfoto langsung terbit.
                        </p>
                        <div class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-3">
                            <a href="#paket" class="rk-focus-light inline-flex min-h-12 items-center rounded-lg bg-accent px-6 text-base font-bold text-ink hover:brightness-110">
                                Lihat paket
                            </a>
                            <a href="/member/login" class="rk-focus-light inline-flex min-h-11 items-center font-semibold underline underline-offset-4">
                                Sudah punya akun? Masuk
                            </a>
                        </div>
                    </div>

                    {{-- Ilustrasi kartu member (hiasan, bukan data asli). --}}
                    <div class="mx-auto hidden w-full max-w-sm lg:block" aria-hidden="true">
                        <div class="relative aspect-[85.6/54] rotate-3 overflow-hidden rounded-xl bg-surface p-5 text-ink shadow-[10px_10px_0_0_var(--color-ink)]">
                            <span class="rk-wordmark">{{ config('app.name') }}<span class="rk-wordmark-dot">.</span></span>
                            <div class="absolute bottom-7 left-5 flex items-end gap-3">
                                <div class="flex aspect-[3/4] w-16 items-end justify-center overflow-hidden bg-brand-100 text-brand-300">
                                    <svg viewBox="0 0 60 70" class="w-14" fill="currentColor"><circle cx="30" cy="24" r="14"/><path d="M4 70c0-17 11-26 26-26s26 9 26 26z"/></svg>
                                </div>
                                <div>
                                    <div class="h-2.5 w-28 rounded-full bg-ink"></div>
                                    <div class="mt-2 font-mono text-xs text-muted">RPK-0000-0000</div>
                                </div>
                            </div>
                            <svg viewBox="0 0 21 21" class="absolute bottom-7 right-5 size-20" fill="currentColor"><path d="M0 0h7v7H0zM1 1v5h5V1zM2 2h3v3H2zM14 0h7v7h-7zM15 1v5h5V1zM16 2h3v3h-3zM0 14h7v7H0zM1 15v5h5v-5zM2 16h3v3H2zM9 0h1v2H9zM11 1h2v1h-2zM9 4h3v1H9zM10 6h2v2h-2zM0 9h2v1H0zM3 9h1v3H3zM5 10h3v1H5zM9 9h2v2H9zM12 10h1v3h-1zM14 9h3v1h-3zM18 9h3v2h-3zM15 11h2v2h-2zM9 13h2v1H9zM9 15h1v3H9zM11 16h3v1h-3zM15 15h2v2h-2zM18 14h1v3h-1zM20 13h1v2h-1zM12 19h2v2h-2zM16 19h5v1h-5zM19 17h2v1h-2zM10 19h1v2h-1z"/></svg>
                            <div class="absolute inset-x-0 bottom-0 h-2 bg-accent"></div>
                        </div>
                    </div>
                </div>
            </section>

            <section aria-labelledby="keunggulan" class="border-b border-line bg-surface">
                <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-14">
                    <h2 id="keunggulan" class="font-display text-2xl font-extrabold italic tracking-tight sm:text-3xl">Kenapa pakai kartu member</h2>
                    <div class="mt-8 grid gap-8 md:grid-cols-3">
                        <div class="border-t-4 border-brand-600 pt-4">
                            <h3 class="text-lg font-bold">Satu kartu, satu orang</h3>
                            <p class="mt-2 text-muted">Kartu memuat foto Anda dan QR yang hanya berlaku untuk Anda. Kartu hilang bisa diterbitkan ulang, kartu lama langsung mati.</p>
                        </div>
                        <div class="border-t-4 border-brand-600 pt-4">
                            <h3 class="text-lg font-bold">Check-in hitungan detik</h3>
                            <p class="mt-2 text-muted">Tunjukkan kartu cetak atau QR di HP ke meja depan. Tanpa tanda tangan di buku, tanpa antre lama.</p>
                        </div>
                        <div class="border-t-4 border-brand-600 pt-4">
                            <h3 class="text-lg font-bold">Masa aktif selalu jelas</h3>
                            <p class="mt-2 text-muted">Sisa hari, kuota kunjungan, riwayat kehadiran, dan riwayat pembayaran bisa Anda lihat sendiri kapan saja.</p>
                        </div>
                    </div>
                </div>
            </section>

            @if ($announcements->isNotEmpty())
                <section aria-labelledby="judul-pengumuman" class="border-b border-line bg-surface">
                    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-14">
                        <h2 id="judul-pengumuman" class="font-display text-2xl font-extrabold italic tracking-tight sm:text-3xl">Pengumuman</h2>
                        <div class="mt-8 grid gap-8 md:grid-cols-2">
                            @foreach ($announcements as $announcement)
                                <article class="border-t-4 border-brand-600 pt-4">
                                    <h3 class="text-lg font-bold">{{ $announcement->title }}</h3>
                                    <p class="mt-1 text-sm text-muted">{{ $announcement->starts_at->format('d M Y') }}</p>
                                    <p class="mt-3 text-muted">{!! nl2br(e($announcement->body)) !!}</p>
                                </article>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif

            <section id="paket" aria-labelledby="judul-paket" class="scroll-mt-4">
                <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-14">
                    <h2 id="judul-paket" class="font-display text-2xl font-extrabold italic tracking-tight sm:text-3xl">Paket Membership</h2>
                    <p class="mt-2 text-muted">Pilih paket, lalu bayar saat aktivasi di gym.</p>

                    <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @forelse ($plans as $plan)
                            <article class="flex flex-col rounded-xl border border-line bg-surface p-5">
                                <h3 class="font-display text-xl font-extrabold">{{ $plan->name }}</h3>
                                @if ($plan->description)
                                    <p class="mt-1 text-sm text-muted">{{ $plan->description }}</p>
                                @endif
                                <p class="mt-4 text-3xl font-bold tabular-nums tracking-tight text-brand-600">Rp {{ number_format($plan->price, 0, ',', '.') }}</p>
                                <dl class="mt-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 border-t border-line pt-4 text-sm">
                                    <dt class="text-muted">Durasi</dt>
                                    <dd class="text-right font-semibold">{{ $plan->duration_days }} hari</dd>
                                    <dt class="text-muted">Kuota</dt>
                                    <dd class="text-right font-semibold">{{ $plan->visit_quota ? $plan->visit_quota.' kunjungan' : 'Tanpa batas' }}</dd>
                                </dl>
                                <a
                                    href="/member/register?plan={{ $plan->id }}"
                                    aria-label="Daftar paket {{ $plan->name }}"
                                    class="rk-focus mt-5 inline-flex min-h-12 items-center justify-center rounded-lg bg-brand-600 px-5 text-base font-bold text-white hover:bg-brand-700"
                                >
                                    Daftar
                                </a>
                            </article>
                        @empty
                            <p class="text-muted">Belum ada paket tersedia.</p>
                        @endforelse
                    </div>
                </div>
            </section>

            <section aria-labelledby="cara-daftar" class="bg-ink text-white">
                <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-14">
                    <h2 id="cara-daftar" class="font-display text-2xl font-extrabold italic tracking-tight sm:text-3xl">Cara bergabung</h2>
                    <ol class="mt-8 grid gap-8 md:grid-cols-3">
                        @foreach ([
                            ['Daftar dari HP', 'Pilih paket, buat akun, lalu unggah foto wajah Anda.'],
                            ['Aktivasi di gym', 'Datang ke meja depan. Petugas mencocokkan wajah dengan foto Anda dan mencatat pembayaran.'],
                            ['Mulai latihan', 'Kartu member langsung terbit. Cetak sendiri atau tunjukkan QR dari HP setiap datang.'],
                        ] as $i => [$judul, $isi])
                            <li class="flex gap-4">
                                <span class="font-display text-5xl font-extrabold italic leading-none text-accent">{{ $i + 1 }}</span>
                                <div>
                                    <h3 class="text-lg font-bold">{{ $judul }}</h3>
                                    <p class="mt-1 text-brand-100">{{ $isi }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    <a href="#paket" class="rk-focus-light mt-10 inline-flex min-h-12 items-center rounded-lg bg-accent px-6 text-base font-bold text-ink hover:brightness-110">
                        Pilih paket
                    </a>
                </div>
            </section>
        </main>

        <footer class="border-t border-white/10 bg-ink text-brand-100">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-8 gap-y-2 px-4 py-6 text-sm sm:px-6">
                <p><span class="font-semibold text-white">{{ config('app.name') }}</span> &copy; {{ now()->year }}</p>
                <nav aria-label="Tautan bawah" class="flex gap-6">
                    <a href="/member/login" class="rk-focus-light inline-flex min-h-11 items-center underline underline-offset-4">Masuk member</a>
                    <a href="/admin/login" class="rk-focus-light inline-flex min-h-11 items-center underline underline-offset-4">Masuk pegawai</a>
                </nav>
            </div>
        </footer>
    </body>
</html>
