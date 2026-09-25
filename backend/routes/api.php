<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CurveSController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MasterDataController;
use App\Http\Controllers\Api\MilestoneController;
use App\Http\Controllers\Api\PeriodController;
use App\Http\Controllers\Api\ProgressReportController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WorkCategoryController;
use App\Http\Controllers\Api\WorkItemController;
use App\Http\Controllers\Api\WorkPlanController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware(['auth:sanctum', 'role'])->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('dashboard', [DashboardController::class, 'index']);

    // Master data
    Route::get('roles', [MasterDataController::class, 'roles']);
    Route::get('units', [MasterDataController::class, 'units']);
    Route::post('units', [MasterDataController::class, 'storeUnit'])->middleware('role:ADMIN');

    // Pengguna (Admin)
    Route::middleware('role:ADMIN')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive']);
    });

    // Proyek
    Route::get('projects', [ProjectController::class, 'index']);
    Route::get('projects/{project}', [ProjectController::class, 'show']);
    Route::middleware('role:ADMIN')->group(function () {
        Route::post('projects', [ProjectController::class, 'store']);
        Route::put('projects/{project}', [ProjectController::class, 'update']);
        Route::delete('projects/{project}', [ProjectController::class, 'destroy']);
        Route::post('projects/{project}/generate-periods', [ProjectController::class, 'generatePeriods']);
    });

    // Kelompok pekerjaan
    Route::get('projects/{project}/work-categories', [WorkCategoryController::class, 'index']);
    Route::middleware('role:ADMIN')->group(function () {
        Route::post('projects/{project}/work-categories', [WorkCategoryController::class, 'store']);
        Route::put('projects/{project}/work-categories/{workCategory}', [WorkCategoryController::class, 'update']);
        Route::delete('projects/{project}/work-categories/{workCategory}', [WorkCategoryController::class, 'destroy']);
    });

    // Pekerjaan
    Route::get('projects/{project}/work-items', [WorkItemController::class, 'index']);
    Route::get('projects/{project}/work-items/{workItem}', [WorkItemController::class, 'show']);
    Route::middleware('role:ADMIN')->group(function () {
        Route::post('projects/{project}/work-items', [WorkItemController::class, 'store']);
        Route::put('projects/{project}/work-items/{workItem}', [WorkItemController::class, 'update']);
        Route::delete('projects/{project}/work-items/{workItem}', [WorkItemController::class, 'destroy']);
    });

    // Periode pelaksanaan
    Route::get('projects/{project}/periods', [PeriodController::class, 'index']);
    Route::middleware('role:ADMIN')->group(function () {
        Route::post('projects/{project}/periods', [PeriodController::class, 'store']);
        Route::delete('projects/{project}/periods/{period}', [PeriodController::class, 'destroy']);
    });

    // Rencana pekerjaan
    Route::get('projects/{project}/work-plans', [WorkPlanController::class, 'index']);
    Route::post('projects/{project}/work-plans', [WorkPlanController::class, 'sync'])->middleware('role:ADMIN');

    // Milestone
    Route::get('projects/{project}/milestones', [MilestoneController::class, 'index']);
    Route::middleware('role:ADMIN')->group(function () {
        Route::post('projects/{project}/milestones', [MilestoneController::class, 'store']);
        Route::put('projects/{project}/milestones/{milestone}', [MilestoneController::class, 'update']);
        Route::delete('projects/{project}/milestones/{milestone}', [MilestoneController::class, 'destroy']);
    });

    // Kurva S
    Route::get('projects/{project}/curve-s', [CurveSController::class, 'show'])->middleware('role:ADMIN,KONTRAKTOR');

    // Progres
    Route::get('progress', [ProgressReportController::class, 'index']);
    Route::get('progress/{progress}', [ProgressReportController::class, 'show']);
    Route::middleware('role:ADMIN,QS')->group(function () {
        Route::post('progress', [ProgressReportController::class, 'store']);
        Route::post('progress/{progress}', [ProgressReportController::class, 'update']);
        Route::patch('progress/{progress}/submit', [ProgressReportController::class, 'submit']);
        Route::delete('progress/{progress}', [ProgressReportController::class, 'destroy']);
        Route::delete('progress-photos/{photo}', [ProgressReportController::class, 'destroyPhoto']);
    });

    // Laporan (QS hanya menginput progres, tidak mengakses laporan)
    Route::prefix('reports')->middleware('role:ADMIN,KONTRAKTOR')->group(function () {
        Route::get('daily', [ReportController::class, 'daily']);
        Route::get('weekly', [ReportController::class, 'weekly']);
        Route::get('monthly', [ReportController::class, 'monthly']);
        Route::get('milestone', [ReportController::class, 'milestone']);
        Route::get('documents', [ReportController::class, 'documents']);
        Route::post('export/excel', [ReportController::class, 'exportExcel']);
        Route::post('export/word', [ReportController::class, 'exportWord']);
    });
});
