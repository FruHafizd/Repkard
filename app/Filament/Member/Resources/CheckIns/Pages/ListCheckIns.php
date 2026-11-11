<?php

namespace App\Filament\Member\Resources\CheckIns\Pages;

use App\Filament\Member\Resources\CheckIns\CheckInResource;
use Filament\Resources\Pages\ListRecords;

class ListCheckIns extends ListRecords
{
    protected static string $resource = CheckInResource::class;
}
