<?php

use Illuminate\Support\Facades\Route;
use Modules\Files\Http\Controllers\FilesController;

Route::middleware(['signed'])->prefix('v1')->group(function () {
    Route::get('files/{file}/signed-download', [FilesController::class, 'signedDownload'])->name('files.signed-download');
    Route::get('files/{file}/signed-preview', [FilesController::class, 'signedPreview'])->name('files.signed-preview');
    Route::get('files/{file}/signed-raw', [FilesController::class, 'signedRaw'])->name('files.signed-raw');
});

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('files', [FilesController::class, 'index'])->middleware('permission:files.view')->name('files.index');
    Route::post('files', [FilesController::class, 'store'])->middleware('permission:files.create')->name('files.store');
    Route::get('files/{file}/preview', [FilesController::class, 'preview'])->middleware('permission:files.view')->name('files.preview');
    Route::get('files/{file}/raw', [FilesController::class, 'raw'])->middleware('permission:files.view')->name('files.raw');
    Route::post('files/{file}/generate-pdf', [FilesController::class, 'generatePdf'])->middleware('permission:files.download')->name('files.generate-pdf');
    Route::post('files/{file}/share', [FilesController::class, 'share'])->middleware('permission:communications.send')->name('files.share');
    Route::get('files/{file}/download', [FilesController::class, 'download'])->middleware('permission:files.view')->name('files.download');
    Route::patch('files/{file}/verify', [FilesController::class, 'verify'])->middleware('permission:files.create')->name('files.verify');
    Route::patch('files/{file}/rename', [FilesController::class, 'rename'])->middleware('permission:files.create')->name('files.rename');
    Route::get('files/{file}/versions', [FilesController::class, 'versions'])->middleware('permission:files.view')->name('files.versions');
    Route::post('files/{file}/versions', [FilesController::class, 'storeVersion'])->middleware('permission:files.upload')->name('files.versions.store');
    Route::delete('files/{file}', [FilesController::class, 'destroy'])->middleware('permission:files.delete')->name('files.destroy');
});
