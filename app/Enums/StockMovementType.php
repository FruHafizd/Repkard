<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StockMovementType: string implements HasLabel
{
    case Restock = 'restock';
    case Sale = 'sale';
    case Void = 'void';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Restock => 'Barang masuk',
            self::Sale => 'Terjual',
            self::Void => 'Pembatalan',
            self::Adjustment => 'Koreksi',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
