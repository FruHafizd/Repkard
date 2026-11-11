<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <header class="p-4">
            <h1 class="text-2xl font-bold">{{ config('app.name') }}</h1>
            <nav>
                <a href="/member/login" class="underline">Masuk</a>
            </nav>
        </header>

        <main class="p-4">
            <h2 class="text-xl font-bold">Paket Membership</h2>

            @forelse ($plans as $plan)
                <article class="my-4 border p-4">
                    <h3 class="font-bold">{{ $plan->name }}</h3>
                    @if ($plan->description)
                        <p>{{ $plan->description }}</p>
                    @endif
                    <p>Durasi: {{ $plan->duration_days }} hari</p>
                    <p>Kuota: {{ $plan->visit_quota ? $plan->visit_quota.' kunjungan' : 'Tanpa batas' }}</p>
                    <p class="font-bold">Rp {{ number_format($plan->price, 0, ',', '.') }}</p>
                    <a href="/member/register?plan={{ $plan->id }}" class="underline">Daftar</a>
                </article>
            @empty
                <p>Belum ada paket tersedia.</p>
            @endforelse
        </main>
    </body>
</html>
