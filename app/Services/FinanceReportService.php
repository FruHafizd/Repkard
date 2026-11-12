<?php

namespace App\Services;

use App\Enums\PaymentType;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\WalkIn;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Laporan keuangan bulanan. Semua angka dihitung dengan SUM di database;
 * rentang bulan memakai zona waktu aplikasi (kolom waktu disimpan dalam zona itu).
 */
class FinanceReportService
{
    /**
     * @return array{
     *   income: array{membership: int, pt: int, walk_in: int, kasir: int, refunds: int, total_income: int},
     *   expenses: array{by_category: array<string, int>, total_expenses: int},
     *   net: int,
     *   daily: array<string, array{income: int, expenses: int}>,
     *   previous: array{total_income: int, total_expenses: int, net: int}
     * }
     */
    public function monthly(int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, config('app.timezone'));
        $summary = $this->summary($start);
        $previous = $this->summary($start->subMonthNoOverflow());

        return $summary + [
            'daily' => $this->daily($start),
            'previous' => [
                'total_income' => $previous['income']['total_income'],
                'total_expenses' => $previous['expenses']['total_expenses'],
                'net' => $previous['net'],
            ],
        ];
    }

    private function summary(CarbonImmutable $start): array
    {
        [$from, $to] = $this->range($start);

        $payments = fn (array $types): int => (int) Payment::query()
            ->whereNull('cancelled_at')->whereIn('type', $types)
            ->whereBetween('paid_at', [$from, $to])->sum('amount');

        $membership = $payments([PaymentType::Purchase->value, PaymentType::Upgrade->value]);
        $pt = $payments([PaymentType::Pt->value]);
        $walkIn = (int) WalkIn::query()->notCancelled()->whereBetween('visited_at', [$from, $to])->sum('amount');
        $kasir = (int) Sale::query()->notCancelled()->whereBetween('sold_at', [$from, $to])->sum('total');
        $refunds = (int) Refund::query()->whereBetween('refunded_at', [$from, $to])->sum('amount');

        $byCategory = Expense::query()->notCancelled()
            ->whereBetween('spent_on', [$start->toDateString(), $start->endOfMonth()->toDateString()])
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->groupBy('expense_categories.name')
            ->orderBy('expense_categories.name')
            ->selectRaw('expense_categories.name as name, SUM(expenses.amount) as total')
            ->pluck('total', 'name')
            ->map(fn ($total): int => (int) $total)
            ->all();

        $totalIncome = $membership + $pt + $walkIn + $kasir - $refunds;
        $totalExpenses = array_sum($byCategory);

        return [
            'income' => [
                'membership' => $membership,
                'pt' => $pt,
                'walk_in' => $walkIn,
                'kasir' => $kasir,
                'refunds' => $refunds,
                'total_income' => $totalIncome,
            ],
            'expenses' => ['by_category' => $byCategory, 'total_expenses' => $totalExpenses],
            'net' => $totalIncome - $totalExpenses,
        ];
    }

    /** @return array<string, array{income: int, expenses: int}> kunci 'Y-m-d' untuk setiap tanggal di bulan itu */
    private function daily(CarbonImmutable $start): array
    {
        [$from, $to] = $this->range($start);
        $fromDate = $start->toDateString();
        $toDate = $start->endOfMonth()->toDateString();

        $days = [];
        for ($day = $start; $day <= $start->endOfMonth(); $day = $day->addDay()) {
            $days[$day->toDateString()] = ['income' => 0, 'expenses' => 0];
        }

        $sum = fn (Builder $query, string $dateColumn, string $amountColumn): array => $query
            ->groupBy(DB::raw("DATE($dateColumn)"))
            ->selectRaw("DATE($dateColumn) as day, SUM($amountColumn) as total")
            ->pluck('total', 'day')->all();

        $payments = $sum(Payment::query()->whereNull('cancelled_at')->whereBetween('paid_at', [$from, $to]), 'paid_at', 'amount');
        $walkIns = $sum(WalkIn::query()->notCancelled()->whereBetween('visited_at', [$from, $to]), 'visited_at', 'amount');
        $sales = $sum(Sale::query()->notCancelled()->whereBetween('sold_at', [$from, $to]), 'sold_at', 'total');
        $refunds = $sum(Refund::query()->whereBetween('refunded_at', [$from, $to]), 'refunded_at', 'amount');
        $expenses = $sum(Expense::query()->notCancelled()->whereBetween('spent_on', [$fromDate, $toDate]), 'spent_on', 'amount');

        foreach ($days as $date => $_) {
            $days[$date] = [
                'income' => (int) ($payments[$date] ?? 0) + (int) ($walkIns[$date] ?? 0)
                    + (int) ($sales[$date] ?? 0) - (int) ($refunds[$date] ?? 0),
                'expenses' => (int) ($expenses[$date] ?? 0),
            ];
        }

        return $days;
    }

    /** @return array{0: string, 1: string} awal & akhir bulan dalam zona waktu aplikasi */
    private function range(CarbonImmutable $start): array
    {
        return [$start->startOfMonth()->toDateTimeString(), $start->endOfMonth()->toDateTimeString()];
    }
}
