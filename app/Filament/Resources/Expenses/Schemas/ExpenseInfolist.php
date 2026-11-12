<?php

namespace App\Filament\Resources\Expenses\Schemas;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Expense;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ExpenseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('spent_on')->label('Tanggal')->date('d M Y'),
                TextEntry::make('category.name')->label('Kategori'),
                TextEntry::make('description')->label('Keterangan'),
                TextEntry::make('amount')->label('Jumlah')->formatStateUsing(fn (int $state): string => PaymentResource::rupiah($state)),
                TextEntry::make('recorder.name')->label('Dicatat oleh'),
                TextEntry::make('proof')->label('Bukti')
                    ->state(fn (Expense $record): string => $record->proof_path ? 'Buka bukti' : '-')
                    ->url(fn (Expense $record): ?string => $record->proof_path ? route('expense.proof', $record) : null, shouldOpenInNewTab: true),
                TextEntry::make('cancelled_at')->label('Dibatalkan pada')->dateTime('d M Y H:i')->visible(fn (Expense $record): bool => $record->isCancelled()),
                TextEntry::make('cancel_reason')->label('Alasan pembatalan')->visible(fn (Expense $record): bool => $record->isCancelled()),
            ]);
    }
}
