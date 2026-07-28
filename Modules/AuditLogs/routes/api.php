<?php

use Illuminate\Support\Facades\Route;
use Modules\AuditLogs\Http\Controllers\AuditLogsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('audit-logs', [AuditLogsController::class, 'index'])
        ->middleware('permission:audit-logs.view')
        ->name('audit-logs.index');
});
