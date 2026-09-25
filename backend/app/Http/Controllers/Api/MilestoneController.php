<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMilestoneRequest;
use App\Http\Resources\MilestoneResource;
use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class MilestoneController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json([
            'data' => MilestoneResource::collection($project->milestones()->with('period')->get()),
        ]);
    }

    public function store(StoreMilestoneRequest $request, Project $project): JsonResponse
    {
        $milestone = $project->milestones()->create($request->validated());

        return response()->json([
            'message' => 'Milestone berhasil ditambahkan.',
            'data' => new MilestoneResource($milestone->load('period')),
        ], 201);
    }

    public function update(StoreMilestoneRequest $request, Project $project, Milestone $milestone): JsonResponse
    {
        abort_unless($milestone->project_id === $project->id, 404);

        $milestone->update($request->validated());

        return response()->json([
            'message' => 'Milestone berhasil diperbarui.',
            'data' => new MilestoneResource($milestone->load('period')),
        ]);
    }

    public function destroy(Project $project, Milestone $milestone): JsonResponse
    {
        $this->authorize('update', $project);
        abort_unless($milestone->project_id === $project->id, 404);

        $milestone->delete();

        return response()->json(['message' => 'Milestone berhasil dihapus.']);
    }
}
