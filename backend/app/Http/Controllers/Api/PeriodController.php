<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PeriodResource;
use App\Models\Period;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeriodController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json([
            'data' => PeriodResource::collection($project->periods()->get()),
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'nama_periode' => ['required', 'string', 'max:60'],
            'urutan' => ['required', 'integer', 'min:1'],
            'bulan_ke' => ['required', 'integer', 'min:1'],
            'minggu_ke' => ['required', 'integer', 'min:1'],
            'minggu_ke_bulan' => ['required', 'integer', 'min:1'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        $period = $project->periods()->create($data);

        return response()->json([
            'message' => 'Periode berhasil ditambahkan.',
            'data' => new PeriodResource($period),
        ], 201);
    }

    public function destroy(Project $project, Period $period): JsonResponse
    {
        $this->authorize('update', $project);
        abort_unless($period->project_id === $project->id, 404);

        if ($period->workPlans()->exists() || $period->progressReports()->exists()) {
            return response()->json([
                'message' => 'Periode tidak dapat dihapus karena sudah dipakai rencana atau laporan progres.',
            ], 422);
        }

        $period->delete();

        return response()->json(['message' => 'Periode berhasil dihapus.']);
    }
}
