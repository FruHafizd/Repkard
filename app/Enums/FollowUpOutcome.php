<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FollowUpOutcome: string implements HasLabel
{
    case AkanDatang = 'akan_datang';
    case Sibuk = 'sibuk';
    case Sakit = 'sakit';
    case Pindah = 'pindah';
    case TidakTerhubung = 'tidak_terhubung';
    case Lainnya = 'lainnya';

    public function getLabel(): string
    {
        return match ($this) {
            self::AkanDatang => 'Akan datang lagi',
            self::Sibuk => 'Sedang sibuk',
            self::Sakit => 'Sakit / cedera',
            self::Pindah => 'Pindah kota',
            self::TidakTerhubung => 'Tidak bisa dihubungi',
            self::Lainnya => 'Lainnya',
        };
    }
}
