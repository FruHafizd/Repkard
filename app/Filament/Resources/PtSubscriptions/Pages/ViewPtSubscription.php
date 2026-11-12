<?php

namespace App\Filament\Resources\PtSubscriptions\Pages;

use App\Filament\Resources\PtSubscriptions\PtSubscriptionResource;
use App\Filament\Resources\PtSubscriptions\Tables\PtSubscriptionsTable;
use App\Models\PtSubscription;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewPtSubscription extends ViewRecord
{
    protected static string $resource = PtSubscriptionResource::class;

    /** Tanpa scope trainer, supaya klien trainer lain ditolak Policy (403), bukan 404. */
    protected function resolveRecord(int|string $key): Model
    {
        return PtSubscription::query()->findOrFail($key);
    }

    protected function getHeaderActions(): array
    {
        return [
            PtSubscriptionsTable::gantiTrainerAction(),
            PtSubscriptionsTable::batalkanAction(),
        ];
    }
}
