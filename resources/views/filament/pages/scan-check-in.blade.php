<x-filament-panels::page>
    @vite('resources/js/scanner.js')

    @php($result = $this->currentResult())

    <x-filament::section heading="Pindai kartu">
        <div wire:ignore id="reader" x-data x-init="window.startScanner('reader', (text) => $wire.scan(text))"></div>

        <div style="margin-top: 1rem">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="text"
                    autofocus
                    placeholder="Scanner USB: arahkan kartu, hasil otomatis terisi"
                    autocomplete="off"
                    x-on:keydown.enter.prevent="$wire.scan($event.target.value); $event.target.value = ''"
                />
            </x-filament::input.wrapper>
        </div>
    </x-filament::section>

    @if ($rejectedReason)
        <x-filament::section heading="Ditolak" icon="heroicon-o-x-circle" icon-color="danger">
            <x-filament::badge color="danger" size="lg">{{ $rejectedReason }}</x-filament::badge>
        </x-filament::section>
    @endif

    @if ($result?->allowed)
        @php($member = $result->member)
        @php($membership = $result->membership)
        <x-filament::section heading="Diterima — cocokkan wajah" icon="heroicon-o-check-circle" icon-color="success">
            <div style="display: flex; flex-wrap: wrap; gap: 1.5rem">
                @if ($member->photoUrl())
                    <img src="{{ $member->photoUrl() }}" alt="Foto member" width="320" style="min-width: 300px; max-width: 100%; height: auto">
                @endif

                <dl>
                    <dt>Nama</dt>
                    <dd><strong>{{ $member->user?->name }}</strong></dd>
                    <dt>Nomor member</dt>
                    <dd>{{ $member->member_number }}</dd>
                    <dt>Paket</dt>
                    <dd>{{ $membership->plan?->name }}</dd>
                    <dt>Berlaku sampai</dt>
                    <dd>{{ $membership->ends_on->translatedFormat('d M Y') }}</dd>
                    <dt>Sisa hari</dt>
                    <dd>{{ (int) now()->startOfDay()->diffInDays($membership->ends_on, false) }}</dd>
                    <dt>Sisa kuota</dt>
                    <dd>{{ $membership->visits_remaining ?? 'Tanpa batas' }}</dd>
                </dl>
            </div>

            <div style="display: flex; gap: 0.75rem; margin-top: 1rem">
                <x-filament::button color="success" wire:click="konfirmasi">Wajah cocok — Check-in</x-filament::button>
                <x-filament::button color="danger" wire:click="tolakWajah">Wajah tidak cocok — Tolak</x-filament::button>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
