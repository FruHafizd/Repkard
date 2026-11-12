<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\PaymentType;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('paid_at', 'desc')
            ->columns([
                TextColumn::make('paid_at')->label('Tanggal')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('member.member_number')->label('Nomor member')->placeholder('-')->visibleFrom('lg'),
                TextColumn::make('member.user.name')->label('Nama'),
                TextColumn::make('amount')->label('Jumlah')
                    ->formatStateUsing(fn (int $state): string => PaymentResource::rupiah($state))
                    ->sortable(),
                TextColumn::make('type')->label('Jenis')->badge()->visibleFrom('md'),
                TextColumn::make('discount_amount')->label('Diskon')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? PaymentResource::rupiah($state) : '-')
                    ->visibleFrom('md'),
                TextColumn::make('promoCode.code')->label('Kode promo')->placeholder('-')->visibleFrom('lg'),
                TextColumn::make('method')->label('Metode')->badge()->visibleFrom('md'),
                TextColumn::make('recorder.name')->label('Dicatat oleh')->visibleFrom('lg'),
                TextColumn::make('status')->label('Status')
                    ->state(fn (Payment $record): string => $record->isCancelled() ? 'Dibatalkan' : 'Lunas')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Dibatalkan' ? 'danger' : 'success'),
            ])
            ->filters([
                SelectFilter::make('type')->label('Jenis')->options(PaymentType::class),
            ])
            ->recordActions([
                ViewAction::make(),
                self::kuitansiAction(),
                self::batalkanAction(),
            ]);
    }

    public static function kuitansiAction(): Action
    {
        return Action::make('kuitansi')
            ->label('Kuitansi')
            ->url(fn (Payment $record): string => route('payment.receipt', $record), shouldOpenInNewTab: true)
            ->authorize('view');
    }

    public static function batalkanAction(): Action
    {
        return Action::make('batalkan')
            ->label('Batalkan')
            ->color('danger')
            ->requiresConfirmation()
            ->authorize('cancel')
            ->schema([
                Textarea::make('reason')->label('Alasan pembatalan')->required()->maxLength(500),
            ])
            ->action(function (Payment $record, array $data): void {
                $record->update([
                    'cancelled_at' => now(),
                    'cancelled_by' => auth()->id(),
                    'cancel_reason' => $data['reason'],
                ]);

                Notification::make()->success()->title('Pembayaran dibatalkan')->send();
            });
    }
}
