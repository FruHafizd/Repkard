<?php

namespace App\Filament\Resources\PtPackages\Pages;

use App\Filament\Resources\PtPackages\PtPackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPtPackages extends ListRecords
{
    protected static string $resource = PtPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
