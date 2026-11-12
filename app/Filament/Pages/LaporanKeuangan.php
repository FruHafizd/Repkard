<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Services\FinanceReportService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;

class LaporanKeuangan extends Page
{
    use HasPageShield;

    protected static ?string $title = 'Laporan Keuangan';

    protected static ?string $navigationLabel = 'Laporan Keuangan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected string $view = 'filament.pages.laporan-keuangan';

    /** Bulan terpilih, format 'Y-m'. */
    public string $bulan = '';

    public function mount(): void
    {
        $this->bulan = now()->format('Y-m');
    }

    /** @return array<string, string> 24 bulan terakhir, mis. '2026-11' => 'November 2026' */
    public function monthOptions(): array
    {
        $options = [];
        $month = now()->startOfMonth();
        for ($i = 0; $i < 24; $i++) {
            $options[$month->format('Y-m')] = $month->translatedFormat('F Y');
            $month = $month->subMonthNoOverflow();
        }

        return $options;
    }

    /** Bulan yang valid; nilai di luar daftar (mis. dari request palsu) jatuh ke bulan ini. */
    public function selectedMonth(): CarbonImmutable
    {
        $bulan = array_key_exists($this->bulan, $this->monthOptions()) ? $this->bulan : now()->format('Y-m');

        return CarbonImmutable::createFromFormat('Y-m-d', $bulan.'-01', config('app.timezone'))->startOfMonth();
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function report(): array
    {
        $month = $this->selectedMonth();

        return app(FinanceReportService::class)->monthly($month->year, $month->month);
    }

    /**
     * Perbandingan dengan bulan lalu; null bila bulan lalu 0 (persentase tidak bermakna).
     *
     * @return array{text: string, up: bool}|null
     */
    public function comparison(int $current, int $previous): ?array
    {
        if ($previous === 0) {
            return null;
        }

        $percent = round(($current - $previous) / abs($previous) * 100, 1);

        return [
            'text' => ($percent > 0 ? '+' : '').number_format($percent, 1, ',', '.').'% dari bulan lalu',
            'up' => $percent >= 0,
        ];
    }

    public function rupiah(int $amount): string
    {
        return ($amount < 0 ? '−' : '').PaymentResource::rupiah(abs($amount));
    }
}
