<?php

namespace App\Filament\Resources\StaffAttendances;

use App\Filament\Resources\StaffAttendances\Pages\ListStaffAttendances;
use App\Filament\Resources\StaffAttendances\Tables\StaffAttendancesTable;
use App\Models\StaffAttendance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StaffAttendanceResource extends Resource
{
    protected static ?string $model = StaffAttendance::class;

    protected static ?string $modelLabel = 'Absensi';

    protected static ?string $pluralModelLabel = 'Rekap Absensi';

    protected static ?string $navigationLabel = 'Rekap Absensi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function table(Table $table): Table
    {
        return StaffAttendancesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('user');
        $user = auth()->user();

        // Tanpa izin "lihat semua", hanya absensi sendiri.
        if (! $user?->can('ViewAll:StaffAttendance')) {
            $query->where('user_id', $user?->getKey());
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaffAttendances::route('/'),
        ];
    }
}
