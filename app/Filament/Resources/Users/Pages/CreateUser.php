<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected string $generatedPassword = '';

    protected function handleRecordCreation(array $data): Model
    {
        $role = $data['role'];
        unset($data['role']);

        $this->generatedPassword = Str::password(12, symbols: false);
        $data['password'] = $this->generatedPassword;
        $data['must_change_password'] = true;

        $user = static::getModel()::create($data);
        $user->assignRole($role);

        return $user;
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('Password sementara')
            ->body("Catat sekarang, password ini hanya ditampilkan sekali: {$this->generatedPassword}")
            ->warning()
            ->persistent()
            ->send();
    }
}
