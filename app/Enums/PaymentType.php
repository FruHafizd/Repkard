<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentType: string implements HasLabel
{
    case Purchase = 'purchase';
    case Upgrade = 'upgrade';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Pembelian',
            self::Upgrade => 'Upgrade',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
