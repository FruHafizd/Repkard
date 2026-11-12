<?php

namespace App\Filament\Resources\WalkIns\Pages;

use App\Enums\PaymentMethod;
use App\Filament\Resources\WalkIns\WalkInResource;
use App\Models\MembershipPlan;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListWalkIns extends ListRecords
{
    protected static string $resource = WalkInResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Catat pendatang')
                ->modalHeading('Catat pendatang')
                ->modalSubmitActionLabel('Catat')
                ->createAnother(false)
                ->schema([
                    TextInput::make('name')->label('Nama (opsional)')->maxLength(100),
                    TextInput::make('amount')->label('Jumlah bayar')
                        ->numeric()->minValue(0)->prefix('Rp')->required()
                        ->default(fn (): ?int => MembershipPlan::query()->active()->where('visit_quota', 1)->value('price')),
                    Select::make('method')->label('Metode')
                        ->options(PaymentMethod::class)->default(PaymentMethod::Cash->value)->required(),
                    Textarea::make('note')->label('Catatan')->maxLength(500),
                ])
                ->mutateDataUsing(fn (array $data): array => $data + [
                    'recorded_by' => auth()->id(),
                    'visited_at' => now(),
                ])
                ->successNotificationTitle('Pendatang dicatat'),
        ];
    }
}
