<x-filament-widgets::widget>
    <x-filament::section heading="Akun Anda menunggu aktivasi" icon="heroicon-o-clock" icon-color="warning">
        <div class="grid gap-6 md:grid-cols-2">
            <div class="grid content-start gap-4">
                @if ($plan)
                    <dl class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm text-gray-600 dark:text-gray-400">Paket pilihan</dt>
                            <dd class="text-xl font-bold text-gray-950 dark:text-white">{{ $plan->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-600 dark:text-gray-400">Total bayar</dt>
                            <dd class="text-xl font-bold tabular-nums text-gray-950 dark:text-white">Rp {{ number_format($plan->price, 0, ',', '.') }}</dd>
                        </div>
                    </dl>
                @else
                    <p class="font-semibold text-gray-950 dark:text-white">Anda belum memilih paket.</p>
                @endif

                <p class="text-gray-700 dark:text-gray-300">Datang ke gym, tunjukkan wajah Anda ke petugas, dan lakukan pembayaran. Kartu member akan langsung terbit.</p>
            </div>

            <form wire:submit="gantiPaket" class="grid content-start gap-3">
                <label for="rk-plan" class="text-sm font-medium text-gray-950 dark:text-white">Ingin paket lain?</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="rk-plan" wire:model="planId">
                        @foreach ($plans as $option)
                            <option value="{{ $option->id }}">{{ $option->name }} — Rp {{ number_format($option->price, 0, ',', '.') }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
                @error('planId') <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p> @enderror

                <div>
                    <x-filament::button type="submit" color="gray">Ganti paket</x-filament::button>
                </div>
            </form>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
