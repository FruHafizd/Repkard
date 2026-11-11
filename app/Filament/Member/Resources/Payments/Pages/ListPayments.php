<?php

namespace App\Filament\Member\Resources\Payments\Pages;

use App\Filament\Member\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;
}
