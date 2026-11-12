<?php

namespace App\Filament\Pages;

use App\Enums\PtSessionStatus;
use App\Models\PtSession;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class KomisiTrainer extends Page
{
    use HasPageShield;

    protected static ?string $title = 'Komisi Trainer';

    protected static ?string $navigationLabel = 'Komisi Trainer';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected string $view = 'filament.pages.komisi-trainer';

    /** Format Y-m. */
    public string $bulan = '';

    public function mount(): void
    {
        $this->bulan = now()->format('Y-m');
    }

    /** @return array<string, string> */
    public function bulanOptions(): array
    {
        $options = [];

        for ($i = 0; $i < 12; $i++) {
            $m = now()->startOfMonth()->subMonths($i);
            $options[$m->format('Y-m')] = $m->translatedFormat('F Y');
        }

        return $options;
    }

    /** @return array<int, array{trainer: string, selesai: int, tidak_datang: int, komisi: int}> */
    public function rows(): array
    {
        $user = auth()->user();
        $start = Carbon::createFromFormat('Y-m-d', $this->bulan.'-01')->startOfMonth();

        return PtSession::query()
            ->with('trainer')
            ->whereIn('status', [PtSessionStatus::Completed->value, PtSessionStatus::NoShow->value])
            ->whereBetween('finished_at', [$start, $start->copy()->endOfMonth()])
            // Tanpa izin "lihat semua", trainer hanya melihat barisnya sendiri.
            ->when(! $user?->can('ViewAll:PtSession'), fn ($q) => $q->where('trainer_id', $user?->getKey()))
            ->get()
            ->groupBy('trainer_id')
            ->map(fn ($sessions): array => [
                'trainer' => $sessions->first()->trainer->name,
                'selesai' => $sessions->where('status', PtSessionStatus::Completed)->count(),
                'tidak_datang' => $sessions->where('status', PtSessionStatus::NoShow)->count(),
                'komisi' => (int) $sessions->sum('commission_amount'),
            ])
            ->sortBy('trainer')
            ->values()
            ->all();
    }

    protected function getViewData(): array
    {
        return ['rows' => $this->rows(), 'bulanOptions' => $this->bulanOptions()];
    }
}
