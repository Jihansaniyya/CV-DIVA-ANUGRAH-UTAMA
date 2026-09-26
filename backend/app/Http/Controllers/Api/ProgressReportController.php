<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProgressReportRequest;
use App\Http\Requests\UpdateProgressReportRequest;
use App\Http\Resources\ProgressReportResource;
use App\Models\ProgressPhoto;
use App\Models\ProgressReport;
use App\Models\Project;
use App\Services\ProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProgressReportController extends Controller
{
    private const RELASI = ['project', 'user', 'period', 'details.workItem.unit', 'photos', 'issues.workItem'];

    public function __construct(private readonly ProgressService $progress) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ProgressReport::class);

        $user = $request->user();

        $reports = ProgressReport::with(self::RELASI)
            ->whereHas('project', fn ($q) => $q->visibleTo($user))
            ->when($user->isQs(), fn ($q) => $q->where('user_id', $user->id))
            ->when($user->isKontraktor(), fn ($q) => $q->where('status', ReportStatus::DIKIRIM->value))
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->filled('period_id'), fn ($q) => $q->where('period_id', $request->integer('period_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('dari'), fn ($q) => $q->whereDate('tanggal_laporan', '>=', $request->string('dari')))
            ->when($request->filled('sampai'), fn ($q) => $q->whereDate('tanggal_laporan', '<=', $request->string('sampai')))
            ->orderByDesc('tanggal_laporan')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return ProgressReportResource::collection($reports);
    }

    public function store(StoreProgressReportRequest $request): JsonResponse
    {
        $project = Project::findOrFail($request->integer('project_id'));

        $report = $this->progress->create(
            $project,
            $request->user(),
            $request->validated(),
            $request->file('photos', [])
        );

        return response()->json([
            'message' => 'Laporan progres berhasil disimpan.',
            'data' => new ProgressReportResource($report->load(self::RELASI)),
        ], 201);
    }

    public function show(ProgressReport $progress): JsonResponse
    {
        $this->authorize('view', $progress);

        return response()->json([
            'data' => new ProgressReportResource($progress->load(self::RELASI)),
        ]);
    }

    public function update(UpdateProgressReportRequest $request, ProgressReport $progress): JsonResponse
    {
        $this->authorize('update', $progress);

        $report = $this->progress->update($progress, $request->validated(), $request->file('photos', []));

        return response()->json([
            'message' => 'Laporan progres berhasil diperbarui.',
            'data' => new ProgressReportResource($report->load(self::RELASI)),
        ]);
    }

    public function destroy(ProgressReport $progress): JsonResponse
    {
        $this->authorize('delete', $progress);

        $this->progress->deleteReport($progress);

        return response()->json(['message' => 'Laporan progres berhasil dihapus.']);
    }

    /** Kirim laporan sehingga ikut terhitung pada progres aktual dan Kurva S. */
    public function submit(ProgressReport $progress): JsonResponse
    {
        $this->authorize('submit', $progress);

        $report = $this->progress->submit($progress);

        return response()->json([
            'message' => 'Laporan progres berhasil dikirim.',
            'data' => new ProgressReportResource($report->load(self::RELASI)),
        ]);
    }

    public function destroyPhoto(ProgressPhoto $photo): JsonResponse
    {
        $this->authorize('update', $photo->report);

        $this->progress->deletePhoto($photo);

        return response()->json(['message' => 'Foto berhasil dihapus.']);
    }
}
