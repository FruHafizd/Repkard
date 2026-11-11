<x-filament-panels::page>
    @if ($card)
        <x-filament::section>
            <div style="display: flex; gap: 24px; flex-wrap: wrap; align-items: flex-start">
                @if ($member->photoUrl())
                    <img src="{{ $member->photoUrl() }}" alt="Foto member" width="120" style="max-width: 100%; height: auto">
                @endif
                <div>
                    <p><strong>{{ $member->user->name }}</strong></p>
                    <p>No. Member: {{ $member->member_number }}</p>
                </div>
                <img src="{{ $qr }}" alt="QR kartu" width="160" height="160">
            </div>
        </x-filament::section>

        <div>
            <x-filament::button tag="a" :href="route('member.card.pdf', $member)" target="_blank">
                Unduh / Cetak PDF
            </x-filament::button>
        </div>
    @else
        <x-filament::section>
            <p>Kartu tersedia setelah akun diaktifkan.</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
