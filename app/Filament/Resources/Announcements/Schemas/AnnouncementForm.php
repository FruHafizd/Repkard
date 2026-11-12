<?php

namespace App\Filament\Resources\Announcements\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AnnouncementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Judul')
                    ->required()
                    ->maxLength(150),
                Textarea::make('body')
                    ->label('Isi')
                    ->helperText('Teks biasa. Baris baru akan tetap tampil.')
                    ->required()
                    ->rows(6)
                    ->maxLength(5000),
                DateTimePicker::make('starts_at')
                    ->label('Mulai tayang')
                    ->default(now())
                    ->required(),
                DateTimePicker::make('ends_at')
                    ->label('Berakhir (opsional)')
                    ->helperText('Kosongkan jika tidak berakhir.')
                    ->after('starts_at'),
                Toggle::make('is_pinned')
                    ->label('Sematkan di atas'),
                Toggle::make('show_on_landing')
                    ->label('Tampilkan di landing page'),
            ]);
    }
}
