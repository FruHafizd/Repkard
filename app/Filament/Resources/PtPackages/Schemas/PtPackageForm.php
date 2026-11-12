<?php

namespace App\Filament\Resources\PtPackages\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PtPackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Deskripsi')
                    ->columnSpanFull(),
                TextInput::make('sessions_count')
                    ->label('Jumlah sesi')
                    ->required()
                    ->integer()
                    ->minValue(1),
                TextInput::make('validity_days')
                    ->label('Masa berlaku (hari)')
                    ->required()
                    ->integer()
                    ->minValue(1),
                TextInput::make('price')
                    ->label('Harga')
                    ->required()
                    ->integer()
                    ->minValue(0)
                    ->prefix('Rp'),
                TextInput::make('sort_order')
                    ->label('Urutan tampil')
                    ->required()
                    ->integer()
                    ->minValue(0)
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true)
                    ->helperText('Paket nonaktif tidak bisa dijual lagi'),
            ]);
    }
}
