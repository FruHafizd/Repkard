<?php

namespace App\Filament\Member\Resources\CheckIns;

use App\Filament\Member\Resources\CheckIns\Pages\ListCheckIns;
use App\Models\CheckIn;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CheckInResource extends Resource
{
    protected static ?string $model = CheckIn::class;

    protected static ?string $modelLabel = 'Kehadiran';

    protected static ?string $pluralModelLabel = 'Riwayat Kehadiran';

    protected static ?string $navigationLabel = 'Riwayat Kehadiran';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('checked_in_at', 'desc')
            ->columns([
                TextColumn::make('checked_in_at')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('jam')->label('Jam')->state(fn (CheckIn $record): string => $record->checked_in_at->format('H:i')),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('member_id', Filament::auth()->user()?->member?->id ?? 0)
            ->where('result', CheckIn::RESULT_ACCEPTED);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCheckIns::route('/'),
        ];
    }
}
