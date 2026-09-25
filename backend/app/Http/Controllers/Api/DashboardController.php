<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->dashboard->forUser($request->user())]);
    }
}
