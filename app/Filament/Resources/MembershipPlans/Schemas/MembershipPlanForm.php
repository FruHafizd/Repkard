<?php

namespace App\Filament\Resources\MembershipPlans\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MembershipPlanForm
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
                TextInput::make('duration_days')
                    ->label('Masa berlaku (hari)')
                    ->required()
                    ->integer()
                    ->minValue(1),
                TextInput::make('visit_quota')
                    ->label('Kuota kunjungan')
                    ->integer()
                    ->minValue(1)
                    ->helperText('Kosongkan untuk tanpa batas kunjungan'),
                TextInput::make('price')
                    ->label('Harga')
                    ->required()
                    ->numeric()
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
                    ->helperText('Paket nonaktif tidak tampil untuk dipilih'),
            ]);
    }
}
