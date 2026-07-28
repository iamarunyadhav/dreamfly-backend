<?php

use Illuminate\Support\Facades\Route;
use Modules\Services\Http\Controllers\ServicesController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('services', [ServicesController::class, 'index'])->middleware('permission:services.view')->name('services.index');
    Route::post('services', [ServicesController::class, 'store'])->middleware('permission:services.create')->name('services.store');
    Route::get('services/{service}', [ServicesController::class, 'show'])->middleware('permission:services.view')->name('services.show');
    Route::put('services/{service}', [ServicesController::class, 'update'])->middleware('permission:services.edit')->name('services.update');
    Route::patch('services/{service}', [ServicesController::class, 'update'])->middleware('permission:services.edit');
    Route::delete('services/{service}', [ServicesController::class, 'destroy'])->middleware('permission:services.delete')->name('services.destroy');
});
