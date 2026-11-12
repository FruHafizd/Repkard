<?php

namespace App\Filament\Resources\Products\Tables;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Product;
use App\Services\SaleService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use InvalidArgumentException;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->weight('bold'),
                TextColumn::make('sku')->label('SKU')->searchable()->placeholder('-')->visibleFrom('lg'),
                TextColumn::make('price')->label('Harga')
                    ->formatStateUsing(fn (int $state): string => PaymentResource::rupiah($state))
                    ->sortable(),
                TextColumn::make('stock')->label('Stok')
                    ->badge()
                    ->state(fn (Product $record): string => $record->track_stock ? (string) $record->stock : 'Tanpa stok')
                    ->color(fn (Product $record): string => $record->isLowStock() ? 'warning' : 'gray'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::restockAction(),
                self::adjustAction(),
            ]);
    }

    public static function restockAction(): Action
    {
        return Action::make('tambahStok')
            ->label('Tambah stok')
            ->icon('heroicon-o-plus-circle')
            ->authorize('manageStock')
            ->visible(fn (Product $record): bool => $record->track_stock)
            ->schema([
                TextInput::make('quantity')->label('Jumlah masuk')->required()->numeric()->integer()->minValue(1),
                Textarea::make('note')->label('Catatan')->maxLength(255),
            ])
            ->action(function (Product $record, array $data): void {
                try {
                    app(SaleService::class)->restock($record, (int) $data['quantity'], $data['note'] ?? null, auth()->user());
                } catch (InvalidArgumentException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Stok ditambah')->send();
            });
    }

    public static function adjustAction(): Action
    {
        return Action::make('koreksiStok')
            ->label('Koreksi stok')
            ->color('gray')
            ->icon('heroicon-o-adjustments-horizontal')
            ->authorize('manageStock')
            ->visible(fn (Product $record): bool => $record->track_stock)
            ->fillForm(fn (Product $record): array => ['new_stock' => $record->stock])
            ->schema([
                TextInput::make('new_stock')->label('Stok sebenarnya')->required()->numeric()->integer()->minValue(0),
                Textarea::make('reason')->label('Alasan koreksi')->required()->maxLength(255),
            ])
            ->action(function (Product $record, array $data): void {
                try {
                    app(SaleService::class)->adjust($record, (int) $data['new_stock'], $data['reason'], auth()->user());
                } catch (InvalidArgumentException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Stok dikoreksi')->send();
            });
    }
}
