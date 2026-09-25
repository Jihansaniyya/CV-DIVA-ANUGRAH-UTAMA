<?php

namespace App\Enums;

enum ReportType: string
{
    case HARIAN = 'HARIAN';
    case MINGGUAN = 'MINGGUAN';
    case BULANAN = 'BULANAN';
    case MILESTONE = 'MILESTONE';
}
