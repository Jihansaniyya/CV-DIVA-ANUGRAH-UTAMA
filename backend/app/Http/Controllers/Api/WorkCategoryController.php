<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkCategoryRequest;
use App\Http\Resources\WorkCategoryResource;
use App\Models\Project;
use App\Models\WorkCategory;
use Illuminate\Http\JsonResponse;

class WorkCategoryController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json([
            'data' => WorkCategoryResource::collection($project->workCategories()->get()),
        ]);
    }

    public function store(StoreWorkCategoryRequest $request, Project $project): JsonResponse
    {
        $data = $request->validated();
        $data['urutan'] = $data['urutan'] ?? ((int) $project->workCategories()->max('urutan') + 1);

        $category = $project->workCategories()->create($data);

        return response()->json([
            'message' => 'Kelompok pekerjaan berhasil ditambahkan.',
            'data' => new WorkCategoryResource($category),
        ], 201);
    }

    public function update(StoreWorkCategoryRequest $request, Project $project, WorkCategory $workCategory): JsonResponse
    {
        abort_unless($workCategory->project_id === $project->id, 404);

        $workCategory->update($request->validated());

        return response()->json([
            'message' => 'Kelompok pekerjaan berhasil diperbarui.',
            'data' => new WorkCategoryResource($workCategory),
        ]);
    }

    public function destroy(Project $project, WorkCategory $workCategory): JsonResponse
    {
        $this->authorize('update', $project);
        abort_unless($workCategory->project_id === $project->id, 404);

        $workCategory->delete();

        return response()->json(['message' => 'Kelompok pekerjaan berhasil dihapus.']);
    }
}
