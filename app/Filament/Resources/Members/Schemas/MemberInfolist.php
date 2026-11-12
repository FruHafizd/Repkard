<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Models\Member;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
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
                RepeatableEntry::make('cards')
                    ->label('Riwayat kartu')
                    ->state(fn (Member $record) => $record->cards()->orderByDesc('version')->get())
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('version')->label('Versi'),
                        TextEntry::make('issued_at')->label('Terbit')->dateTime('d M Y H:i'),
                        TextEntry::make('revoked_at')->label('Dicabut')->dateTime('d M Y H:i')->placeholder('Aktif'),
                        TextEntry::make('revoke_reason')->label('Alasan')->placeholder('-'),
                    ]),
                RepeatableEntry::make('followUps')
                    ->label('Riwayat tindak lanjut')
                    ->state(fn (Member $record) => $record->followUps()->with('user')->orderByDesc('contacted_at')->get())
                    ->placeholder('Belum ada tindak lanjut')
                    ->columns(5)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('contacted_at')->label('Tanggal')->dateTime('d M Y H:i'),
                        TextEntry::make('channel')->label('Cara'),
                        TextEntry::make('outcome')->label('Hasil'),
                        TextEntry::make('user.name')->label('Dicatat oleh'),
                        TextEntry::make('next_contact_on')->label('Hubungi lagi')->date('d M Y')->placeholder('-'),
                        TextEntry::make('note')->label('Catatan')->placeholder('-')->columnSpanFull(),
                    ]),
            ]);
    }
}
