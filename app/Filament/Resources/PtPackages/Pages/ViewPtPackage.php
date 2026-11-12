<?php

namespace App\Filament\Resources\PtPackages\Pages;

use App\Filament\Resources\PtPackages\PtPackageResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPtPackage extends ViewRecord
{
    protected static string $resource = PtPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
