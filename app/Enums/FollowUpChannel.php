<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FollowUpChannel: string implements HasLabel
{
    case Telepon = 'telepon';
    case Chat = 'chat';
    case Langsung = 'langsung';
    case Lainnya = 'lainnya';

    public function getLabel(): string
    {
        return match ($this) {
            self::Telepon => 'Telepon',
            self::Chat => 'Chat',
            self::Langsung => 'Bertemu langsung',
            self::Lainnya => 'Lainnya',
        };
    }
}
