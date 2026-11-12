<?php

namespace App\Filament\Resources\PromoCodes\Tables;

use App\Models\PromoCode;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PromoCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount([
                'payments as uses_count' => fn ($q) => $q->whereNull('cancelled_at'),
            ]))
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->weight('bold'),
                TextColumn::make('name')->label('Nama')->searchable()->visibleFrom('md'),
                TextColumn::make('discount')->label('Diskon')->state(fn (PromoCode $record): string => $record->discountLabel()),
                TextColumn::make('period')->label('Periode')
                    ->state(fn (PromoCode $record): string => ($record->starts_on?->format('d M Y') ?? 'sejak awal').' - '.($record->ends_on?->format('d M Y') ?? 'tanpa batas'))
                    ->visibleFrom('lg'),
                TextColumn::make('uses_count')->label('Terpakai')
                    ->formatStateUsing(fn (PromoCode $record): string => $record->uses_count.' / '.($record->max_uses ?? 'tanpa batas')),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
