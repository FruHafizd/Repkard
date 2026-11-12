<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentType: string implements HasLabel
{
    case Purchase = 'purchase';
    case Upgrade = 'upgrade';
    case Pt = 'pt';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Pembelian',
            self::Upgrade => 'Upgrade',
            self::Pt => 'Paket PT',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
