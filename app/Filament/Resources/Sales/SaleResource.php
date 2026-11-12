<?php

namespace App\Filament\Resources\Sales;

use App\Filament\Resources\Sales\Pages\ListSales;
use App\Filament\Resources\Sales\Pages\ViewSale;
use App\Filament\Resources\Sales\Schemas\SaleInfolist;
use App\Filament\Resources\Sales\Tables\SalesTable;
use App\Models\Sale;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static ?string $modelLabel = 'Penjualan';

    protected static ?string $pluralModelLabel = 'Penjualan';

    protected static ?string $navigationLabel = 'Penjualan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    public static function infolist(Schema $schema): Schema
    {
        return SaleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['recorder', 'items']);
        $user = auth()->user();

        // Tanpa izin "lihat semua", hanya penjualan sendiri.
        if (! $user?->can('ViewAll:Sale')) {
            $query->where('recorded_by', $user?->getKey());
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSales::route('/'),
            'view' => ViewSale::route('/{record}'),
        ];
    }
}
