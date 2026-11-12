<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static ?string $title = 'Riwayat stok';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i'),
                TextColumn::make('type')->label('Jenis')->badge(),
                TextColumn::make('quantity')->label('Jumlah')
                    ->formatStateUsing(fn (int $state): string => ($state > 0 ? '+' : '').$state),
                TextColumn::make('note')->label('Catatan')->placeholder('-')->visibleFrom('md'),
                TextColumn::make('user.name')->label('Oleh')->visibleFrom('lg'),
            ]);
    }
}
