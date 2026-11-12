<?php

namespace App\Filament\Member\Widgets;

use App\Enums\MemberStatus;
use App\Models\CheckIn;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MembershipStatusWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -10;

    /** Di HP: status selebar layar, sisanya dua kolom. */
    protected int|array|null $columns = ['default' => 2, '@xl' => 3, '!@lg' => 3];

    public static function canView(): bool
    {
        return Filament::auth()->user()?->member?->status === MemberStatus::Active;
    }

    protected function getStats(): array
    {
        $member = Filament::auth()->user()->member;
        $membership = $member->currentMembership()?->load('plan');

        $status = match (true) {
            $membership === null => 'Habis',
            $membership->isFrozen() => 'Dibekukan',
            $membership->visits_remaining === 0 => 'Habis',
            default => 'Aktif',
        };

        $stats = [
            Stat::make('Status membership', $status)
                ->description(match ($status) {
                    'Aktif' => 'Tunjukkan kartu di meja depan',
                    'Dibekukan' => 'Sedang cuti sementara',
                    default => 'Perpanjang di meja depan',
                })
                ->descriptionIcon(match ($status) {
                    'Aktif' => Heroicon::CheckCircle,
                    'Dibekukan' => Heroicon::PauseCircle,
                    default => Heroicon::XCircle,
                })
                ->columnSpanFull()
                ->extraAttributes(['class' => 'rk-status rk-status-'.match ($status) {
                    'Aktif' => 'ok',
                    'Dibekukan' => 'hold',
                    default => 'no',
                }]),
            Stat::make('Paket', $membership?->plan?->name ?? '-'),
            Stat::make('Berlaku sampai', $membership?->ends_on->format('d M Y') ?? '-'),
            Stat::make('Sisa hari', $membership ? (int) now()->startOfDay()->diffInDays($membership->ends_on) + 1 : 0),
        ];

        if ($membership?->visits_remaining !== null) {
            $stats[] = Stat::make('Sisa kuota', $membership->visits_remaining);
        }

        $stats[] = Stat::make('Kehadiran bulan ini', CheckIn::query()
            ->where('member_id', $member->id)
            ->where('result', CheckIn::RESULT_ACCEPTED)
            ->whereBetween('checked_in_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count());

        return $stats;
    }
}
