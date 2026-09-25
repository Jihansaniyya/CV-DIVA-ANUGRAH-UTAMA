<?php

namespace App\Services;

use App\Models\Project;
use App\Models\WorkItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Penyimpanan data pekerjaan beserta turunannya.
 *
 * Setiap pekerjaan baru langsung memperoleh rencana awal (volume dibagi rata ke
 * periode aktif) dan bobot seluruh pekerjaan proyek dihitung ulang.
 */
class WorkItemService
{
    public function __construct(
        private readonly WeightCalculatorService $weights,
        private readonly WorkPlanService $plans,
    ) {}

    /** Simpan satu pekerjaan. */
    public function create(Project $project, array $data): WorkItem
    {
        return $this->createGroup($project, [$data], $data['work_category_id'] ?? null)->first();
    }

    /**
     * Simpan kelompok pekerjaan (baru atau yang sudah ada) beserta daftar pekerjaannya
     * dalam satu transaksi.
     *
     * @param  array<int,array<string,mixed>>  $items
     * @param  array{kode?:string|null,nama:string}|null  $kategoriBaru
     * @return Collection<int,WorkItem>
     */
    public function createGroup(Project $project, array $items, ?int $categoryId = null, ?array $kategoriBaru = null): Collection
    {
        return DB::transaction(function () use ($project, $items, $categoryId, $kategoriBaru) {
            if ($kategoriBaru !== null) {
                $categoryId = $project->workCategories()->create([
                    'kode' => $kategoriBaru['kode'] ?? null,
                    'nama' => $kategoriBaru['nama'],
                    'urutan' => (int) $project->workCategories()->max('urutan') + 1,
                ])->id;
            }

            $urutan = (int) $project->workItems()->max('urutan');

            $dibuat = collect($items)->map(function (array $data) use ($project, $categoryId, &$urutan) {
                $item = $project->workItems()->create([
                    ...$data,
                    'work_category_id' => $categoryId,
                    'urutan' => $data['urutan'] ?? ++$urutan,
                ]);

                $this->plans->syncWithWorkItem($item);

                return $item;
            });

            $this->weights->recalculateProject($project);

            return $dibuat->map->fresh(['unit', 'category', 'periodMulai', 'periodSelesai']);
        });
    }

    /**
     * Perbarui pekerjaan. Perubahan Periode Mulai/Selesai membagi ulang target,
     * perubahan volume menskalakan target secara proporsional.
     */
    public function update(WorkItem $item, array $data): WorkItem
    {
        return DB::transaction(function () use ($item, $data) {
            $sebelum = [
                'period_mulai_id' => $item->period_mulai_id,
                'period_selesai_id' => $item->period_selesai_id,
                'volume' => (float) $item->volume,
            ];

            $item->update($data);
            $this->plans->syncWithWorkItem($item, $sebelum);
            $this->weights->recalculateProject($item->project);

            return $item->fresh(['unit', 'category', 'periodMulai', 'periodSelesai']);
        });
    }
}
