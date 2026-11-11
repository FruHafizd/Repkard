<?php

namespace App\Filament\Resources\MembershipPlans\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MembershipPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('duration_days')
                    ->label('Durasi')
                    ->formatStateUsing(fn (int $state): string => "{$state} hari"),
                TextColumn::make('visit_quota')
                    ->label('Kuota')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? 'Tanpa batas' : (string) $state),
                TextColumn::make('price')
                    ->label('Harga')
                    ->formatStateUsing(fn (int $state): string => 'Rp '.number_format($state, 0, ',', '.')),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
