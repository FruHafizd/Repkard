<x-filament-widgets::widget>
    <x-filament::section heading="Akun Anda menunggu aktivasi">
        @if ($plan)
            <p>Paket pilihan: <strong>{{ $plan->name }}</strong></p>
            <p>Total bayar: <strong>Rp {{ number_format($plan->price, 0, ',', '.') }}</strong></p>
        @else
            <p>Anda belum memilih paket.</p>
        @endif

        <p>Datang ke gym, tunjukkan wajah Anda ke petugas, dan lakukan pembayaran. Kartu member akan langsung terbit.</p>

        <form wire:submit="gantiPaket">
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model="planId">
                    @foreach ($plans as $option)
                        <option value="{{ $option->id }}">{{ $option->name }} — Rp {{ number_format($option->price, 0, ',', '.') }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
            @error('planId') <p>{{ $message }}</p> @enderror

            <x-filament::button type="submit">Ganti paket</x-filament::button>
        </form>
    </x-filament::section>
</x-filament-widgets::widget>
