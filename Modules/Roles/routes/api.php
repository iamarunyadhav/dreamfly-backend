<?php

use Illuminate\Support\Facades\Route;
use Modules\Roles\Http\Controllers\PermissionsController;
use Modules\Roles\Http\Controllers\RolesController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('permissions', [PermissionsController::class, 'index'])
        ->middleware('permission:roles.view')
        ->name('permissions.index');

    Route::get('roles', [RolesController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
    Route::post('roles', [RolesController::class, 'store'])->middleware('permission:roles.create')->name('roles.store');
    Route::get('roles/{role}', [RolesController::class, 'show'])->middleware('permission:roles.view')->name('roles.show');
    Route::put('roles/{role}', [RolesController::class, 'update'])->middleware('permission:roles.edit')->name('roles.update');
    Route::patch('roles/{role}', [RolesController::class, 'update'])->middleware('permission:roles.edit');
    Route::delete('roles/{role}', [RolesController::class, 'destroy'])->middleware('permission:roles.delete')->name('roles.destroy');
});
