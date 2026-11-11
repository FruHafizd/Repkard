<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')->label('Nama'),
                TextEntry::make('email')->label('Email'),
                TextEntry::make('phone')->label('Nomor HP')->placeholder('-'),
                TextEntry::make('roles.name')->label('Role')->badge(),
                IconEntry::make('is_active')->label('Aktif')->boolean(),
            ]);
    }
}
