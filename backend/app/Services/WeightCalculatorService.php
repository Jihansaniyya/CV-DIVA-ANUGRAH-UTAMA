<?php

namespace App\Services;

use App\Models\ProgressDetail;
use App\Models\Project;
use App\Models\WorkItem;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung bobot pekerjaan (%) beserta turunannya.
 *
 * Formula bobot (mengikuti struktur laporan mingguan/bulanan CV Diva Anugrah Utama):
 *   harga_pekerjaan = volume x harga_satuan
 *   bobot_i         = harga_pekerjaan_i / SUM(harga_pekerjaan) x 100
 *
 * Bila proyek sama sekali tidak memakai data harga (semua harga_satuan kosong),
 * sistem memakai kolom bobot_manual yang diisi Admin. Tidak ada nilai harga
 * maupun bobot yang dibangkitkan secara acak oleh sistem.
 */
class WeightCalculatorService
{
    public function recalculateProject(Project $project): void
    {
        DB::transaction(function () use ($project) {
            $items = $project->workItems()->get();

            if ($items->isEmpty()) {
                return;
            }

            $usesPrice = $items->contains(fn (WorkItem $item) => $item->harga_satuan !== null && (float) $item->harga_satuan > 0);

            $total = 0.0;

            foreach ($items as $item) {
                if ($usesPrice) {
                    $hargaPekerjaan = round((float) $item->volume * (float) ($item->harga_satuan ?? 0), 2);
                    $item->harga_pekerjaan = $hargaPekerjaan;
                    $total += $hargaPekerjaan;
                } else {
                    $item->harga_pekerjaan = null;
                }
            }

            foreach ($items as $item) {
                if ($usesPrice) {
                    $item->bobot = $total > 0
                        ? round((float) $item->harga_pekerjaan / $total * 100, 4)
                        : 0;
                } else {
                    $item->bobot = round((float) ($item->bobot_manual ?? 0), 4);
                }

                $item->saveQuietly();
            }

            $this->recalculatePlans($project);
            $this->recalculateActuals($project);
        });
    }

    /** Selaraskan target_bobot rencana dengan bobot pekerjaan terbaru. */
    public function recalculatePlans(Project $project): void
    {
        $project->loadMissing('workItems');
        $bobotMap = $project->workItems->pluck('bobot', 'id');
        $volumeMap = $project->workItems->pluck('volume', 'id');

        foreach ($project->workPlans()->get() as $plan) {
            $volume = (float) ($volumeMap[$plan->work_item_id] ?? 0);
            $bobot = (float) ($bobotMap[$plan->work_item_id] ?? 0);

            $plan->target_persentase = $volume > 0
                ? round((float) $plan->target_volume / $volume * 100, 4)
                : 0;
            $plan->target_bobot = round((float) $plan->target_persentase * $bobot / 100, 4);
            $plan->saveQuietly();
        }
    }

    /** Selaraskan bobot realisasi pada seluruh detail progres proyek. */
    public function recalculateActuals(Project $project): void
    {
        $project->loadMissing('workItems');
        $bobotMap = $project->workItems->pluck('bobot', 'id');
        $volumeMap = $project->workItems->pluck('volume', 'id');

        $reportIds = $project->progressReports()->pluck('id');

        if ($reportIds->isEmpty()) {
            return;
        }

        $details = ProgressDetail::whereIn('progress_report_id', $reportIds)->get();

        foreach ($details as $detail) {
            $volume = (float) ($volumeMap[$detail->work_item_id] ?? 0);
            $bobot = (float) ($bobotMap[$detail->work_item_id] ?? 0);

            $detail->persentase_realisasi = $volume > 0
                ? round((float) $detail->volume_realisasi / $volume * 100, 4)
                : 0;
            $detail->bobot_realisasi = round((float) $detail->persentase_realisasi * $bobot / 100, 4);
            $detail->saveQuietly();
        }
    }

    /** Hitung nilai turunan untuk satu baris rencana. */
    public function planValues(WorkItem $item, float $targetVolume): array
    {
        $persentase = (float) $item->volume > 0
            ? round($targetVolume / (float) $item->volume * 100, 4)
            : 0.0;

        return [
            'target_persentase' => $persentase,
            'target_bobot' => round($persentase * (float) $item->bobot / 100, 4),
        ];
    }

    /** Hitung nilai turunan untuk satu baris realisasi. */
    public function actualValues(WorkItem $item, float $volumeRealisasi): array
    {
        $persentase = (float) $item->volume > 0
            ? round($volumeRealisasi / (float) $item->volume * 100, 4)
            : 0.0;

        return [
            'persentase_realisasi' => $persentase,
            'bobot_realisasi' => round($persentase * (float) $item->bobot / 100, 4),
        ];
    }
}
