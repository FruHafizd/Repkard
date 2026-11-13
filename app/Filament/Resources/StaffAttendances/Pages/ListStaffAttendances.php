<?php

namespace App\Filament\Resources\StaffAttendances\Pages;

use App\Filament\Resources\StaffAttendances\StaffAttendanceResource;
use App\Models\StaffAttendance;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ListStaffAttendances extends ListRecords
{
    protected static string $resource = StaffAttendanceResource::class;

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.resources.staff-attendances.ringkasan')
                ->viewData(fn (): array => ['rows' => $this->ringkasan()]),
            EmbeddedTable::make(),
        ]);
    }

    /**
     * Ringkasan per orang dari data yang sedang difilter (bulan & orang).
     *
     * @return array<int, array{nama: string, hadir: int, terlambat: int, lupa_pulang: int, total: string}>
     */
    public function ringkasan(): array
    {
        return $this->getFilteredTableQuery()
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows): array => [
                'nama' => $rows->first()->user->name,
                'hadir' => $rows->map(fn (StaffAttendance $a): string => $a->clock_in_at->toDateString())->unique()->count(),
                'terlambat' => $rows->where('is_late', true)->count(),
                'lupa_pulang' => $rows->filter(fn (StaffAttendance $a): bool => $a->isForgotClockOut())->count(),
                'total' => StaffAttendance::formatMinutes((int) $rows->sum(fn (StaffAttendance $a): int => $a->workedMinutes() ?? 0)),
            ])
            ->sortBy('nama')
            ->values()
            ->all();
    }
}
