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
 * Target hanya boleh diisi pada periode aktif pekerjaan (Periode Mulai s/d
 * Periode Selesai). Total target_volume seluruh periode untuk satu pekerjaan
 * tidak boleh melebihi volume rencana pekerjaan tersebut.
 */
class WorkPlanService
{
    /** Presisi target_volume (decimal 15,4). */
    private const DESIMAL = 4;

    private const TOLERANSI = 0.00005;

    public function __construct(private readonly WeightCalculatorService $weights) {}

    /**
     * Simpan/perbarui sekumpulan baris rencana sekaligus.
     *
     * @param  array<int,array{work_item_id:int,period_id:int,target_volume:float,catatan?:string|null}>  $rows
     */
    public function sync(Project $project, array $rows): void
    {
        DB::transaction(function () use ($project, $rows) {
            $items = $project->workItems()->with(['periodMulai', 'periodSelesai'])->get()->keyBy('id');
            $periods = $project->periods()->get()->keyBy('id');

            $akumulasi = [];

            foreach ($rows as $row) {
                if (! $items->has($row['work_item_id'])) {
                    throw ValidationException::withMessages([
                        'work_item_id' => 'Pekerjaan tidak ditemukan pada proyek ini.',
                    ]);
                }

                if (! $periods->has($row['period_id'])) {
                    throw ValidationException::withMessages([
                        'period_id' => 'Periode tidak ditemukan pada proyek ini.',
                    ]);
                }

                /** @var WorkItem $item */
                $item = $items[$row['work_item_id']];

                if ((float) $row['target_volume'] > 0 && ! $this->periodeAktif($item, $periods[$row['period_id']])) {
                    throw ValidationException::withMessages([
                        'rows' => 'Periode '.$periods[$row['period_id']]->nama_periode.' berada di luar rentang pelaksanaan pekerjaan "'
                            .$item->uraian_pekerjaan.'" ('.$this->labelRentang($item).').',
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

                if (round($totalTarget + $targetLain, self::DESIMAL) > round((float) $item->volume, self::DESIMAL) + self::TOLERANSI) {
                    throw ValidationException::withMessages([
                        'rows' => 'Total target volume pekerjaan "'.$item->uraian_pekerjaan.'" melebihi volume rencana ('.number_format((float) $item->volume, 2, ',', '.').').',
                    ]);
                }
            }

            foreach ($rows as $row) {
                $this->simpanTarget($items[$row['work_item_id']], (int) $row['period_id'], (float) $row['target_volume'], $row['catatan'] ?? null);
            }
        });
    }

    /**
     * Selaraskan rencana setelah data pekerjaan disimpan.
     *
     * - Pekerjaan baru, atau Periode Mulai/Selesai berubah: target dibagi rata ulang.
     * - Hanya volume berubah: target diskalakan proporsional.
     *
     * @param  array{period_mulai_id:int|null,period_selesai_id:int|null,volume:float}|null  $sebelum
     */
    public function syncWithWorkItem(WorkItem $item, ?array $sebelum = null): void
    {
        $item->load(['periodMulai', 'periodSelesai']);

        $rentangBerubah = $sebelum === null
            || (int) $sebelum['period_mulai_id'] !== (int) $item->period_mulai_id
            || (int) $sebelum['period_selesai_id'] !== (int) $item->period_selesai_id;

        if ($rentangBerubah) {
            $this->distributeEvenly($item);
        } elseif (abs($sebelum['volume'] - (float) $item->volume) > self::TOLERANSI) {
            $this->scaleToVolume($item, $sebelum['volume']);
        }
    }

    /**
     * Nilai awal rencana: volume pekerjaan dibagi rata ke seluruh periode aktif.
     * Contoh 23,87 m³ pada M-II s/d M-V = 5,9675 m³ per minggu. Sisa pembulatan
     * dimasukkan ke periode terakhir agar total tetap sama dengan volume.
     * Rencana lama pekerjaan ini diganti seluruhnya.
     */
    public function distributeEvenly(WorkItem $item): void
    {
        DB::transaction(function () use ($item) {
            $item->workPlans()->delete();

            $aktif = $item->activePeriods();
            $jumlah = $aktif->count();

            if ($jumlah === 0 || (float) $item->volume <= 0) {
                return;
            }

            $volume = (float) $item->volume;
            $bagian = round($volume / $jumlah, self::DESIMAL);

            foreach ($aktif->values() as $index => $period) {
                $target = $index === $jumlah - 1
                    ? round($volume - $bagian * ($jumlah - 1), self::DESIMAL)
                    : $bagian;

                $this->simpanTarget($item, $period->id, $target);
            }
        });
    }

    /**
     * Saat volume pekerjaan berubah, target setiap periode diskalakan proporsional
     * sehingga pola rencana yang disusun Admin tetap terjaga.
     */
    public function scaleToVolume(WorkItem $item, float $volumeLama): void
    {
        if ($volumeLama <= 0) {
            $this->distributeEvenly($item);

            return;
        }

        DB::transaction(function () use ($item, $volumeLama) {
            $plans = $item->workPlans()->join('periods', 'periods.id', '=', 'work_plans.period_id')
                ->orderBy('periods.urutan')
                ->get(['work_plans.*']);

            if ($plans->isEmpty()) {
                return;
            }

            $volumeBaru = (float) $item->volume;
            $totalLama = (float) $plans->sum('target_volume');
            $penuh = abs($totalLama - $volumeLama) < self::TOLERANSI;
            $faktor = $volumeBaru / $volumeLama;
            $total = 0.0;

            foreach ($plans->values() as $index => $plan) {
                $target = round((float) $plan->target_volume * $faktor, self::DESIMAL);

                // Rencana yang sebelumnya penuh tetap penuh (sisa 0) tanpa selisih pembulatan.
                if ($penuh && $index === $plans->count() - 1) {
                    $target = round($volumeBaru - $total, self::DESIMAL);
                }

                $total += $target;
                $this->simpanTarget($item, $plan->period_id, $target, $plan->catatan);
            }
        });
    }

    /** Matriks rencana: pekerjaan x periode, siap dipakai tabel rencana di frontend. */
    public function matrix(Project $project): array
    {
        $periods = $project->periods()->orderBy('urutan')->get();
        $items = $project->workItems()->with(['unit', 'category', 'periodMulai', 'periodSelesai'])->orderBy('urutan')->get();
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
                'period_mulai_id' => $item->period_mulai_id,
                'period_selesai_id' => $item->period_selesai_id,
                'periode_mulai' => $item->periodMulai?->nama_periode,
                'periode_selesai' => $item->periodSelesai?->nama_periode,
                'periode' => $periods->map(fn (Period $p) => [
                    'period_id' => $p->id,
                    'aktif' => $this->periodeAktif($item, $p),
                    'target_volume' => (float) ($planItem[$p->id]->target_volume ?? 0),
                    'target_persentase' => (float) ($planItem[$p->id]->target_persentase ?? 0),
                    'target_bobot' => (float) ($planItem[$p->id]->target_bobot ?? 0),
                ])->all(),
                'total_target_volume' => round((float) $planItem->sum('target_volume'), self::DESIMAL),
                'total_target_bobot' => round((float) $planItem->sum('target_bobot'), 4),
                'sisa_volume' => round((float) $item->volume - (float) $planItem->sum('target_volume'), self::DESIMAL),
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
            'total_bobot_pekerjaan' => round((float) $items->sum('bobot'), 4),
            'total_bobot_rencana' => round((float) $plans->sum('target_bobot'), 4),
        ];
    }

    /** Periode aktif = di antara Periode Mulai dan Periode Selesai pekerjaan (inklusif). */
    public function periodeAktif(WorkItem $item, Period $period): bool
    {
        $mulai = $item->periodMulai?->urutan ?? 1;
        $selesai = $item->periodSelesai?->urutan ?? PHP_INT_MAX;

        return $period->urutan >= $mulai && $period->urutan <= $selesai;
    }

    private function labelRentang(WorkItem $item): string
    {
        return ($item->periodMulai?->nama_periode ?? 'awal').' s/d '.($item->periodSelesai?->nama_periode ?? 'akhir');
    }

    private function simpanTarget(WorkItem $item, int $periodId, float $targetVolume, ?string $catatan = null): void
    {
        if ($targetVolume <= 0) {
            WorkPlan::where('work_item_id', $item->id)->where('period_id', $periodId)->delete();

            return;
        }

        $nilai = $this->weights->planValues($item, $targetVolume);

        WorkPlan::updateOrCreate(
            ['work_item_id' => $item->id, 'period_id' => $periodId],
            [
                'project_id' => $item->project_id,
                'target_volume' => $targetVolume,
                'target_persentase' => $nilai['target_persentase'],
                'target_bobot' => $nilai['target_bobot'],
                'catatan' => $catatan,
            ]
        );
    }
}
