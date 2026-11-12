<?php

namespace App\Filament\Member\Resources\Announcements\Pages;

use App\Filament\Member\Resources\Announcements\AnnouncementResource;
use Filament\Resources\Pages\ListRecords;

class ListAnnouncements extends ListRecords
{
    protected static string $resource = AnnouncementResource::class;
}
