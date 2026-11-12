<?php

namespace App\Filament\Resources\PtPackages\Tables;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PtPackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('sessions_count')->label('Sesi')->visibleFrom('md'),
                TextColumn::make('validity_days')
                    ->label('Berlaku')
                    ->formatStateUsing(fn (int $state): string => "{$state} hari")
                    ->visibleFrom('md'),
                TextColumn::make('price')
                    ->label('Harga')
                    ->formatStateUsing(fn (int $state): string => PaymentResource::rupiah($state)),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
