<?php

namespace App\Filament\Resources\Sales\Tables;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Sale;
use App\Services\SaleService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use InvalidArgumentException;

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sold_at', 'desc')
            ->columns([
                TextColumn::make('number')->label('No. transaksi')->searchable()->weight('bold'),
                TextColumn::make('sold_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('total')->label('Total')
                    ->formatStateUsing(fn (int $state): string => PaymentResource::rupiah($state))
                    ->sortable(),
                TextColumn::make('method')->label('Metode')->badge()->visibleFrom('md'),
                TextColumn::make('recorder.name')->label('Kasir')->visibleFrom('lg'),
                TextColumn::make('status')->label('Status')
                    ->state(fn (Sale $record): string => $record->isCancelled() ? 'Dibatalkan' : 'Lunas')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Dibatalkan' ? 'danger' : 'success'),
            ])
            ->recordActions([
                ViewAction::make(),
                self::cancelAction(),
            ]);
    }

    public static function cancelAction(): Action
    {
        return Action::make('batalkan')
            ->label('Batalkan')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Stok barang akan dikembalikan.')
            ->authorize('cancel')
            ->schema([
                Textarea::make('reason')->label('Alasan pembatalan')->required()->maxLength(500),
            ])
            ->action(function (Sale $record, array $data): void {
                try {
                    app(SaleService::class)->void($record, auth()->user(), $data['reason']);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Transaksi dibatalkan')->send();
            });
    }
}
