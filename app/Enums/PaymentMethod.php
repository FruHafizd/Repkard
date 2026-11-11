<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Qris = 'qris';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Transfer => 'Transfer',
            self::Qris => 'QRIS',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
