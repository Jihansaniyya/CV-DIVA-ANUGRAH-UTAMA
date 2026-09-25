<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkItemRequest;
use App\Http\Requests\UpdateWorkItemRequest;
use App\Http\Resources\WorkItemResource;
use App\Models\Project;
use App\Models\WorkItem;
use App\Services\WeightCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkItemController extends Controller
{
    public function __construct(private readonly WeightCalculatorService $weights) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $items = $project->workItems()->with(['unit', 'category'])->orderBy('urutan')->get();

        $items->each(function (WorkItem $item) {
            $realisasi = $item->volumeRealisasi();
            $item->volume_realisasi = $realisasi;
            $item->persentase_realisasi = (float) $item->volume > 0
                ? round($realisasi / (float) $item->volume * 100, 2)
                : 0.0;
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
        $data = $request->validated();
        $data['urutan'] = $data['urutan'] ?? ((int) $project->workItems()->max('urutan') + 1);

        $item = $project->workItems()->create($data);
        $this->weights->recalculateProject($project);

        return response()->json([
            'message' => 'Pekerjaan berhasil ditambahkan.',
            'data' => new WorkItemResource($item->fresh(['unit', 'category'])),
        ], 201);
    }

    public function show(Project $project, WorkItem $workItem): JsonResponse
    {
        $this->authorize('view', $project);
        abort_unless($workItem->project_id === $project->id, 404);

        return response()->json(['data' => new WorkItemResource($workItem->load(['unit', 'category']))]);
    }

    public function update(UpdateWorkItemRequest $request, Project $project, WorkItem $workItem): JsonResponse
    {
        abort_unless($workItem->project_id === $project->id, 404);

        $workItem->update($request->validated());
        $this->weights->recalculateProject($project);

        return response()->json([
            'message' => 'Pekerjaan berhasil diperbarui.',
            'data' => new WorkItemResource($workItem->fresh(['unit', 'category'])),
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
