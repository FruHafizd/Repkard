<?php

namespace App\Filament\Member\Resources\Payments;

use App\Filament\Member\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\PaymentResource as AdminPaymentResource;
use App\Models\Payment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $modelLabel = 'Pembayaran';

    protected static ?string $pluralModelLabel = 'Riwayat Pembayaran';

    protected static ?string $navigationLabel = 'Riwayat Pembayaran';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('paid_at', 'desc')
            ->columns([
                TextColumn::make('paid_at')->label('Tanggal')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('membership.plan.name')->label('Paket')->placeholder('-'),
                TextColumn::make('amount')->label('Jumlah')
                    ->formatStateUsing(fn (int $state): string => AdminPaymentResource::rupiah($state)),
                TextColumn::make('method')->label('Metode')->badge(),
                TextColumn::make('status')->label('Status')
                    ->state(fn (Payment $record): string => $record->isCancelled() ? 'Dibatalkan' : 'Lunas')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Dibatalkan' ? 'danger' : 'success'),
            ])
            ->recordActions([
                Action::make('kuitansi')
                    ->label('Kuitansi')
                    ->url(fn (Payment $record): string => route('payment.receipt', $record), shouldOpenInNewTab: true)
                    ->authorize('view'),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('membership.plan')
            ->where('member_id', Filament::auth()->user()?->member?->id ?? 0);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
        ];
    }
}
