<?php

namespace App\Filament\Charts;

use App\Services\FinanceReportService;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;

/**
 * Grafik batang harian pemasukan vs pengeluaran. Dipasang langsung di halaman Laporan Keuangan
 * (bukan di folder Widgets, jadi tidak ikut ditemukan panel / dashboard).
 */
class FinanceDailyChart extends ChartWidget
{
    protected ?string $heading = 'Pemasukan vs pengeluaran harian';

    protected ?string $maxHeight = '320px';

    protected int|string|array $columnSpan = 'full';

    /** Bulan 'Y-m' dari halaman induk. */
    #[Reactive]
    public string $bulan = '';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $month = CarbonImmutable::createFromFormat('Y-m-d', $this->bulan.'-01', config('app.timezone'));
        $daily = app(FinanceReportService::class)->monthly($month->year, $month->month)['daily'];

        return [
            'datasets' => [
                [
                    'label' => 'Pemasukan',
                    'data' => array_column($daily, 'income'),
                    'backgroundColor' => config('design.primary.600'),
                ],
                [
                    'label' => 'Pengeluaran',
                    'data' => array_column($daily, 'expenses'),
                    'backgroundColor' => config('design.danger.600'),
                ],
            ],
            'labels' => array_map(fn (string $date): string => (string) (int) substr($date, 8, 2), array_keys($daily)),
        ];
    }
}
