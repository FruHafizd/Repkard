<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')->label('Nama produk'),
                TextEntry::make('sku')->label('SKU')->placeholder('-'),
                TextEntry::make('price')->label('Harga')->formatStateUsing(fn (int $state): string => PaymentResource::rupiah($state)),
                IconEntry::make('track_stock')->label('Hitung stok')->boolean(),
                TextEntry::make('stock')->label('Stok')->visible(fn ($record): bool => $record->track_stock),
                IconEntry::make('is_active')->label('Aktif')->boolean(),
            ]);
    }
}
