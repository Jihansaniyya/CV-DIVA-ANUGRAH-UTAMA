<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncWorkPlanRequest;
use App\Models\Project;
use App\Services\WorkPlanService;
use Illuminate\Http\JsonResponse;

class WorkPlanController extends Controller
{
    public function __construct(private readonly WorkPlanService $plans) {}

    public function index(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json(['data' => $this->plans->matrix($project)]);
    }

    public function sync(SyncWorkPlanRequest $request, Project $project): JsonResponse
    {
        $this->plans->sync($project, $request->validated('rows'));

        return response()->json([
            'message' => 'Rencana pekerjaan berhasil disimpan.',
            'data' => $this->plans->matrix($project),
        ]);
    }

    /** Isi rencana pekerjaan yang masih kosong dengan membagi volumenya rata ke periode aktif. */
    public function distribute(Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $jumlah = $this->plans->distributeEmpty($project);

        return response()->json([
            'message' => $jumlah > 0
                ? "Rencana {$jumlah} pekerjaan berhasil dibagi rata ke periode aktif."
                : 'Semua pekerjaan sudah memiliki rencana; tidak ada yang diubah.',
            'data' => $this->plans->matrix($project),
        ]);
    }
}
