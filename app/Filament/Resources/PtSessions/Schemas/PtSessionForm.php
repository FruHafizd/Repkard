<?php

namespace App\Filament\Resources\PtSessions\Schemas;

use App\Models\PtSubscription;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PtSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('pt_subscription_id')
                ->label('Klien PT')
                ->options(fn (): array => self::subscriptionOptions())
                ->searchable()
                ->required(),
            DateTimePicker::make('scheduled_at')
                ->label('Tanggal & jam')
                ->seconds(false)
                ->minDate(now()->startOfDay())
                ->required(),
            TextInput::make('duration_minutes')
                ->label('Durasi (menit)')
                ->numeric()
                ->integer()
                ->minValue(15)
                ->maxValue(480)
                ->default(60)
                ->required(),
        ]);
    }

    /** Subscription yang masih bisa dipakai; trainer hanya melihat kliennya sendiri. @return array<int, string> */
    public static function subscriptionOptions(): array
    {
        $user = auth()->user();

        return self::subscriptionQuery()
            ->get()
            ->filter(fn (PtSubscription $s): bool => $s->isUsable())
            ->mapWithKeys(fn (PtSubscription $s): array => [
                $s->getKey() => "{$s->member->user->name} — sisa {$s->sessions_remaining} sesi"
                    .($user?->can('ViewAll:PtSession') ? " ({$s->trainer->name})" : ''),
            ])
            ->all();
    }

    /** @return Builder<PtSubscription> */
    public static function subscriptionQuery(): Builder
    {
        $user = auth()->user();

        return PtSubscription::query()
            ->with(['member.user', 'trainer'])
            ->when(! $user?->can('ViewAll:PtSession'), fn (Builder $q) => $q->where('trainer_id', $user?->getKey()))
            ->whereNull('cancelled_at')
            ->where('sessions_remaining', '>', 0);
    }
}
