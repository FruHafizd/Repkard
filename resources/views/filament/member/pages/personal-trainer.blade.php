<x-filament-panels::page>
    @forelse ($subscriptions as $pt)
        <x-filament::section :heading="$pt->package->name" icon="heroicon-o-user-group">
            <x-slot name="afterHeader">
                <x-filament::badge :color="$pt->statusLabel() === 'Aktif' ? 'success' : 'gray'">{{ $pt->statusLabel() }}</x-filament::badge>
            </x-slot>
            <dl>
                <div><dt>Trainer</dt><dd>{{ $pt->trainer->name }}</dd></div>
                <div><dt>Sisa sesi</dt><dd>{{ $pt->sessions_remaining }} dari {{ $pt->sessions_total }}</dd></div>
                <div><dt>Berlaku sampai</dt><dd>{{ $pt->expires_on->format('d M Y') }}</dd></div>
            </dl>
        </x-filament::section>
    @empty
        <x-filament::section heading="Belum ada paket PT" icon="heroicon-o-information-circle">
            <p>Tanyakan paket personal trainer ke petugas.</p>
        </x-filament::section>
    @endforelse
</x-filament-panels::page>
