<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PeriodResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

/**
 * Periode pelaksanaan hanya dapat dibaca. Periode dibentuk dan disinkronkan
 * otomatis oleh ProjectScheduleService setiap kali tanggal proyek berubah.
 */
class PeriodController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json([
            'data' => PeriodResource::collection($project->periods()->orderBy('urutan')->get()),
        ]);
    }
}
