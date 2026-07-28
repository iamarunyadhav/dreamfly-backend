<?php

use Illuminate\Support\Facades\Route;
use Modules\Forms\Http\Controllers\FormsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('forms', [FormsController::class, 'index'])->middleware('permission:forms.view')->name('forms.index');
    Route::post('forms', [FormsController::class, 'store'])->middleware('permission:forms.create')->name('forms.store');
    Route::get('forms/{form}', [FormsController::class, 'show'])->middleware('permission:forms.view')->name('forms.show');
    Route::put('forms/{form}', [FormsController::class, 'update'])->middleware('permission:forms.edit')->name('forms.update');
    Route::patch('forms/{form}', [FormsController::class, 'update'])->middleware('permission:forms.edit');
    Route::delete('forms/{form}', [FormsController::class, 'destroy'])->middleware('permission:forms.delete')->name('forms.destroy');
});
