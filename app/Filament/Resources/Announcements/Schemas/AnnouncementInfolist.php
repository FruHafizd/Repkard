<?php

namespace App\Filament\Resources\Announcements\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AnnouncementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('title')->label('Judul'),
                TextEntry::make('body')->label('Isi')->html()
                    ->formatStateUsing(fn (string $state): string => nl2br(e($state))),
                TextEntry::make('starts_at')->label('Mulai tayang')->dateTime('d M Y H:i'),
                TextEntry::make('ends_at')->label('Berakhir')->dateTime('d M Y H:i')->placeholder('Tidak berakhir'),
                TextEntry::make('status')
                    ->label('Status')
                    ->state(fn ($record): string => $record->statusLabel())
                    ->badge(),
                IconEntry::make('is_pinned')->label('Disematkan')->boolean(),
                IconEntry::make('show_on_landing')->label('Di landing page')->boolean(),
                TextEntry::make('creator.name')->label('Dibuat oleh')->placeholder('-'),
            ]);
    }
}
