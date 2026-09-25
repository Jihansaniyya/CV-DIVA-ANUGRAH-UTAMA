<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case BELUM_DIMULAI = 'BELUM_DIMULAI';
    case BERJALAN = 'BERJALAN';
    case SELESAI = 'SELESAI';
    case TERLAMBAT = 'TERLAMBAT';

    public function label(): string
    {
        return match ($this) {
            self::BELUM_DIMULAI => 'Belum Dimulai',
            self::BERJALAN => 'Berjalan',
            self::SELESAI => 'Selesai',
            self::TERLAMBAT => 'Terlambat',
        };
    }
}
