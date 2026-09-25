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
}
