<?php

use Illuminate\Support\Facades\Route;
use Modules\Agreements\Http\Controllers\AgreementsController;

Route::middleware(['signed'])->prefix('v1')->group(function () {
    Route::get('agreements/default-video', [AgreementsController::class, 'defaultVideo'])->name('agreements.default-video');
});

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('agreements', [AgreementsController::class, 'index'])->middleware('permission:agreements.view')->name('agreements.index');
    Route::post('agreements', [AgreementsController::class, 'store'])->middleware('permission:agreements.create')->name('agreements.store');
    Route::get('agreements/{agreement}', [AgreementsController::class, 'show'])->middleware('permission:agreements.view')->name('agreements.show');
    Route::get('agreements/{agreement}/pdf', [AgreementsController::class, 'pdf'])->middleware('permission:agreements.view')->name('agreements.pdf');
    Route::post('agreements/{agreement}/generate', [AgreementsController::class, 'generate'])->middleware('permission:agreements.generate')->name('agreements.generate');
    Route::post('agreements/{agreement}/share', [AgreementsController::class, 'share'])->middleware('permission:agreements.share')->name('agreements.share');
    Route::post('agreements/{agreement}/signed-file', [AgreementsController::class, 'uploadSigned'])
        ->middleware('permission:agreements.edit')->name('agreements.signed-file');
});
