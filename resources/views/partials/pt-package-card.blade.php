{{-- Kartu satu paket Personal Trainer (publik, hanya baca). --}}
<article class="flex flex-col rounded-xl border border-line bg-surface p-5">
    <h3 class="font-display text-xl font-extrabold">{{ $package->name }}</h3>
    @if ($package->description)
        <p class="mt-1 text-sm text-muted">{{ $package->description }}</p>
    @endif
    <p class="mt-4 text-3xl font-bold tabular-nums tracking-tight text-brand-600">Rp {{ number_format($package->price, 0, ',', '.') }}</p>
    <p class="text-sm text-muted">± Rp {{ number_format(intdiv($package->price, max($package->sessions_count, 1)), 0, ',', '.') }} per sesi</p>
    <dl class="mt-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 border-t border-line pt-4 text-sm">
        <dt class="text-muted">Jumlah sesi</dt>
        <dd class="text-right font-semibold">{{ $package->sessions_count }} sesi</dd>
        <dt class="text-muted">Masa berlaku</dt>
        <dd class="text-right font-semibold">{{ $package->validity_days }} hari</dd>
    </dl>
</article>
