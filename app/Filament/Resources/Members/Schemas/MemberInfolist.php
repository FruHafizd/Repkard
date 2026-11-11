<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Models\Member;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MemberInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ImageEntry::make('photo_path')
                    ->label('Foto')
                    ->getStateUsing(fn (Member $record): ?string => $record->photoUrl())
                    ->imageHeight(320)
                    ->placeholder('Belum ada foto')
                    ->columnSpanFull(),
                TextEntry::make('member_number')->label('Nomor member')->placeholder('-'),
                TextEntry::make('status')->label('Status')->badge(),
                TextEntry::make('user.name')->label('Nama'),
                TextEntry::make('user.email')->label('Email'),
                TextEntry::make('phone')->label('Nomor HP'),
                TextEntry::make('gender')
                    ->label('Jenis kelamin')
                    ->formatStateUsing(fn (string $state): string => $state === 'L' ? 'Laki-laki' : 'Perempuan'),
                TextEntry::make('birth_date')->label('Tanggal lahir')->date('d M Y')->placeholder('-'),
                TextEntry::make('address')->label('Alamat')->placeholder('-')->columnSpanFull(),
                TextEntry::make('emergency_contact_name')->label('Nama kontak darurat')->placeholder('-'),
                TextEntry::make('emergency_contact_phone')->label('HP kontak darurat')->placeholder('-'),
                TextEntry::make('selectedPlan.name')->label('Paket pilihan')->placeholder('-'),
                TextEntry::make('created_at')->label('Didaftarkan')->dateTime('d M Y H:i'),
            ]);
    }
}
