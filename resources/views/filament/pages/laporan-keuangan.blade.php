<x-filament-panels::page>
    @php($report = $this->report)
    @php($income = $report['income'])
    @php($expenses = $report['expenses'])
    @php($previous = $report['previous'])

    <div class="rk-fin">
        <div>
            <label for="rk-fin-bulan" class="rk-fin-label">Bulan</label>
            <div>
                <select id="rk-fin-bulan" wire:model.live="bulan" class="rk-fin-select">
                    @foreach ($this->monthOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="rk-fin-stats">
            @foreach ([
                ['Pemasukan', $income['total_income'], $previous['total_income']],
                ['Pengeluaran', $expenses['total_expenses'], $previous['total_expenses']],
                ['Laba bersih', $report['net'], $previous['net']],
            ] as [$label, $current, $before])
                @php($delta = $this->comparison($current, $before))
                <div class="rk-fin-card">
                    <div class="rk-fin-label">{{ $label }}</div>
                    <div class="rk-fin-value {{ $current < 0 ? 'rk-fin-neg' : '' }}">{{ $this->rupiah($current) }}</div>
                    @if ($delta)
                        <div class="rk-fin-delta {{ $delta['up'] ? '' : 'rk-fin-neg' }}">{{ $delta['text'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="rk-fin-card">
            @livewire(\App\Filament\Charts\FinanceDailyChart::class, ['bulan' => $this->selectedMonth()->format('Y-m')], key('chart-'.$this->selectedMonth()->format('Y-m')))
        </div>

        <div class="rk-fin-card">
            <div class="rk-fin-title">Pemasukan per sumber</div>
            <div class="rk-fin-scroll">
                <table class="rk-fin-table">
                    <thead>
                        <tr><th>Sumber</th><th>Jumlah</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Membership</td><td>{{ $this->rupiah($income['membership']) }}</td></tr>
                        <tr><td>Personal Trainer</td><td>{{ $this->rupiah($income['pt']) }}</td></tr>
                        <tr><td>Pendatang</td><td>{{ $this->rupiah($income['walk_in']) }}</td></tr>
                        <tr><td>Kasir</td><td>{{ $this->rupiah($income['kasir']) }}</td></tr>
                        <tr><td>Refund</td><td class="{{ $income['refunds'] > 0 ? 'rk-fin-neg' : '' }}">{{ $this->rupiah(-$income['refunds']) }}</td></tr>
                    </tbody>
                    <tfoot>
                        <tr><td>Total pemasukan</td><td>{{ $this->rupiah($income['total_income']) }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="rk-fin-card">
            <div class="rk-fin-title">Pengeluaran per kategori</div>
            <div class="rk-fin-scroll">
                <table class="rk-fin-table">
                    <thead>
                        <tr><th>Kategori</th><th>Jumlah</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($expenses['by_category'] as $name => $total)
                            <tr><td>{{ $name }}</td><td>{{ $this->rupiah($total) }}</td></tr>
                        @empty
                            <tr><td colspan="2">Belum ada pengeluaran bulan ini.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr><td>Total pengeluaran</td><td>{{ $this->rupiah($expenses['total_expenses']) }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="rk-fin-card">
            <div class="rk-fin-title">Per hari</div>
            <div class="rk-fin-scroll">
                <table class="rk-fin-table">
                    <thead>
                        <tr><th>Tanggal</th><th>Pemasukan</th><th>Pengeluaran</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($report['daily'] as $date => $row)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($date)->translatedFormat('d M Y') }}</td>
                                <td>{{ $this->rupiah($row['income']) }}</td>
                                <td>{{ $this->rupiah($row['expenses']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
