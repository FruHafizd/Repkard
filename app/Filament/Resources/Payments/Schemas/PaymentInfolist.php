<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('paid_at')->label('Tanggal')->dateTime('d M Y H:i'),
                TextEntry::make('member.member_number')->label('Nomor member')->placeholder('-'),
                TextEntry::make('member.user.name')->label('Nama'),
                TextEntry::make('membership.plan.name')->label('Paket')->placeholder('-'),
                TextEntry::make('original_amount')->label('Harga asli')->placeholder('-')
                    ->formatStateUsing(fn (int $state): string => PaymentResource::rupiah($state)),
                TextEntry::make('discount_amount')->label('Diskon')
                    ->formatStateUsing(fn (int $state): string => PaymentResource::rupiah($state)),
                TextEntry::make('promoCode.code')->label('Kode promo')->placeholder('-'),
                TextEntry::make('amount')->label('Jumlah dibayar')->formatStateUsing(fn (int $state): string => PaymentResource::rupiah($state)),
                TextEntry::make('method')->label('Metode')->badge(),
                TextEntry::make('recorder.name')->label('Dicatat oleh'),
                TextEntry::make('note')->label('Catatan')->placeholder('-'),
                TextEntry::make('cancelled_at')->label('Dibatalkan pada')->dateTime('d M Y H:i')->visible(fn ($record): bool => $record->isCancelled()),
                TextEntry::make('canceller.name')->label('Dibatalkan oleh')->visible(fn ($record): bool => $record->isCancelled()),
                TextEntry::make('cancel_reason')->label('Alasan pembatalan')->visible(fn ($record): bool => $record->isCancelled()),
            ]);
    }
}
