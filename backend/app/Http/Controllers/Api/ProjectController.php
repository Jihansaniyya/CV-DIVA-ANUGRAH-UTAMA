<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\CurveSService;
use App\Services\ProjectScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectScheduleService $schedule,
        private readonly CurveSService $curve,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Project::class);

        $projects = Project::visibleTo($request->user())
            ->with('qs')
            ->withCount(['workItems', 'periods'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%'.$request->string('q').'%';
                $query->where(fn ($sub) => $sub->where('nama_proyek', 'like', $q)
                    ->orWhere('nomor_spk', 'like', $q)
                    ->orWhere('lokasi', 'like', $q));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('lokasi'), fn ($query) => $query->where('lokasi', 'like', '%'.$request->string('lokasi').'%'))
            ->when($request->filled('tahun_anggaran'), fn ($query) => $query->where('tahun_anggaran', $request->integer('tahun_anggaran')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        // Progres aktual & rencana dihitung dari data laporan dan rencana pekerjaan.
        $projects->getCollection()->transform(function (Project $project) {
            $aktual = $this->curve->totalActual($project);
            $rencana = $this->curve->plannedToDate($project);
            $project->progres_aktual = $aktual;
            $project->progres_rencana = $rencana;
            $project->deviasi = round($aktual - $rencana, 4);

            return $project;
        });

        return ProjectResource::collection($projects);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $project = Project::create($data);

        if ($request->boolean('generate_periode', true)) {
            $this->schedule->generateWeeklyPeriods($project);
        }

        $this->sinkronkanPenugasanQs($project);

        return response()->json([
            'message' => 'Proyek berhasil ditambahkan.',
            'data' => new ProjectResource($project->fresh(['qs'])),
        ], 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $project->load(['qs', 'creator'])->loadCount(['workItems', 'periods']);
        $aktual = $this->curve->totalActual($project);
        $rencana = $this->curve->plannedToDate($project);
        $project->progres_aktual = $aktual;
        $project->progres_rencana = $rencana;
        $project->deviasi = round($aktual - $rencana, 4);

        return response()->json(['data' => new ProjectResource($project)]);
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $tanggalBerubah = $request->filled('tanggal_mulai') || $request->filled('tanggal_selesai');

        $project->update($request->validated());

        if ($tanggalBerubah) {
            $this->schedule->generateWeeklyPeriods($project);
        }

        $this->sinkronkanPenugasanQs($project);

        return response()->json([
            'message' => 'Proyek berhasil diperbarui.',
            'data' => new ProjectResource($project->fresh(['qs'])),
        ]);
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return response()->json(['message' => 'Proyek berhasil dihapus.']);
    }

    /** Buat ulang periode mingguan proyek. */
    public function generatePeriods(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $dibuat = $this->schedule->generateWeeklyPeriods($project, $request->boolean('force'));

        return response()->json([
            'message' => 'Periode pelaksanaan berhasil dibentuk ('.$dibuat.' periode baru).',
        ]);
    }

    /** QS penanggung jawab utama juga dicatat pada tabel penugasan. */
    private function sinkronkanPenugasanQs(Project $project): void
    {
        if (! $project->qs_user_id) {
            return;
        }

        $project->assignments()->updateOrCreate(
            ['user_id' => $project->qs_user_id],
            ['peran' => 'QS', 'is_primary' => true]
        );
    }
}
