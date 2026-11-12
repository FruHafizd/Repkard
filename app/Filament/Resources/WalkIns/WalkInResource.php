<?php

namespace App\Filament\Resources\WalkIns;

use App\Filament\Resources\WalkIns\Pages\ListWalkIns;
use App\Filament\Resources\WalkIns\Tables\WalkInsTable;
use App\Models\WalkIn;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WalkInResource extends Resource
{
    protected static ?string $model = WalkIn::class;

    protected static ?string $modelLabel = 'Pendatang';

    protected static ?string $pluralModelLabel = 'Pendatang';

    protected static ?string $navigationLabel = 'Pendatang';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    public static function table(Table $table): Table
    {
        return WalkInsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('recorder');
        $user = auth()->user();

        // Tanpa izin "lihat semua", hanya catatan sendiri.
        if (! $user?->can('ViewAll:WalkIn')) {
            $query->where('recorded_by', $user?->getKey());
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWalkIns::route('/'),
        ];
    }
}
