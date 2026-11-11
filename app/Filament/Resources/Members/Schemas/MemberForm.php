<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Enums\MemberStatus;
use App\Filament\Forms\Components\WebcamPhoto;
use App\Models\Member;
use App\Models\User;
use App\Services\MemberPhotoService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('Nomor HP')
                    ->tel()
                    ->required()
                    ->maxLength(20)
                    // Email cadangan dibuat dari nomor HP, jadi tidak boleh bentrok.
                    ->rule(fn ($livewire) => function (string $attribute, mixed $value, \Closure $fail) use ($livewire): void {
                        if (! $livewire->getRecord() && User::where('email', self::emailDariHp((string) $value))->exists()) {
                            $fail('Nomor HP ini sudah terdaftar.');
                        }
                    }),
                TextInput::make('email')
                    ->label('Email (opsional)')
                    ->email()
                    ->maxLength(255)
                    ->unique(
                        table: User::class,
                        column: 'email',
                        ignorable: fn (?Member $record) => $record?->user,
                    ),
                Select::make('gender')
                    ->label('Jenis kelamin')
                    ->options(['L' => 'Laki-laki', 'P' => 'Perempuan'])
                    ->required(),
                DatePicker::make('birth_date')
                    ->label('Tanggal lahir')
                    ->maxDate(now()),
                Textarea::make('address')
                    ->label('Alamat')
                    ->columnSpanFull(),
                TextInput::make('emergency_contact_name')
                    ->label('Nama kontak darurat')
                    ->maxLength(255),
                TextInput::make('emergency_contact_phone')
                    ->label('HP kontak darurat')
                    ->tel()
                    ->maxLength(20),
                Select::make('selected_plan_id')
                    ->label('Paket pilihan')
                    ->relationship('selectedPlan', 'name', fn (Builder $query) => $query->active()),
                WebcamPhoto::make('photo_webcam')
                    ->label('Foto dari kamera')
                    // Hanya data URL sementara; dibuang di halaman create/edit, tidak disimpan ke database.
                    ->helperText('Nyalakan kamera lalu ambil foto, atau unggah file di bawah.'),
                FileUpload::make('photo_upload')
                    ->label('atau unggah foto')
                    ->helperText('Kosongkan bila tidak ingin mengganti foto. Maksimal 5 MB.')
                    ->image()
                    ->maxSize(MemberPhotoService::MAX_KB)
                    ->disk('local')
                    ->visibility('private')
                    // Foto diproses (crop 3:4, JPEG) dan disimpan private; state berisi path hasil.
                    ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(MemberPhotoService::class)->store($file)),
                Select::make('status')
                    ->label('Status')
                    ->visibleOn('edit')
                    ->options(fn (?Member $record): array => $record
                        ? collect([$record->status, MemberStatus::Inactive])
                            ->mapWithKeys(fn (MemberStatus $s): array => [$s->value => $s->label()])
                            ->all()
                        : [])
                    ->required(),
            ]);
    }

    public static function emailDariHp(string $phone): string
    {
        return preg_replace('/\D/', '', $phone).'@member.repkard.local';
    }
}
