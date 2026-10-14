<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\CurveSService;
use App\Services\ProjectAssignmentService;
use App\Services\ProjectScheduleService;
use App\Services\ProjectStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectScheduleService $schedule,
        private readonly CurveSService $curve,
        private readonly ProjectStatusService $status,
        private readonly ProjectAssignmentService $assignments,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Project::class);
        $this->status->sinkronkanHarian();

        $projects = Project::visibleTo($request->user())
            ->with('qs')
            ->withCount(['workItems', 'periods'])
            ->withSum('workPlans as total_bobot_rencana', 'target_bobot')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%'.$request->string('q').'%';
                $query->where(fn ($sub) => $sub->where('nama_proyek', 'like', $q)
                    ->orWhere('nomor_spk', 'like', $q)
                    ->orWhere('lokasi', 'like', $q));
            })
            ->when($request->filled('id'), fn ($query) => $query->whereKey($request->integer('id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('lokasi'), fn ($query) => $query->where('lokasi', 'like', '%'.$request->string('lokasi').'%'))
            ->when($request->filled('tahun_anggaran'), fn ($query) => $query->where('tahun_anggaran', $request->integer('tahun_anggaran')))
            ->terbaru()
            ->paginate($this->perPage($request))
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

    /**
     * Seluruh proyek yang boleh diakses, tanpa paginasi, untuk pilihan dropdown. Hanya kolom ringan
     * (tanpa perhitungan progres per proyek) sehingga tetap cepat walau jumlah proyek lebih dari 100.
     */
    public function options(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Project::class);

        $projects = Project::visibleTo($request->user())
            ->withSum('workPlans as total_bobot_rencana', 'target_bobot')
            ->terbaru()
            ->get();

        return response()->json(['data' => ProjectResource::collection($projects)]);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $project = Project::create($data);

        // Periode mingguan (M-I, M-II, ...) selalu dibentuk dari durasi proyek.
        $this->schedule->generateWeeklyPeriods($project);
        $this->status->sinkronkan($project);

        $this->assignments->sinkronkanQs($project);

        return response()->json([
            'message' => 'Proyek berhasil ditambahkan.',
            'data' => new ProjectResource($project->fresh(['qs'])),
        ], 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $project->load(['qs', 'creator'])->loadCount(['workItems', 'periods'])
            ->loadSum('workPlans as total_bobot_rencana', 'target_bobot');
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

        // Durasi berubah -> periode, rentang pekerjaan, rencana, dan progres ikut disinkronkan.
        if ($tanggalBerubah) {
            $this->schedule->generateWeeklyPeriods($project);
        }

        $this->status->sinkronkan($project);

        $this->assignments->sinkronkanQs($project);

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
}
