<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Sale;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SaleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('number')->label('No. transaksi'),
                TextEntry::make('sold_at')->label('Waktu')->dateTime('d M Y H:i'),
                TextEntry::make('recorder.name')->label('Kasir'),
                TextEntry::make('buyer')->label('Pembeli')
                    ->state(fn (Sale $record): ?string => $record->member?->user?->name ?? $record->customer_name)
                    ->placeholder('-'),
                TextEntry::make('method')->label('Metode')->badge(),
                TextEntry::make('total')->label('Total')->formatStateUsing(fn (int $state): string => PaymentResource::rupiah($state)),
                TextEntry::make('cash_received')->label('Uang diterima')
                    ->formatStateUsing(fn (int $state): string => PaymentResource::rupiah($state))
                    ->placeholder('-'),
                TextEntry::make('change')->label('Kembalian')
                    ->state(fn (Sale $record): ?string => $record->change() === null ? null : PaymentResource::rupiah($record->change()))
                    ->placeholder('-'),
                RepeatableEntry::make('items')->label('Barang')->columnSpanFull()->schema([
                    TextEntry::make('product_name')->label('Produk'),
                    TextEntry::make('quantity')->label('Jumlah'),
                    TextEntry::make('unit_price')->label('Harga')->formatStateUsing(fn (int $state): string => PaymentResource::rupiah($state)),
                    TextEntry::make('subtotal')->label('Subtotal')->formatStateUsing(fn (int $state): string => PaymentResource::rupiah($state)),
                ])->columns(4),
                TextEntry::make('cancelled_at')->label('Dibatalkan pada')->dateTime('d M Y H:i')->visible(fn (Sale $record): bool => $record->isCancelled()),
                TextEntry::make('cancel_reason')->label('Alasan pembatalan')->visible(fn (Sale $record): bool => $record->isCancelled()),
            ]);
    }
}
