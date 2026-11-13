<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Grup menu sidebar panel admin. Urutan case = urutan grup di sidebar
 * (didaftarkan lewat ->navigationGroups(MenuGroup::class) di AdminPanelProvider).
 */
enum MenuGroup implements HasLabel
{
    case MejaDepan;
    case Member;
    case PersonalTrainer;
    case Keuangan;
    case Pengaturan;
    case TimAkses;

    public function getLabel(): string
    {
        return match ($this) {
            self::MejaDepan => 'Meja Depan',
            self::Member => 'Member',
            self::PersonalTrainer => 'Personal Trainer',
            self::Keuangan => 'Keuangan',
            self::Pengaturan => 'Pengaturan',
            self::TimAkses => 'Tim & Akses',
        };
    }
}
