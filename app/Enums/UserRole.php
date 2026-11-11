<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Pegawai = 'pegawai';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Pegawai => 'Pegawai',
            self::Member => 'Member',
        };
    }
}
