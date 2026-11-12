<?php

namespace App\Filament\Widgets;

use App\Models\CheckIn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class CheckInHariIniTable extends TableWidget
{
    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Member') ?? false;
    }

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Check-in hari ini';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => CheckIn::query()
                ->with(['member.user'])
                ->where('result', CheckIn::RESULT_ACCEPTED)
                ->whereBetween('checked_in_at', [now()->startOfDay(), now()->endOfDay()])
                ->latest('checked_in_at')
                ->limit(20))
            ->columns([
                TextColumn::make('checked_in_at')->label('Jam')->dateTime('H:i'),
                TextColumn::make('member.user.name')->label('Nama')->placeholder('-'),
                TextColumn::make('member.member_number')->label('Nomor member')->placeholder('-')->visibleFrom('md'),
                TextColumn::make('method')->label('Metode')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'manual' ? 'Manual' : 'Kartu')
                    ->color(fn (string $state): string => $state === 'manual' ? 'warning' : 'gray'),
            ])
            ->paginated(false)
            ->emptyStateHeading('Belum ada check-in hari ini');
    }
}
