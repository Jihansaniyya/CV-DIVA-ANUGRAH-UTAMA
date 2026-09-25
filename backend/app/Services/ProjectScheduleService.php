<?php

namespace App\Services;

use App\Models\Period;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Membentuk periode pelaksanaan proyek.
 *
 * Periode dasar aplikasi adalah MINGGUAN (7 hari kalender), mengikuti kolom
 * "MINGGU KE" pada laporan bulanan. Pengelompokan bulan (BULAN I, BULAN II, ...)
 * dihitung 4 minggu per bulan agar konsisten dengan contoh laporan
 * (45 hari kalender = 6 minggu = Bulan I minggu I-IV, Bulan II minggu V-VI).
 */
class ProjectScheduleService
{
    public const MINGGU_PER_BULAN = 4;

    private const ROMAWI = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
        9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII', 13 => 'XIII', 14 => 'XIV', 15 => 'XV',
        16 => 'XVI', 17 => 'XVII', 18 => 'XVIII', 19 => 'XIX', 20 => 'XX',
    ];

    public static function romawi(int $angka): string
    {
        return self::ROMAWI[$angka] ?? (string) $angka;
    }

    /**
     * Bangun periode mingguan untuk sebuah proyek.
     * Periode lama yang sudah dipakai rencana/laporan tidak dihapus kecuali $force.
     */
    public function generateWeeklyPeriods(Project $project, bool $force = false): int
    {
        return DB::transaction(function () use ($project, $force) {
            if ($force) {
                $project->periods()->delete();
            }

            $mulai = CarbonImmutable::parse($project->tanggal_mulai);
            $selesai = CarbonImmutable::parse($project->tanggal_selesai);
            $jumlahHari = $mulai->diffInDays($selesai) + 1;
            $jumlahMinggu = (int) max(1, ceil($jumlahHari / 7));

            $dibuat = 0;

            for ($i = 1; $i <= $jumlahMinggu; $i++) {
                $awal = $mulai->addDays(($i - 1) * 7);
                $akhir = $awal->addDays(6);

                if ($akhir->greaterThan($selesai)) {
                    $akhir = $selesai;
                }

                $bulanKe = (int) ceil($i / self::MINGGU_PER_BULAN);
                $mingguKeBulan = $i - (($bulanKe - 1) * self::MINGGU_PER_BULAN);

                $period = Period::updateOrCreate(
                    ['project_id' => $project->id, 'urutan' => $i],
                    [
                        'nama_periode' => 'Minggu '.self::romawi($i),
                        'bulan_ke' => $bulanKe,
                        'minggu_ke' => $i,
                        'minggu_ke_bulan' => $mingguKeBulan,
                        'tanggal_mulai' => $awal->toDateString(),
                        'tanggal_selesai' => $akhir->toDateString(),
                    ]
                );

                if ($period->wasRecentlyCreated) {
                    $dibuat++;
                }
            }

            $project->forceFill([
                'jangka_waktu_hari' => $project->jangka_waktu_hari ?: $jumlahHari,
            ])->saveQuietly();

            return $dibuat;
        });
    }

    /** Cari periode yang memuat tanggal tertentu. */
    public function resolvePeriod(Project $project, string $tanggal): ?Period
    {
        return $project->periods()
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->first();
    }
}
