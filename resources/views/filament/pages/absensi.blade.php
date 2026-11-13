<x-filament-panels::page>
    @php
        $belumMasuk = $today === null;
        $sudahMasuk = $today !== null && $today->clock_out_at === null;
        $sudahPulang = $today !== null && $today->clock_out_at !== null;
    @endphp

    <x-filament::section heading="Status hari ini">
        <p class="text-lg font-semibold" data-testid="status-absensi">
            @if ($belumMasuk)
                Belum absen masuk
            @elseif ($sudahMasuk)
                Sudah absen masuk pukul {{ $today->clock_in_at->format('H:i') }}
                @if ($today->is_late)
                    <x-filament::badge color="danger" class="ms-2 inline-flex">Terlambat</x-filament::badge>
                @endif
            @else
                Sudah absen pulang pukul {{ $today->clock_out_at->format('H:i') }}
                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">(masuk {{ $today->clock_in_at->format('H:i') }})</span>
            @endif
        </p>
    </x-filament::section>

    @unless ($sudahPulang)
        <x-filament::section :heading="$belumMasuk ? 'Absen masuk' : 'Absen pulang'" description="Ambil swafoto lewat kamera. Foto wajib supaya absen tidak bisa dititipkan.">
            {{ $this->form }}

            <div class="mt-4">
                <x-filament::button
                    size="xl"
                    wire:click="absen"
                    wire:loading.attr="disabled"
                    wire:target="absen"
                    :disabled="blank($data['photo'] ?? null)"
                    class="w-full sm:w-auto"
                >
                    {{ $belumMasuk ? 'Absen Masuk' : 'Absen Pulang' }}
                </x-filament::button>
            </div>
        </x-filament::section>
    @endunless

    <x-filament::section heading="Riwayat bulan ini">
        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-start">
                <thead>
                    <tr>
                        <th class="fi-ta-header-cell px-3 py-2 text-start">Tanggal</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-start">Masuk</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-start">Pulang</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-start">Durasi</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-start">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($riwayat as $row)
                        <tr>
                            <td class="fi-ta-cell px-3 py-2">{{ $row->clock_in_at->translatedFormat('d M Y') }}</td>
                            <td class="fi-ta-cell px-3 py-2">{{ $row->clock_in_at->format('H:i') }}</td>
                            <td class="fi-ta-cell px-3 py-2">{{ $row->clock_out_at?->format('H:i') ?? '-' }}</td>
                            <td class="fi-ta-cell px-3 py-2">{{ $row->durationLabel() ?? '-' }}</td>
                            <td class="fi-ta-cell px-3 py-2">
                                <div class="flex flex-wrap gap-1">
                                    @if ($row->is_late)
                                        <x-filament::badge color="danger">Terlambat</x-filament::badge>
                                    @endif
                                    @if ($row->isForgotClockOut())
                                        <x-filament::badge color="warning">Lupa pulang</x-filament::badge>
                                    @endif
                                    @if ($row->isCorrected())
                                        <x-filament::badge color="gray">Dikoreksi</x-filament::badge>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="fi-ta-cell px-3 py-2" colspan="5">Belum ada absensi bulan ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
