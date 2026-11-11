<?php

namespace App\Filament\Member\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use SensitiveParameter;

class EditProfile extends BaseEditProfile
{
    /** Kolom member yang boleh diubah sendiri. */
    private const MEMBER_FIELDS = ['phone', 'address', 'emergency_contact_name', 'emergency_contact_phone'];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $member = $this->getUser()->member;

        $memberData = $member?->only([...self::MEMBER_FIELDS, 'gender']) ?? [];
        $memberData['birth_date'] = $member?->birth_date?->format('d M Y');

        return $memberData + $data;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            // Hanya tampil; tidak dikirim ke server (dehydrated false).
            TextInput::make('name')->label('Nama')->disabled()->dehydrated(false),
            TextInput::make('email')->label('Email')->disabled()->dehydrated(false),
            Select::make('gender')->label('Jenis kelamin')
                ->options(['L' => 'Laki-laki', 'P' => 'Perempuan'])
                ->disabled()->dehydrated(false),
            TextInput::make('birth_date')->label('Tanggal lahir')->disabled()->dehydrated(false),

            TextInput::make('phone')->label('Nomor HP')->tel()->required()->maxLength(20),
            Textarea::make('address')->label('Alamat'),
            TextInput::make('emergency_contact_name')->label('Nama kontak darurat')->maxLength(255),
            TextInput::make('emergency_contact_phone')->label('HP kontak darurat')->tel()->maxLength(20),

            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }

    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        // Allowlist di server: nama, email, foto, tanggal lahir, jenis kelamin diabaikan walau dikirim.
        $memberData = Arr::only($data, self::MEMBER_FIELDS);

        $userData = Arr::only($data, ['password']);
        if (isset($memberData['phone'])) {
            $userData['phone'] = $memberData['phone'];
        }

        if ($userData !== []) {
            $record->update($userData);
        }

        $record->member?->update($memberData);

        return $record;
    }
}
