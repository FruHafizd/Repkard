<?php

namespace App\Filament\Widgets;

use App\Enums\MemberStatus;
use App\Filament\Pages\MemberMenghilang;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\CheckIn;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\WalkIn;
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

        $menghilang = Member::query()
            ->mulaiMenghilang((int) config('repkard.inactive_warning_days'))
            ->count();

        $checkInHariIni = CheckIn::query()
            ->where('result', CheckIn::RESULT_ACCEPTED)
            ->whereBetween('checked_in_at', [now()->startOfDay(), now()->endOfDay()])
            ->count();

        $user = auth()->user();
        $rentang = [now()->startOfDay(), now()->endOfDay()];

        // Pemasukan hari ini = pembayaran member + pendatang − refund, tidak termasuk yang dibatalkan.
        // Tanpa izin "lihat semua", hanya yang dicatat sendiri.
        $pemasukan = Payment::query()
            ->whereNull('cancelled_at')
            ->whereBetween('paid_at', $rentang)
            ->when(! $user?->can('ViewAll:Payment'), fn (Builder $q) => $q->where('recorded_by', $user?->getKey()))
            ->sum('amount')
            + WalkIn::query()
                ->notCancelled()
                ->whereBetween('visited_at', $rentang)
                ->when(! $user?->can('ViewAll:WalkIn'), fn (Builder $q) => $q->where('recorded_by', $user?->getKey()))
                ->sum('amount');

        // Refund tidak pernah dicatat pegawai, jadi hanya dikurangkan bagi yang melihat semua pembayaran.
        if ($user?->can('ViewAll:Payment')) {
            $pemasukan -= Refund::query()->whereBetween('refunded_at', $rentang)->sum('amount');
        }

        return [
            Stat::make('Pemasukan hari ini', PaymentResource::rupiah($pemasukan))->icon(Heroicon::OutlinedBanknotes),
            Stat::make('Member aktif', $aktif)->icon(Heroicon::OutlinedUserGroup),
            Stat::make('Menunggu aktivasi', $menunggu)->icon(Heroicon::OutlinedClock),
            Stat::make('Habis dalam 7 hari', $habisDalam7Hari)->icon(Heroicon::OutlinedExclamationTriangle),
            Stat::make('Mulai menghilang', $menghilang)
                ->icon(Heroicon::OutlinedUserMinus)
                ->url($user?->can('View:MemberMenghilang') ? MemberMenghilang::getUrl() : null),
            Stat::make('Check-in hari ini', $checkInHariIni)->icon(Heroicon::OutlinedQrCode),
        ];
    }
}
