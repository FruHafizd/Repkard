<?php

namespace App\Filament\Resources\PtPackages\Schemas;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PtPackageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')->label('Nama'),
                TextEntry::make('description')->label('Deskripsi')->placeholder('-')->columnSpanFull(),
                TextEntry::make('sessions_count')->label('Jumlah sesi'),
                TextEntry::make('validity_days')
                    ->label('Masa berlaku')
                    ->formatStateUsing(fn (int $state): string => "{$state} hari"),
                TextEntry::make('price')
                    ->label('Harga')
                    ->formatStateUsing(fn (int $state): string => PaymentResource::rupiah($state)),
                TextEntry::make('sort_order')->label('Urutan tampil'),
                IconEntry::make('is_active')->label('Aktif')->boolean(),
            ]);
    }
}
