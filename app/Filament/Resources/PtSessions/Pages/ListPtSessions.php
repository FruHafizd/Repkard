<?php

namespace App\Filament\Resources\PtSessions\Pages;

use App\Filament\Resources\PtSessions\PtSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPtSessions extends ListRecords
{
    protected static string $resource = PtSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
