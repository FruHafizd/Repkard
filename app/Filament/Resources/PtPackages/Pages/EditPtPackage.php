<?php

namespace App\Filament\Resources\PtPackages\Pages;

use App\Filament\Resources\PtPackages\PtPackageResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPtPackage extends EditRecord
{
    protected static string $resource = PtPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
