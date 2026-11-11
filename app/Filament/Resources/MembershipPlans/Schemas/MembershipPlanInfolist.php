<?php

namespace App\Filament\Resources\MembershipPlans\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MembershipPlanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')->label('Nama'),
                TextEntry::make('description')->label('Deskripsi')->placeholder('-')->columnSpanFull(),
                TextEntry::make('duration_days')
                    ->label('Durasi')
                    ->formatStateUsing(fn (int $state): string => "{$state} hari"),
                TextEntry::make('visit_quota')
                    ->label('Kuota')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? 'Tanpa batas' : (string) $state),
                TextEntry::make('price')
                    ->label('Harga')
                    ->formatStateUsing(fn (int $state): string => 'Rp '.number_format($state, 0, ',', '.')),
                TextEntry::make('sort_order')->label('Urutan tampil'),
                IconEntry::make('is_active')->label('Aktif')->boolean(),
            ]);
    }
}
