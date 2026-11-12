<?php

namespace App\Filament\Resources\WalkIns\Tables;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\WalkIn;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WalkInsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('visited_at', 'desc')
            ->columns([
                TextColumn::make('visited_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('name')->label('Nama')->placeholder('Tanpa nama'),
                TextColumn::make('amount')->label('Jumlah')
                    ->formatStateUsing(fn (int $state): string => PaymentResource::rupiah($state))
                    ->sortable(),
                TextColumn::make('method')->label('Metode')->badge()->visibleFrom('md'),
                TextColumn::make('recorder.name')->label('Dicatat oleh')->visibleFrom('lg'),
                TextColumn::make('status')->label('Status')
                    ->state(fn (WalkIn $record): string => $record->isCancelled() ? 'Dibatalkan' : 'Lunas')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Dibatalkan' ? 'danger' : 'success'),
            ])
            ->recordActions([
                Action::make('batalkan')
                    ->label('Batalkan')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize('cancel')
                    ->schema([
                        Textarea::make('reason')->label('Alasan pembatalan')->required()->maxLength(500),
                    ])
                    ->action(function (WalkIn $record, array $data): void {
                        $record->update([
                            'cancelled_at' => now(),
                            'cancelled_by' => auth()->id(),
                            'cancel_reason' => $data['reason'],
                        ]);

                        Notification::make()->success()->title('Catatan dibatalkan')->send();
                    }),
            ]);
    }
}
