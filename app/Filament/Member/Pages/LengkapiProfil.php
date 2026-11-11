<?php

namespace App\Filament\Member\Pages;

use App\Services\MemberPhotoService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class LengkapiProfil extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Lengkapi Profil';

    protected static ?string $slug = 'lengkapi-profil';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $member = Filament::auth()->user()->member;

        // Profil yang sudah lengkap tidak bisa diubah sendiri (ganti foto = lewat persetujuan, Fase 2).
        if (! $member || (filled($member->gender) && filled($member->photo_path))) {
            $this->redirect(Filament::getUrl());

            return;
        }

        $this->form->fill($member->only(['gender', 'birth_date', 'address', 'emergency_contact_name', 'emergency_contact_phone']));
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('gender')
                ->label('Jenis kelamin')
                ->options(['L' => 'Laki-laki', 'P' => 'Perempuan'])
                ->required(),
            DatePicker::make('birth_date')
                ->label('Tanggal lahir')
                ->maxDate(now()),
            Textarea::make('address')
                ->label('Alamat'),
            TextInput::make('emergency_contact_name')
                ->label('Nama kontak darurat')
                ->maxLength(255),
            TextInput::make('emergency_contact_phone')
                ->label('HP kontak darurat')
                ->tel()
                ->maxLength(20),
            FileUpload::make('photo')
                ->label('Foto wajah')
                ->helperText('Foto ini akan dicocokkan dengan wajah Anda saat aktivasi di gym. Maksimal 5 MB.')
                ->image()
                ->maxSize(MemberPhotoService::MAX_KB)
                ->disk('local')
                ->visibility('private')
                ->required()
                ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(MemberPhotoService::class)->store($file)),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('simpan')
                ->footer([
                    Actions::make([
                        Action::make('simpan')->label('Simpan')->submit('simpan'),
                    ]),
                ]),
        ]);
    }

    public function simpan(): void
    {
        $data = $this->form->getState();
        $member = Filament::auth()->user()->member;

        // Cegah ganti foto lewat request ganda setelah profil lengkap.
        if (filled($member->photo_path) && filled($member->gender)) {
            $this->redirect(Filament::getUrl());

            return;
        }

        $member->update([
            'gender' => $data['gender'],
            'birth_date' => $data['birth_date'] ?? null,
            'address' => $data['address'] ?? null,
            'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
            'photo_path' => $data['photo'],
        ]);

        Notification::make()->success()->title('Profil lengkap')->send();

        $this->redirect(Filament::getUrl());
    }
}
