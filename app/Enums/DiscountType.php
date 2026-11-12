<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DiscountType: string implements HasLabel
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Persen',
            self::Fixed => 'Potongan nominal',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
