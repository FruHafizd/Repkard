<?php

namespace App\Filament\Widgets;

use App\Enums\MemberStatus;
use App\Models\CheckIn;
use App\Models\Member;
use App\Models\Membership;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class AdminStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $today = now()->toDateString();

        $aktif = Member::query()
            ->where('status', MemberStatus::Active)
            ->whereHas('memberships', fn (Builder $q) => $q
                ->whereNull('cancelled_at')
                ->whereDate('starts_on', '<=', $today)
                ->whereDate('ends_on', '>=', $today))
            ->count();

        $menunggu = Member::query()
            ->where('status', MemberStatus::Pending)
            ->count();

        $habisDalam7Hari = Membership::query()
            ->whereNull('cancelled_at')
            ->whereBetween('ends_on', [$today, now()->addDays(7)->toDateString()])
            ->whereHas('member', fn (Builder $q) => $q->where('status', MemberStatus::Active))
            ->distinct('member_id')
            ->count('member_id');

        $checkInHariIni = CheckIn::query()
            ->where('result', CheckIn::RESULT_ACCEPTED)
            ->whereBetween('checked_in_at', [now()->startOfDay(), now()->endOfDay()])
            ->count();

        return [
            Stat::make('Member aktif', $aktif)->icon(Heroicon::OutlinedUserGroup),
            Stat::make('Menunggu aktivasi', $menunggu)->icon(Heroicon::OutlinedClock),
            Stat::make('Habis dalam 7 hari', $habisDalam7Hari)->icon(Heroicon::OutlinedExclamationTriangle),
            Stat::make('Check-in hari ini', $checkInHariIni)->icon(Heroicon::OutlinedQrCode),
        ];
    }
}
