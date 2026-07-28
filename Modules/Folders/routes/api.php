<?php

use Illuminate\Support\Facades\Route;
use Modules\Folders\Http\Controllers\FoldersController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('folders', [FoldersController::class, 'index'])->middleware('permission:folders.view')->name('folders.index');
    Route::post('folders', [FoldersController::class, 'store'])->middleware('permission:folders.create')->name('folders.store');
    Route::get('folders/{folder}', [FoldersController::class, 'show'])->middleware('permission:folders.view')->name('folders.show');
    Route::post('folders/{folder}/propagate', [FoldersController::class, 'propagate'])->middleware('permission:folders.create')->name('folders.propagate');
    Route::put('folders/{folder}', [FoldersController::class, 'update'])->middleware('permission:folders.edit')->name('folders.update');
    Route::patch('folders/{folder}', [FoldersController::class, 'update'])->middleware('permission:folders.edit');
    Route::delete('folders/{folder}', [FoldersController::class, 'destroy'])->middleware('permission:folders.delete')->name('folders.destroy');
});
