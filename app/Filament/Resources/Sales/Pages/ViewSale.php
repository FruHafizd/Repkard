<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Resources\Sales\SaleResource;
use App\Filament\Resources\Sales\Tables\SalesTable;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('struk')
                ->label('Cetak struk')
                ->color('gray')
                ->url(fn (): string => route('kasir.receipt', $this->getRecord()), shouldOpenInNewTab: true),
            SalesTable::cancelAction(),
        ];
    }
}
