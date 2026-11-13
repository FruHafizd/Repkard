<x-filament::section heading="Ringkasan per orang" description="Mengikuti filter bulan dan orang di bawah.">
    <div class="overflow-x-auto">
        <table class="fi-ta-table w-full text-start" data-testid="ringkasan-absensi">
            <thead>
                <tr>
                    <th class="fi-ta-header-cell px-3 py-2 text-start">Nama</th>
                    <th class="fi-ta-header-cell px-3 py-2 text-end">Hari hadir</th>
                    <th class="fi-ta-header-cell px-3 py-2 text-end">Terlambat</th>
                    <th class="fi-ta-header-cell px-3 py-2 text-end">Lupa pulang</th>
                    <th class="fi-ta-header-cell px-3 py-2 text-end">Total jam</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="fi-ta-cell px-3 py-2">{{ $row['nama'] }}</td>
                        <td class="fi-ta-cell px-3 py-2 text-end">{{ $row['hadir'] }}</td>
                        <td class="fi-ta-cell px-3 py-2 text-end">{{ $row['terlambat'] }}</td>
                        <td class="fi-ta-cell px-3 py-2 text-end">{{ $row['lupa_pulang'] }}</td>
                        <td class="fi-ta-cell px-3 py-2 text-end">{{ $row['total'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="fi-ta-cell px-3 py-2" colspan="5">Belum ada absensi pada periode ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament::section>
