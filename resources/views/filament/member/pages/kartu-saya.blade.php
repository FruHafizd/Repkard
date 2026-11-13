<x-filament-panels::page>
    @if ($card)
        <div class="rk-card-page">
            <div class="rk-card">
                <div class="rk-card-brand">@include('partials.wordmark', ['variant' => 'white'])</div>
                @if ($member->photoUrl())
                    <img class="rk-card-photo" src="{{ $member->photoUrl() }}" alt="Foto member">
                @endif
                <div class="rk-card-info">
                    <p class="rk-card-name">{{ $member->user->name }}</p>
                    <p class="rk-card-label">No. Member</p>
                    <p class="rk-card-number">{{ $member->member_number }}</p>
                </div>
            </div>

            <div class="rk-qr">
                <p class="rk-qr-title">QR check-in</p>
                <img src="{{ $qr }}" alt="QR kartu" width="320" height="320">
                <p>Tunjukkan QR ini ke petugas di meja depan. Naikkan kecerahan layar bila sulit terbaca.</p>
            </div>
        </div>

        <div class="rk-card-actions">
            <x-filament::button tag="a" :href="route('member.card.pdf', $member)" target="_blank" icon="heroicon-o-printer" size="lg">
                Unduh / Cetak PDF
            </x-filament::button>
        </div>
    @else
        <x-filament::section heading="Kartu belum terbit" icon="heroicon-o-clock">
            <p>Kartu tersedia setelah akun diaktifkan.</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
