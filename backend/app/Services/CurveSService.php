<?php

namespace App\Services;

use App\Enums\ReportStatus;
use App\Models\ProgressDetail;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Membentuk Kurva S: rencana kumulatif vs realisasi kumulatif per periode.
 *
 *   rencana periode   = SUM(work_plans.target_bobot) pada periode tsb
 *   realisasi periode = SUM(progress_details.bobot_realisasi) dari laporan berstatus DIKIRIM
 *   kumulatif         = penjumlahan berjalan sejak periode pertama
 *   deviasi           = realisasi kumulatif - rencana kumulatif
 *
 * Garis realisasi hanya digambar sampai periode yang sudah berjalan (tanggal_mulai <= hari ini)
 * supaya periode yang belum dilaksanakan tidak terbaca sebagai realisasi 0%.
 */
class CurveSService
{
    public function build(Project $project): array
    {
        $periods = $project->periods()->orderBy('urutan')->get();

        $rencanaPerPeriode = $project->workPlans()
            ->selectRaw('period_id, SUM(target_bobot) as total')
            ->groupBy('period_id')
            ->pluck('total', 'period_id');

        $aktualPerPeriode = $this->actualByPeriod($project);

        $hariIni = CarbonImmutable::now()->startOfDay();
        $rencanaKumulatif = 0.0;
        $aktualKumulatif = 0.0;

        $titik = [];
        $progresRencanaSaatIni = 0.0;

        foreach ($periods as $period) {
            $rencana = round((float) ($rencanaPerPeriode[$period->id] ?? 0), 4);
            $aktual = round((float) ($aktualPerPeriode[$period->id] ?? 0), 4);

            $rencanaKumulatif = round($rencanaKumulatif + $rencana, 4);
            $sudahBerjalan = CarbonImmutable::parse($period->tanggal_mulai)->lessThanOrEqualTo($hariIni);

            if ($sudahBerjalan) {
                $aktualKumulatif = round($aktualKumulatif + $aktual, 4);
                $progresRencanaSaatIni = $rencanaKumulatif;
            }

            $titik[] = [
                'period_id' => $period->id,
                'urutan' => $period->urutan,
                'nama_periode' => $period->nama_periode,
                'bulan_ke' => $period->bulan_ke,
                'minggu_ke' => $period->minggu_ke,
                'tanggal_mulai' => $period->tanggal_mulai->toDateString(),
                'tanggal_selesai' => $period->tanggal_selesai->toDateString(),
                'rencana' => $rencana,
                'rencana_kumulatif' => $rencanaKumulatif,
                'aktual' => $sudahBerjalan ? $aktual : null,
                'aktual_kumulatif' => $sudahBerjalan ? $aktualKumulatif : null,
                'deviasi' => $sudahBerjalan ? round($aktualKumulatif - $rencanaKumulatif, 4) : null,
            ];
        }

        $progresAktual = $this->totalActual($project);

        return [
            'titik' => $titik,
            'milestones' => $project->milestones()->get()->map(fn ($m) => [
                'id' => $m->id,
                'nama' => $m->nama,
                'period_id' => $m->period_id ?? $periods->first(
                    fn ($p) => $m->tanggal_target->betweenIncluded($p->tanggal_mulai, $p->tanggal_selesai)
                )?->id,
                'tanggal_target' => $m->tanggal_target->toDateString(),
                'target_persentase' => (float) $m->target_persentase,
                'status' => $m->status,
            ])->all(),
            'ringkasan' => [
                'total_bobot_rencana' => round((float) $project->workPlans()->sum('target_bobot'), 4),
                'progres_rencana' => round($progresRencanaSaatIni, 4),
                'progres_aktual' => round($progresAktual, 4),
                'deviasi' => round($progresAktual - $progresRencanaSaatIni, 4),
                'jumlah_periode' => $periods->count(),
            ],
        ];
    }

    /** SUM bobot realisasi per period_id (hanya laporan DIKIRIM). */
    public function actualByPeriod(Project $project): Collection
    {
        return ProgressDetail::query()
            ->join('progress_reports', 'progress_reports.id', '=', 'progress_details.progress_report_id')
            ->where('progress_reports.project_id', $project->id)
            ->where('progress_reports.status', ReportStatus::DIKIRIM->value)
            ->whereNotNull('progress_reports.period_id')
            ->selectRaw('progress_reports.period_id as period_id, SUM(progress_details.bobot_realisasi) as total')
            ->groupBy('progress_reports.period_id')
            ->pluck('total', 'period_id');
    }

    /** Total progres aktual proyek (%) dari seluruh laporan yang sudah dikirim. */
    public function totalActual(Project $project): float
    {
        return round((float) ProgressDetail::query()
            ->join('progress_reports', 'progress_reports.id', '=', 'progress_details.progress_report_id')
            ->where('progress_reports.project_id', $project->id)
            ->where('progress_reports.status', ReportStatus::DIKIRIM->value)
            ->sum('progress_details.bobot_realisasi'), 4);
    }

    /** Progres rencana sampai hari ini (%) berdasarkan periode yang sudah berjalan. */
    public function plannedToDate(Project $project): float
    {
        $hariIni = CarbonImmutable::now()->toDateString();

        return round((float) $project->workPlans()
            ->whereHas('period', fn ($q) => $q->whereDate('tanggal_mulai', '<=', $hariIni))
            ->sum('target_bobot'), 4);
    }

    /** Realisasi kumulatif volume per work_item (dipakai laporan mingguan/bulanan). */
    public function actualVolumeByWorkItem(Project $project, ?string $sampaiTanggal = null): Collection
    {
        $query = ProgressDetail::query()
            ->join('progress_reports', 'progress_reports.id', '=', 'progress_details.progress_report_id')
            ->where('progress_reports.project_id', $project->id)
            ->where('progress_reports.status', ReportStatus::DIKIRIM->value);

        if ($sampaiTanggal !== null) {
            $query->whereDate('progress_reports.tanggal_laporan', '<=', $sampaiTanggal);
        }

        return $query->selectRaw('progress_details.work_item_id as work_item_id, SUM(progress_details.volume_realisasi) as volume, SUM(progress_details.bobot_realisasi) as bobot')
            ->groupBy('progress_details.work_item_id')
            ->get()
            ->keyBy('work_item_id');
    }

    /** Realisasi volume per work_item dalam rentang tanggal tertentu. */
    public function actualVolumeBetween(Project $project, string $dari, string $sampai): Collection
    {
        return ProgressDetail::query()
            ->join('progress_reports', 'progress_reports.id', '=', 'progress_details.progress_report_id')
            ->where('progress_reports.project_id', $project->id)
            ->where('progress_reports.status', ReportStatus::DIKIRIM->value)
            ->whereBetween('progress_reports.tanggal_laporan', [$dari, $sampai])
            ->selectRaw('progress_details.work_item_id as work_item_id, SUM(progress_details.volume_realisasi) as volume, SUM(progress_details.bobot_realisasi) as bobot')
            ->groupBy('progress_details.work_item_id')
            ->get()
            ->keyBy('work_item_id');
    }
}
