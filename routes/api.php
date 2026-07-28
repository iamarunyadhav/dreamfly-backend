<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportsController;
use Illuminate\Support\Facades\Route;

// Most API routes are registered per-module (see Modules/*/routes/api.php and
// Modules/*/app/Providers/RouteServiceProvider.php), mounted under /api/v1/*.

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');

    Route::get('reports/overview', [ReportsController::class, 'overview'])
        ->middleware('permission:reports.view')
        ->name('reports.overview');
});
