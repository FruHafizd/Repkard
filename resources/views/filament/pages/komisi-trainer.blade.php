<x-filament-panels::page>
    <x-filament::section>
        <label for="bulan" class="fi-fo-field-label-content">Bulan</label>
        <x-filament::input.wrapper>
            <x-filament::input.select id="bulan" wire:model.live="bulan">
                @foreach ($bulanOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </x-filament::section>

    <x-filament::section heading="Komisi per trainer">
        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-start">
                <thead>
                    <tr>
                        <th class="fi-ta-header-cell text-start">Trainer</th>
                        <th class="fi-ta-header-cell text-end">Selesai</th>
                        <th class="fi-ta-header-cell text-end">Tidak datang</th>
                        <th class="fi-ta-header-cell text-end">Total komisi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="fi-ta-cell">{{ $row['trainer'] }}</td>
                            <td class="fi-ta-cell text-end">{{ $row['selesai'] }}</td>
                            <td class="fi-ta-cell text-end">{{ $row['tidak_datang'] }}</td>
                            <td class="fi-ta-cell text-end">Rp {{ number_format($row['komisi'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td class="fi-ta-cell" colspan="4">Belum ada sesi yang ditandai di bulan ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
