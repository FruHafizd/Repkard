<?php

namespace App\Filament\Resources\Members\Pages;

use App\Enums\MemberStatus;
use App\Filament\Resources\Members\MemberResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua'),
            'menunggu' => Tab::make('Menunggu aktivasi')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', MemberStatus::Pending)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
