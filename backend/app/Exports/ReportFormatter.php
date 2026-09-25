<?php

namespace App\Exports;

use App\Services\ProjectScheduleService;
use Illuminate\Support\Carbon;

/** Utilitas format tanggal & angka romawi untuk export. */
class ReportFormatter
{
    public static function romawi(int $angka): string
    {
        return ProjectScheduleService::romawi($angka);
    }

    public static function tanggal(?string $tanggal): ?string
    {
        return $tanggal ? Carbon::parse($tanggal)->translatedFormat('d F Y') : null;
    }

    public static function rentangTanggal(?string $dari, ?string $sampai): string
    {
        return trim(self::tanggal($dari).' s/d '.self::tanggal($sampai));
    }
}
