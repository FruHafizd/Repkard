<?php

namespace App\Filament\Resources\PtSessions\Pages;

use App\Filament\Resources\PtSessions\PtSessionResource;
use App\Filament\Resources\PtSessions\Schemas\PtSessionForm;
use App\Services\PtService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class CreatePtSession extends CreateRecord
{
    protected static string $resource = PtSessionResource::class;

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        // Dicek ulang di server: trainer hanya boleh menjadwalkan kliennya sendiri.
        $subscription = PtSessionForm::subscriptionQuery()->find($data['pt_subscription_id']);

        try {
            if ($subscription === null) {
                throw new InvalidArgumentException('Klien PT tidak ditemukan atau bukan klien Anda.');
            }

            return app(PtService::class)->schedule(
                $subscription,
                Carbon::parse($data['scheduled_at']),
                (int) $data['duration_minutes'],
                auth()->user(),
            );
        } catch (InvalidArgumentException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            $this->halt();
        }
    }
}
