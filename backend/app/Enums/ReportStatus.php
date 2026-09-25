<?php

namespace App\Enums;

enum ReportStatus: string
{
    case DRAFT = 'DRAFT';
    case DIKIRIM = 'DIKIRIM';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::DIKIRIM => 'Dikirim',
        };
    }
}
