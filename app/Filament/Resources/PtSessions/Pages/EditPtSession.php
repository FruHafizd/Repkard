<?php

namespace App\Filament\Resources\PtSessions\Pages;

use App\Filament\Resources\PtSessions\PtSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPtSession extends EditRecord
{
    protected static string $resource = PtSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
