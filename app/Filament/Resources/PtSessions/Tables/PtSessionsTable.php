<?php

namespace App\Filament\Resources\PtSessions\Tables;

use App\Enums\PtSessionStatus;
use App\Enums\UserRole;
use App\Models\PtSession;
use App\Models\User;
use App\Services\PtService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class PtSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('scheduled_at')
            ->defaultGroup(
                Group::make('scheduled_at')->label('Tanggal')->date()->titlePrefixedWithLabel(false)
                    ->orderQueryUsing(fn (Builder $query, string $direction) => $query->orderBy('scheduled_at', $direction)),
            )
            ->columns([
                TextColumn::make('scheduled_at')->label('Jam')->dateTime('H:i')->sortable(),
                TextColumn::make('duration_minutes')->label('Durasi')->suffix(' menit')->visibleFrom('md'),
                TextColumn::make('subscription.member.user.name')->label('Member'),
                TextColumn::make('trainer.name')->label('Trainer')->visibleFrom('md'),
                TextColumn::make('subscription.sessions_remaining')->label('Sisa sesi')->visibleFrom('md'),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->filters([
                Filter::make('mendatang')
                    ->label('Hari ini & akan datang')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->where('scheduled_at', '>=', now()->startOfDay())),
                SelectFilter::make('trainer_id')
                    ->label('Trainer')
                    ->options(fn (): array => User::query()->role(UserRole::Trainer->value)->orderBy('name')->pluck('name', 'id')->all())
                    ->visible(fn (): bool => (bool) auth()->user()?->can('ViewAll:PtSession')),
                SelectFilter::make('status')->label('Status')->options(PtSessionStatus::class),
                Filter::make('rentang')
                    ->label('Rentang tanggal')
                    ->schema([
                        DatePicker::make('dari')->label('Dari'),
                        DatePicker::make('sampai')->label('Sampai'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, string $d) => $q->where('scheduled_at', '>=', $d.' 00:00:00'))
                        ->when($data['sampai'] ?? null, fn (Builder $q, string $d) => $q->where('scheduled_at', '<=', $d.' 23:59:59'))),
            ])
            ->recordActions([
                self::selesaiAction(PtSessionStatus::Completed, 'success'),
                self::selesaiAction(PtSessionStatus::NoShow, 'warning'),
                self::batalkanAction(),
            ]);
    }

    private static function selesaiAction(PtSessionStatus $status, string $color): Action
    {
        return Action::make($status->value)
            ->label($status->label())
            ->color($color)
            ->requiresConfirmation()
            ->modalDescription('Sesi paket PT berkurang 1 dan komisi trainer tercatat.')
            ->authorize('update')
            ->visible(fn (PtSession $record): bool => $record->status === PtSessionStatus::Scheduled)
            ->action(function (PtSession $record) use ($status): void {
                self::jalankan(function () use ($record, $status): void {
                    app(PtService::class)->finish($record, $status, auth()->user());

                    Notification::make()->success()->title("Sesi ditandai {$status->label()}")->send();
                });
            });
    }

    private static function batalkanAction(): Action
    {
        return Action::make('batalkan')
            ->label('Batalkan')
            ->color('danger')
            ->authorize('update')
            ->visible(fn (PtSession $record): bool => $record->status === PtSessionStatus::Scheduled)
            ->schema([
                Textarea::make('reason')->label('Alasan pembatalan')->required()->maxLength(255),
            ])
            ->action(function (PtSession $record, array $data): void {
                self::jalankan(function () use ($record, $data): void {
                    app(PtService::class)->cancelSession($record, auth()->user(), $data['reason']);

                    Notification::make()->success()->title('Sesi dibatalkan')->send();
                });
            });
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
