<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Nama produk')->required()->maxLength(255),
                TextInput::make('sku')->label('SKU')->maxLength(100)->unique(ignoreRecord: true),
                TextInput::make('price')->label('Harga (Rp)')->required()->numeric()->integer()->minValue(0),
                TextInput::make('sort_order')->label('Urutan')->numeric()->integer()->minValue(0)->default(0),
                Toggle::make('track_stock')
                    ->label('Hitung stok')
                    ->helperText('Matikan untuk jasa, mis. sewa handuk. Stok diubah lewat aksi "Tambah stok" / "Koreksi stok".')
                    ->default(true),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ]);
    }
}
