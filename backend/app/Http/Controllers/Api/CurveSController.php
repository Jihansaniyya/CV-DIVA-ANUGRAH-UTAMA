<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\CurveSService;
use Illuminate\Http\JsonResponse;

class CurveSController extends Controller
{
    public function __construct(private readonly CurveSService $curve) {}

    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json([
            'data' => array_merge(
                $this->curve->build($project),
                ['proyek' => ['id' => $project->id, 'nama_proyek' => $project->nama_proyek]]
            ),
        ]);
    }
}
