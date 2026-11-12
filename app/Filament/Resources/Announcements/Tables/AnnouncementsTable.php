<?php

namespace App\Filament\Resources\Announcements\Tables;

use App\Models\Announcement;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AnnouncementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Judul')->searchable()->wrap(),
                TextColumn::make('status')->label('Status')
                    ->state(fn (Announcement $record): string => $record->statusLabel())
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Tayang' => 'success',
                        'Terjadwal' => 'info',
                        default => 'gray',
                    }),
                IconColumn::make('is_pinned')->label('Disematkan')->boolean()->visibleFrom('md'),
                IconColumn::make('show_on_landing')->label('Di landing')->boolean()->visibleFrom('md'),
                TextColumn::make('creator.name')->label('Dibuat oleh')->placeholder('-')->visibleFrom('lg'),
                TextColumn::make('starts_at')->label('Mulai')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'tayang' => 'Tayang',
                        'terjadwal' => 'Terjadwal',
                        'berakhir' => 'Berakhir',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'tayang' => $query
                            ->where('starts_at', '<=', now())
                            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now())),
                        'terjadwal' => $query->where('starts_at', '>', now()),
                        'berakhir' => $query->whereNotNull('ends_at')->where('ends_at', '<=', now()),
                        default => $query,
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
