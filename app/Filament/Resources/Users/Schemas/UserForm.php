<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('Nomor HP')
                    ->tel()
                    ->maxLength(30),
                Select::make('role')
                    ->label('Role')
                    ->options([
                        UserRole::Owner->value => UserRole::Owner->label(),
                        UserRole::Pegawai->value => UserRole::Pegawai->label(),
                    ])
                    ->required()
                    ->afterStateHydrated(fn (Select $component, $record) => $record
                        ? $component->state($record->roles->first()?->name)
                        : null),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true)
                    ->disabled(fn ($record): bool => $record?->is(auth()->user()) ?? false)
                    ->helperText(fn ($record): ?string => $record?->is(auth()->user())
                        ? 'Anda tidak dapat menonaktifkan akun sendiri.'
                        : null),
            ]);
    }
}
