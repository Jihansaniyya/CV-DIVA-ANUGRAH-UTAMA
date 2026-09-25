<?php

namespace App\Enums;

enum RoleCode: string
{
    case ADMIN = 'ADMIN';
    case QS = 'QS';
    case KONTRAKTOR = 'KONTRAKTOR';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Admin',
            self::QS => 'Quantity Surveyor',
            self::KONTRAKTOR => 'Kontraktor',
        };
    }
}
