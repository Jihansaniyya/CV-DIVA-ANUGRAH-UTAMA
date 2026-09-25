<?php

namespace App\Exports;

use Illuminate\Support\Carbon;

/** Utilitas format tanggal & angka romawi untuk export. */
class ReportFormatter
{
    private const ROMAWI = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'];

    public static function romawi(int $angka): string
    {
        return self::ROMAWI[$angka] ?? (string) $angka;
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
