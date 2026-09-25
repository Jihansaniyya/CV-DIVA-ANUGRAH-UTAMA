<?php

namespace App\Services;

use App\Models\Period;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Membentuk periode pelaksanaan proyek.
 *
 * Periode dasar aplikasi adalah MINGGUAN (7 hari kalender) berlabel M-I, M-II, ...
 * Jumlah periode = ceil(jumlah hari pelaksanaan / 7), misalnya 45 hari = 7 minggu.
 * Pengelompokan bulan (BULAN I, BULAN II, ...) dihitung 4 minggu per bulan.
 *
 * Periode proyek adalah sumber tunggal bagi Data Pekerjaan, Rencana, Progres,
 * Kurva S, dan Laporan. Periode tidak dibuat manual; setiap perubahan tanggal
 * proyek menyinkronkan ulang periode beserta data yang merujuknya.
 */
class ProjectScheduleService
{
    public const MINGGU_PER_BULAN = 4;

    private const ROMAWI = [
        1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD', 100 => 'C', 90 => 'XC',
        50 => 'L', 40 => 'XL', 10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I',
    ];

    public function __construct(private readonly WorkPlanService $plans) {}

    public static function romawi(int $angka): string
    {
        if ($angka < 1) {
            return (string) $angka;
        }

        $hasil = '';

        foreach (self::ROMAWI as $nilai => $simbol) {
            while ($angka >= $nilai) {
                $hasil .= $simbol;
                $angka -= $nilai;
            }
        }

        return $hasil;
    }

    public static function label(int $urutan): string
    {
        return 'M-'.self::romawi($urutan);
    }

    /**
     * Sinkronkan periode mingguan proyek dengan tanggal pelaksanaannya.
     *
     * Periode yang sudah ada dipertahankan (id tetap) dan diperbarui tanggalnya.
     * Bila durasi proyek berkurang, periode berlebih dihapus: rentang pekerjaan
     * yang melewati periode terakhir dipotong dan target volumenya dibagi ulang.
     * Laporan progres dipetakan ulang ke periode sesuai tanggal laporannya.
     *
     * @return int jumlah periode baru yang dibuat
     */
    public function generateWeeklyPeriods(Project $project): int
    {
        return DB::transaction(function () use ($project) {
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
                        'nama_periode' => self::label($i),
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

            $this->hapusPeriodeBerlebih($project, $jumlahMinggu);
            $this->petakanUlangTanggal($project);

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

    private function hapusPeriodeBerlebih(Project $project, int $jumlahMinggu): void
    {
        $berlebih = $project->periods()->where('urutan', '>', $jumlahMinggu)->pluck('id');

        if ($berlebih->isEmpty()) {
            return;
        }

        $terakhir = $project->periods()->where('urutan', $jumlahMinggu)->firstOrFail();

        $terdampak = $project->workItems()
            ->where(fn ($q) => $q->whereIn('period_mulai_id', $berlebih)->orWhereIn('period_selesai_id', $berlebih))
            ->get();

        foreach ($terdampak as $item) {
            if ($berlebih->contains($item->period_mulai_id)) {
                $item->period_mulai_id = $terakhir->id;
            }

            if ($berlebih->contains($item->period_selesai_id)) {
                $item->period_selesai_id = $terakhir->id;
            }

            $item->saveQuietly();
        }

        // Rencana pada periode berlebih ikut terhapus (cascade).
        $project->periods()->whereIn('id', $berlebih)->delete();

        foreach ($terdampak as $item) {
            $this->plans->distributeEvenly($item->fresh());
        }
    }

    /**
     * Laporan progres selalu mengikuti periode yang memuat tanggal laporannya.
     * Milestone memakai periode pilihan Admin; bila periodenya terhapus menjadi null.
     */
    private function petakanUlangTanggal(Project $project): void
    {
        foreach ($project->progressReports()->get() as $report) {
            $periodId = $this->resolvePeriod($project, $report->tanggal_laporan->toDateString())?->id;

            if ($report->period_id !== $periodId) {
                $report->period_id = $periodId;
                $report->saveQuietly();
            }
        }
    }
}
