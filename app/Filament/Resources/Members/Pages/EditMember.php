<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use App\Services\MemberPhotoService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['name'] = $this->getRecord()->user->name;
        $data['email'] = $this->getRecord()->user->email;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $webcam = $data['photo_webcam'] ?? null;
        unset($data['photo_webcam']);

        if (filled($webcam)) {
            app(MemberPhotoService::class)->delete($data['photo_upload'] ?? null);
            $data['photo_upload'] = app(MemberPhotoService::class)->storeFromDataUrl($webcam);
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            $fotoBaru = $data['photo_upload'] ?? null;
            $fotoLama = $record->photo_path;

            $record->user->update([
                'name' => $data['name'],
                'email' => filled($data['email'] ?? null) ? $data['email'] : $record->user->email,
                'phone' => $data['phone'],
            ]);

            unset($data['name'], $data['email'], $data['photo_upload']);

            if ($fotoBaru) {
                $data['photo_path'] = $fotoBaru;
            }

            $record->update($data);

            if ($fotoBaru && $fotoLama) {
                app(MemberPhotoService::class)->delete($fotoLama);
            }

            return $record;
        });
    }
}
