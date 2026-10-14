<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkItemBatchRequest;
use App\Http\Requests\StoreWorkItemRequest;
use App\Http\Requests\UpdateWorkItemRequest;
use App\Http\Resources\WorkItemResource;
use App\Models\Project;
use App\Models\WorkItem;
use App\Services\CurveSService;
use App\Services\ProgressService;
use App\Services\WeightCalculatorService;
use App\Services\WorkItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkItemController extends Controller
{
    private const RELASI = ['unit', 'category', 'periodMulai', 'periodSelesai'];

    public function __construct(
        private readonly WeightCalculatorService $weights,
        private readonly WorkItemService $workItems,
        private readonly CurveSService $curve,
        private readonly ProgressService $progress,
    ) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $items = $project->workItems()->with(self::RELASI)->orderBy('urutan')->get();

        // Realisasi = laporan terkirim; sisa volume = volume - (terkirim + draf QS aktif), sama dengan validasi backend.
        $realisasi = $this->curve->actualVolumeByWorkItem($project);
        $terpakai = $this->progress->volumeTerpakai($items->pluck('id'));

        $items->each(function (WorkItem $item) use ($realisasi, $terpakai) {
            $volume = (float) $item->volume;
            $volRealisasi = round((float) ($realisasi[$item->id]->volume ?? 0), 3);
            $item->volume_realisasi = $volRealisasi;
            $item->persentase_realisasi = $volume > 0 ? round($volRealisasi / $volume * 100, 2) : 0.0;
            $item->sisa_volume = max(round($volume - (float) ($terpakai[$item->id] ?? 0), 3), 0.0);
        });

        return response()->json([
            'data' => WorkItemResource::collection($items),
            'meta' => [
                'total_bobot' => round((float) $items->sum('bobot'), 4),
                'memakai_harga' => $items->contains(fn (WorkItem $i) => $i->harga_satuan !== null),
                'total_harga_pekerjaan' => round((float) $items->sum('harga_pekerjaan'), 2),
            ],
        ]);
    }

    public function store(StoreWorkItemRequest $request, Project $project): JsonResponse
    {
        $item = $this->workItems->create($project, $request->validated());

        return response()->json([
            'message' => 'Pekerjaan berhasil ditambahkan. Target volume awal dibagi rata ke periode aktif.',
            'data' => new WorkItemResource($item),
        ], 201);
    }

    /** Simpan kelompok pekerjaan (baru/yang sudah ada) beserta daftar pekerjaannya sekaligus. */
    public function storeBatch(StoreWorkItemBatchRequest $request, Project $project): JsonResponse
    {
        $items = $this->workItems->createGroup(
            $project,
            $request->validated('items'),
            $request->validated('work_category_id'),
            $request->validated('kategori_baru'),
        );

        return response()->json([
            'message' => $items->count().' pekerjaan berhasil ditambahkan. Target volume awal dibagi rata ke periode aktif.',
            'data' => WorkItemResource::collection($items),
        ], 201);
    }

    public function show(Project $project, WorkItem $workItem): JsonResponse
    {
        $this->authorize('view', $project);
        abort_unless($workItem->project_id === $project->id, 404);

        return response()->json(['data' => new WorkItemResource($workItem->load(self::RELASI))]);
    }

    public function update(UpdateWorkItemRequest $request, Project $project, WorkItem $workItem): JsonResponse
    {
        abort_unless($workItem->project_id === $project->id, 404);

        $item = $this->workItems->update($workItem, $request->validated());

        return response()->json([
            'message' => 'Pekerjaan berhasil diperbarui.',
            'data' => new WorkItemResource($item),
        ]);
    }

    public function destroy(Project $project, WorkItem $workItem): JsonResponse
    {
        $this->authorize('update', $project);
        abort_unless($workItem->project_id === $project->id, 404);

        if ($workItem->progressDetails()->exists()) {
            return response()->json([
                'message' => 'Pekerjaan tidak dapat dihapus karena sudah memiliki laporan progres.',
            ], 422);
        }

        $workItem->delete();
        $this->weights->recalculateProject($project);

        return response()->json(['message' => 'Pekerjaan berhasil dihapus.']);
    }
}
