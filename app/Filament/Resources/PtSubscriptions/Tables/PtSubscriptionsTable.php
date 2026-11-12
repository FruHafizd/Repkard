<?php

namespace App\Filament\Resources\PtSubscriptions\Tables;

use App\Enums\UserRole;
use App\Models\PtSubscription;
use App\Models\User;
use App\Services\PtService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use InvalidArgumentException;

class PtSubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('member.user.name')->label('Member'),
                TextColumn::make('member.member_number')->label('Nomor member')->placeholder('-')->visibleFrom('lg'),
                TextColumn::make('package.name')->label('Paket')->visibleFrom('md'),
                TextColumn::make('trainer.name')->label('Trainer')->visibleFrom('md'),
                TextColumn::make('sessions')->label('Sesi')
                    ->state(fn (PtSubscription $record): string => "{$record->sessions_remaining} / {$record->sessions_total}"),
                TextColumn::make('expires_on')->label('Berlaku sampai')->date('d M Y')->sortable(),
                TextColumn::make('status')->label('Status')
                    ->state(fn (PtSubscription $record): string => $record->statusLabel())
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Aktif' => 'success',
                        'Dibatalkan' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function gantiTrainerAction(): Action
    {
        return Action::make('gantiTrainer')
            ->label('Ganti trainer')
            ->color('warning')
            ->authorize('manage')
            ->schema([
                Select::make('trainer_id')
                    ->label('Trainer baru')
                    ->options(fn (): array => self::trainerOptions())
                    ->required(),
                Textarea::make('reason')->label('Alasan')->required()->maxLength(500),
            ])
            ->action(function (PtSubscription $record, array $data): void {
                self::jalankan(function () use ($record, $data): void {
                    app(PtService::class)->changeTrainer($record, User::findOrFail($data['trainer_id']), $data['reason']);

                    Notification::make()->success()->title('Trainer diganti')->send();
                });
            });
    }

    public static function batalkanAction(): Action
    {
        return Action::make('batalkan')
            ->label('Batalkan')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Paket PT dibatalkan. Uang tidak dikembalikan otomatis.')
            ->authorize('manage')
            ->schema([
                Textarea::make('reason')->label('Alasan pembatalan')->required()->maxLength(500),
            ])
            ->action(function (PtSubscription $record, array $data): void {
                self::jalankan(function () use ($record, $data): void {
                    app(PtService::class)->cancel($record, $data['reason']);

                    Notification::make()->success()->title('Paket PT dibatalkan')->send();
                });
            });
    }

    /** @return array<int, string> */
    public static function trainerOptions(): array
    {
        return User::query()
            ->where('is_active', true)
            ->role(UserRole::Trainer->value)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function jalankan(\Closure $callback): void
    {
        try {
            $callback();
        } catch (InvalidArgumentException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }
}
