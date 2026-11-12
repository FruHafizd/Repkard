<?php

namespace App\Filament\Resources\PromoCodes\Schemas;

use App\Models\PromoCode;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PromoCodeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code')->label('Kode'),
                TextEntry::make('name')->label('Nama promo'),
                TextEntry::make('discount')->label('Diskon')->state(fn (PromoCode $record): string => $record->discountLabel()),
                TextEntry::make('starts_on')->label('Mulai')->date('d M Y')->placeholder('Tanpa batas'),
                TextEntry::make('ends_on')->label('Berakhir')->date('d M Y')->placeholder('Tanpa batas'),
                TextEntry::make('uses')->label('Terpakai')
                    ->state(fn (PromoCode $record): string => $record->usesCount().' / '.($record->max_uses ?? 'tanpa batas')),
                TextEntry::make('max_uses_per_member')->label('Maks. per member'),
                TextEntry::make('plans.name')->label('Paket')->badge()->placeholder('Semua paket'),
                IconEntry::make('new_members_only')->label('Khusus member baru')->boolean(),
                IconEntry::make('is_active')->label('Aktif')->boolean(),
                TextEntry::make('creator.name')->label('Dibuat oleh')->placeholder('-'),
            ]);
    }
}
