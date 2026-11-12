<?php

namespace App\Filament\Widgets;

use App\Enums\MemberStatus;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Membership;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class AkanHabisTable extends TableWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Member') ?? false;
    }

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Akan habis 7 hari lagi';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Membership::query()
                ->with(['member.user', 'plan'])
                ->whereNull('cancelled_at')
                ->whereBetween('ends_on', [now()->toDateString(), now()->addDays(7)->toDateString()])
                ->whereHas('member', fn (Builder $q) => $q->where('status', MemberStatus::Active))
                ->orderBy('ends_on'))
            ->columns([
                TextColumn::make('member.user.name')->label('Nama'),
                TextColumn::make('member.phone')->label('Nomor HP')->visibleFrom('md'),
                TextColumn::make('member.member_number')->label('Nomor member')->placeholder('-')->visibleFrom('md'),
                TextColumn::make('plan.name')->label('Paket')->placeholder('-')->visibleFrom('lg'),
                TextColumn::make('ends_on')->label('Berlaku sampai')->date('d M Y'),
            ])
            ->recordUrl(fn (Membership $record): string => MemberResource::getUrl('view', ['record' => $record->member_id]))
            ->paginated(false)
            ->emptyStateHeading('Tidak ada member yang akan habis');
    }
}
