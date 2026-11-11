<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $role = $data['role'];
        unset($data['role']);

        // Owner tidak boleh menonaktifkan dirinya sendiri.
        if ($record->is(auth()->user())) {
            unset($data['is_active']);
        }

        $record->update($data);
        $record->syncRoles([$role]);

        return $record;
    }
}
