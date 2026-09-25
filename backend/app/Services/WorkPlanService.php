<?php

namespace App\Services;

use App\Models\Period;
use App\Models\Project;
use App\Models\WorkItem;
use App\Models\WorkPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rencana pekerjaan per periode (dasar pembentukan Kurva S).
 *
 *   target_persentase = target_volume / volume pekerjaan x 100
 *   target_bobot      = target_persentase x bobot pekerjaan / 100
 *
 * Total target_volume seluruh periode untuk satu pekerjaan tidak boleh
 * melebihi volume rencana pekerjaan tersebut.
 */
class WorkPlanService
{
    public function __construct(private readonly WeightCalculatorService $weights) {}

    /**
     * Simpan/perbarui sekumpulan baris rencana sekaligus.
     *
     * @param  array<int,array{work_item_id:int,period_id:int,target_volume:float,catatan?:string|null}>  $rows
     */
    public function sync(Project $project, array $rows): void
    {
        DB::transaction(function () use ($project, $rows) {
            $items = $project->workItems()->get()->keyBy('id');
            $periodIds = $project->periods()->pluck('id');

            $akumulasi = [];

            foreach ($rows as $row) {
                if (! $items->has($row['work_item_id'])) {
                    throw ValidationException::withMessages([
                        'work_item_id' => 'Pekerjaan tidak ditemukan pada proyek ini.',
                    ]);
                }

                if (! $periodIds->contains($row['period_id'])) {
                    throw ValidationException::withMessages([
                        'period_id' => 'Periode tidak ditemukan pada proyek ini.',
                    ]);
                }

                $akumulasi[$row['work_item_id']] = ($akumulasi[$row['work_item_id']] ?? 0) + (float) $row['target_volume'];
            }

            foreach ($akumulasi as $workItemId => $totalTarget) {
                /** @var WorkItem $item */
                $item = $items[$workItemId];
                $idsDalamKiriman = collect($rows)->where('work_item_id', $workItemId)->pluck('period_id');

                $targetLain = (float) WorkPlan::where('work_item_id', $workItemId)
                    ->whereNotIn('period_id', $idsDalamKiriman)
                    ->sum('target_volume');

                if (round($totalTarget + $targetLain, 3) > round((float) $item->volume, 3) + 0.0001) {
                    throw ValidationException::withMessages([
                        'rows' => 'Total target volume pekerjaan "'.$item->uraian_pekerjaan.'" melebihi volume rencana ('.number_format((float) $item->volume, 2, ',', '.').').',
                    ]);
                }
            }

            foreach ($rows as $row) {
                /** @var WorkItem $item */
                $item = $items[$row['work_item_id']];
                $nilai = $this->weights->planValues($item, (float) $row['target_volume']);

                if ((float) $row['target_volume'] <= 0) {
                    WorkPlan::where('work_item_id', $item->id)
                        ->where('period_id', $row['period_id'])
                        ->delete();

                    continue;
                }

                WorkPlan::updateOrCreate(
                    ['work_item_id' => $item->id, 'period_id' => $row['period_id']],
                    [
                        'project_id' => $project->id,
                        'target_volume' => $row['target_volume'],
                        'target_persentase' => $nilai['target_persentase'],
                        'target_bobot' => $nilai['target_bobot'],
                        'catatan' => $row['catatan'] ?? null,
                    ]
                );
            }
        });
    }

    /** Matriks rencana: pekerjaan x periode, siap dipakai tabel rencana di frontend. */
    public function matrix(Project $project): array
    {
        $periods = $project->periods()->orderBy('urutan')->get();
        $items = $project->workItems()->with(['unit', 'category'])->get();
        $plans = $project->workPlans()->get();

        $baris = $items->map(function (WorkItem $item) use ($periods, $plans) {
            $planItem = $plans->where('work_item_id', $item->id)->keyBy('period_id');

            return [
                'work_item_id' => $item->id,
                'uraian_pekerjaan' => $item->uraian_pekerjaan,
                'kategori' => $item->category?->nama,
                'satuan' => $item->unit?->code,
                'volume' => (float) $item->volume,
                'bobot' => (float) $item->bobot,
                'periode' => $periods->map(fn (Period $p) => [
                    'period_id' => $p->id,
                    'target_volume' => (float) ($planItem[$p->id]->target_volume ?? 0),
                    'target_persentase' => (float) ($planItem[$p->id]->target_persentase ?? 0),
                    'target_bobot' => (float) ($planItem[$p->id]->target_bobot ?? 0),
                ])->all(),
                'total_target_volume' => (float) $planItem->sum('target_volume'),
                'sisa_volume' => round((float) $item->volume - (float) $planItem->sum('target_volume'), 3),
            ];
        })->all();

        $totalPerPeriode = $periods->map(function (Period $p) use ($plans) {
            return [
                'period_id' => $p->id,
                'nama_periode' => $p->nama_periode,
                'rencana' => round((float) $plans->where('period_id', $p->id)->sum('target_bobot'), 4),
            ];
        });

        $kumulatif = 0.0;
        $totalPerPeriode = $totalPerPeriode->map(function (array $row) use (&$kumulatif) {
            $kumulatif = round($kumulatif + $row['rencana'], 4);
            $row['kumulatif'] = $kumulatif;

            return $row;
        })->all();

        return [
            'periode' => $periods->map(fn (Period $p) => [
                'id' => $p->id,
                'urutan' => $p->urutan,
                'nama_periode' => $p->nama_periode,
                'bulan_ke' => $p->bulan_ke,
                'minggu_ke' => $p->minggu_ke,
                'tanggal_mulai' => $p->tanggal_mulai->toDateString(),
                'tanggal_selesai' => $p->tanggal_selesai->toDateString(),
            ])->all(),
            'baris' => $baris,
            'total_per_periode' => $totalPerPeriode,
        ];
    }
}
