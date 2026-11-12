<?php

namespace App\Filament\Resources\CheckIns\Tables;

use App\Models\CheckIn;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CheckInsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('checked_in_at', 'desc')
            ->columns([
                TextColumn::make('checked_in_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('member.user.name')->label('Member')->placeholder('-'),
                TextColumn::make('member.member_number')->label('Nomor')->placeholder('-')->visibleFrom('md'),
                TextColumn::make('method')->label('Metode')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'manual' ? 'Manual' : 'Kartu')
                    ->color(fn (string $state): string => $state === 'manual' ? 'warning' : 'gray')
                    ->visibleFrom('md'),
                TextColumn::make('result')->label('Hasil')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === CheckIn::RESULT_ACCEPTED ? 'Diterima' : 'Ditolak')
                    ->color(fn (string $state): string => $state === CheckIn::RESULT_ACCEPTED ? 'success' : 'danger')
                    ->icon(fn (string $state): Heroicon => $state === CheckIn::RESULT_ACCEPTED ? Heroicon::CheckCircle : Heroicon::XCircle),
                TextColumn::make('reject_reason')->label('Alasan')
                    ->state(fn (CheckIn $record): ?string => $record->reject_reason ?? $record->manual_reason)
                    ->placeholder('-')
                    ->wrap()
                    ->visibleFrom('md'),
                TextColumn::make('scanner.name')->label('Dicatat oleh')->visibleFrom('lg'),
            ])
            ->filters([
                Filter::make('tanggal')
                    ->label('Tanggal')
                    ->schema([
                        DatePicker::make('dari')->label('Dari')->default(now()->toDateString()),
                        DatePicker::make('sampai')->label('Sampai')->default(now()->toDateString()),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, string $d): Builder => $q->whereDate('checked_in_at', '>=', $d))
                        ->when($data['sampai'] ?? null, fn (Builder $q, string $d): Builder => $q->whereDate('checked_in_at', '<=', $d)))
                    ->default(),
                SelectFilter::make('result')->label('Hasil')->options([
                    CheckIn::RESULT_ACCEPTED => 'Diterima',
                    CheckIn::RESULT_REJECTED => 'Ditolak',
                ]),
                SelectFilter::make('method')->label('Metode')->options([
                    'card' => 'Kartu',
                    'manual' => 'Manual',
                ]),
            ]);
    }
}
