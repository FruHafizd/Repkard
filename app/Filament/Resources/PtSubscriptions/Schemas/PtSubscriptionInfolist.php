<?php

namespace App\Filament\Resources\PtSubscriptions\Schemas;

use App\Models\PtSubscription;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PtSubscriptionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('member.user.name')->label('Member'),
                TextEntry::make('member.member_number')->label('Nomor member')->placeholder('-'),
                TextEntry::make('package.name')->label('Paket'),
                TextEntry::make('trainer.name')->label('Trainer'),
                TextEntry::make('sessions')->label('Sisa / total sesi')
                    ->state(fn (PtSubscription $record): string => "{$record->sessions_remaining} / {$record->sessions_total}"),
                TextEntry::make('starts_on')->label('Mulai')->date('d M Y'),
                TextEntry::make('expires_on')->label('Berlaku sampai')->date('d M Y'),
                TextEntry::make('status')->label('Status')
                    ->state(fn (PtSubscription $record): string => $record->statusLabel())
                    ->badge(),
                TextEntry::make('creator.name')->label('Dijual oleh'),
                TextEntry::make('cancelled_at')->label('Dibatalkan pada')->dateTime('d M Y H:i')
                    ->visible(fn (PtSubscription $record): bool => $record->isCancelled()),
                TextEntry::make('cancel_reason')->label('Alasan pembatalan')
                    ->visible(fn (PtSubscription $record): bool => $record->isCancelled()),
            ]);
    }
}
