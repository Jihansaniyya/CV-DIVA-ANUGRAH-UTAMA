<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * Zona waktu operasional proyek (config `app.zona_lokal`, dari APP_TIMEZONE; bawaan Asia/Makassar).
 *
 * Aturannya:
 *   - Timestamp (dikirim_pada, diunggah_pada, digenerate_pada, created_at) tetap disimpan dalam UTC
 *     (`app.timezone`) agar data lama tidak bergeser; hanya dikonversi saat ditampilkan.
 *   - Tanggal kalender (tanggal_mulai, tanggal_selesai, tanggal_laporan, periode) adalah kolom DATE tanpa
 *     jam. "Hari ini" untuk membandingkannya harus tanggal lokal lapangan, bukan tanggal UTC: pukul
 *     00.00-08.00 WITA tanggal UTC masih hari sebelumnya.
 */
class ZonaWaktu
{
    public static function zona(): string
    {
        return (string) config('app.zona_lokal', 'Asia/Makassar');
    }

    /**
     * Tanggal hari ini di lokasi proyek, sebagai tengah malam zona aplikasi. Bentuk ini sama dengan nilai
     * kolom DATE yang di-cast Eloquent, sehingga aman dibandingkan langsung (lessThan, betweenIncluded, ...).
     */
    public static function hariIni(): CarbonImmutable
    {
        return CarbonImmutable::parse(self::sekarang()->toDateString(), config('app.timezone'));
    }

    /** Waktu sekarang dalam zona lokal, untuk ditampilkan atau dipakai pada nama berkas. */
    public static function sekarang(): CarbonImmutable
    {
        return CarbonImmutable::now(self::zona());
    }
}
