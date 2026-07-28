<?php

use Illuminate\Support\Facades\Route;
use Modules\System\Http\Controllers\NotificationsController;
use Modules\System\Http\Controllers\SystemController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('system/settings', [SystemController::class, 'index'])->middleware('permission:system.view')->name('system.settings.index');
    Route::put('system/settings', [SystemController::class, 'update'])->middleware('permission:system.edit')->name('system.settings.update');

    Route::get('notifications', [NotificationsController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/read-all', [NotificationsController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('notifications/{notification}/read', [NotificationsController::class, 'markRead'])->name('notifications.read');
});
