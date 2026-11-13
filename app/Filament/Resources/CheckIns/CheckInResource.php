<?php

namespace App\Filament\Resources\CheckIns;

use App\Enums\MenuGroup;
use App\Filament\Resources\CheckIns\Pages\ListCheckIns;
use App\Filament\Resources\CheckIns\Tables\CheckInsTable;
use App\Models\CheckIn;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CheckInResource extends Resource
{
    protected static ?string $model = CheckIn::class;

    protected static ?string $modelLabel = 'Check-in';

    protected static ?string $pluralModelLabel = 'Riwayat Check-in';

    protected static ?string $navigationLabel = 'Riwayat Check-in';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = MenuGroup::Member;

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return CheckInsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['member.user', 'scanner']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCheckIns::route('/'),
        ];
    }
}
