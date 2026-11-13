<?php

namespace App\Filament\Resources\PtSubscriptions;

use App\Enums\MenuGroup;
use App\Filament\Resources\PtSubscriptions\Pages\ListPtSubscriptions;
use App\Filament\Resources\PtSubscriptions\Pages\ViewPtSubscription;
use App\Filament\Resources\PtSubscriptions\Schemas\PtSubscriptionInfolist;
use App\Filament\Resources\PtSubscriptions\Tables\PtSubscriptionsTable;
use App\Models\PtSubscription;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PtSubscriptionResource extends Resource
{
    protected static ?string $model = PtSubscription::class;

    protected static ?string $modelLabel = 'Klien PT';

    protected static ?string $pluralModelLabel = 'Klien PT';

    protected static ?string $navigationLabel = 'Klien PT';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = MenuGroup::PersonalTrainer;

    protected static ?int $navigationSort = 2;

    public static function infolist(Schema $schema): Schema
    {
        return PtSubscriptionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PtSubscriptionsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['member.user', 'package', 'trainer']);
        $user = auth()->user();

        // Tanpa izin "lihat semua", trainer hanya melihat kliennya sendiri.
        if (! $user?->can('ViewAll:PtSubscription')) {
            $query->where('trainer_id', $user?->getKey());
        }

        return $query;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPtSubscriptions::route('/'),
            'view' => ViewPtSubscription::route('/{record}'),
        ];
    }
}
