<?php

use Illuminate\Support\Facades\Route;
use Modules\AuditLogs\Http\Controllers\AuditLogsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('auditlogs', AuditLogsController::class)->names('auditlogs');
});
