<?php

namespace App\Filament\Member\Resources\Announcements;

use App\Filament\Member\Resources\Announcements\Pages\ListAnnouncements;
use App\Models\Announcement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Daftar pengumuman untuk member (hanya baca). Hak akses panel dijaga middleware;
 * member tidak punya izin "ViewAny:Announcement", jadi daftar dibuka di sini.
 */
class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static ?string $modelLabel = 'Pengumuman';

    protected static ?string $pluralModelLabel = 'Pengumuman';

    protected static ?string $navigationLabel = 'Pengumuman';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Judul')
                    ->weight('bold')
                    ->wrap(),
                TextColumn::make('starts_at')
                    ->label('Tanggal')
                    ->date('d M Y'),
                TextColumn::make('body')
                    ->label('Isi')
                    ->html()
                    // Isi selalu di-escape; nl2br hanya mengubah baris baru jadi <br>.
                    ->formatStateUsing(fn (string $state): string => nl2br(e($state)))
                    ->wrap(),
            ])
            ->paginated([10]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->published();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnnouncements::route('/'),
        ];
    }
}
