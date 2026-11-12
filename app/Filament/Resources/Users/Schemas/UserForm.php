<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
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
                        UserRole::Trainer->value => UserRole::Trainer->label(),
                    ])
                    ->required()
                    ->live()
                    ->afterStateHydrated(fn (Select $component, $record) => $record
                        ? $component->state($record->roles->first()?->name)
                        : null),
                Group::make([
                    TextInput::make('specialties')
                        ->label('Spesialisasi')
                        ->placeholder('Fat loss, strength')
                        ->maxLength(255),
                    Textarea::make('bio')
                        ->label('Bio')
                        ->columnSpanFull(),
                    TextInput::make('commission_per_session')
                        ->label('Komisi per sesi (Rp)')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->default(0)
                        ->required()
                        ->disabled(fn (): bool => ! auth()->user()?->hasRole(UserRole::Owner->value))
                        ->dehydrated(fn (): bool => (bool) auth()->user()?->hasRole(UserRole::Owner->value))
                        ->helperText('Hanya Owner yang dapat mengubah komisi.'),
                ])
                    ->relationship('trainerProfile')
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn ($get): bool => $get('role') === UserRole::Trainer->value),
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
