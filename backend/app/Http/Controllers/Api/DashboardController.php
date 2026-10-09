<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Services\ProjectStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly ProjectStatusService $status,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->status->sinkronkanHarian();

        return response()->json(['data' => $this->dashboard->forUser($request->user())]);
    }
}
