<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Dipakai bersama oleh panel admin dan panel member.
 */
class GantiPassword extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Ganti Password';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('current_password')
                ->label('Password lama')
                ->password()
                ->required()
                ->currentPassword(guard: Filament::getAuthGuard()),
            TextInput::make('password')
                ->label('Password baru')
                ->password()
                ->required()
                ->rule(Password::min(8))
                ->different('current_password')
                ->confirmed(),
            TextInput::make('password_confirmation')
                ->label('Konfirmasi password baru')
                ->password()
                ->required()
                ->dehydrated(false),
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

        Filament::auth()->user()->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ])->save();

        Notification::make()->success()->title('Password berhasil diganti')->send();

        $this->redirect(Filament::getUrl());
    }
}
