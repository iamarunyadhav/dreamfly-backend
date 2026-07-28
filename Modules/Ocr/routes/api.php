<?php

use Illuminate\Support\Facades\Route;
use Modules\Ocr\Http\Controllers\OcrExtractionsController;
use Modules\Ocr\Http\Controllers\OcrSettingsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('ocr/settings', [OcrSettingsController::class, 'show'])->middleware('permission:ocr.manage')->name('ocr.settings.show');
    Route::put('ocr/settings', [OcrSettingsController::class, 'update'])->middleware('permission:ocr.manage')->name('ocr.settings.update');

    Route::post('ocr/files/{file}/run', [OcrExtractionsController::class, 'run'])->middleware('permission:ocr.run')->name('ocr.run');
    Route::get('ocr/files/{file}/extraction', [OcrExtractionsController::class, 'show'])->middleware('permission:ocr.view')->name('ocr.extraction.show');
    Route::patch('ocr/extractions/{extraction}/fields/{field}', [OcrExtractionsController::class, 'updateField'])->middleware('permission:ocr.view')->name('ocr.extraction.fields.update');
    Route::post('ocr/extractions/{extraction}/pdf', [OcrExtractionsController::class, 'generatePdf'])->middleware('permission:ocr.view')->name('ocr.extraction.pdf');
});
