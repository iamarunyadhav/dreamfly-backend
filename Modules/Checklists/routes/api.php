<?php

use Illuminate\Support\Facades\Route;
use Modules\Checklists\Http\Controllers\ChecklistCategoriesController;
use Modules\Checklists\Http\Controllers\ChecklistsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    // Checklist categories (library taxonomy).
    Route::get('checklist-categories', [ChecklistCategoriesController::class, 'index'])->middleware('permission:checklists.view')->name('checklist-categories.index');
    Route::post('checklist-categories', [ChecklistCategoriesController::class, 'store'])->middleware('permission:checklists.create')->name('checklist-categories.store');
    Route::put('checklist-categories/{checklistCategory}', [ChecklistCategoriesController::class, 'update'])->middleware('permission:checklists.edit')->name('checklist-categories.update');
    Route::patch('checklist-categories/{checklistCategory}', [ChecklistCategoriesController::class, 'update'])->middleware('permission:checklists.edit');
    Route::delete('checklist-categories/{checklistCategory}', [ChecklistCategoriesController::class, 'destroy'])->middleware('permission:checklists.delete')->name('checklist-categories.destroy');

    Route::get('checklists', [ChecklistsController::class, 'index'])->middleware('permission:checklists.view')->name('checklists.index');
    Route::post('checklists', [ChecklistsController::class, 'store'])->middleware('permission:checklists.create')->name('checklists.store');
    Route::get('checklists/{checklist}', [ChecklistsController::class, 'show'])->middleware('permission:checklists.view')->name('checklists.show');
    Route::get('checklists/{checklist}/versions', [ChecklistsController::class, 'versions'])->middleware('permission:checklists.view')->name('checklists.versions');
    Route::post('checklists/{checklist}/publish', [ChecklistsController::class, 'publish'])->middleware('permission:checklists.edit')->name('checklists.publish');
    Route::post('checklists/{checklist}/restore', [ChecklistsController::class, 'restore'])->middleware('permission:checklists.edit')->name('checklists.restore');
    Route::put('checklists/{checklist}', [ChecklistsController::class, 'update'])->middleware('permission:checklists.edit')->name('checklists.update');
    Route::patch('checklists/{checklist}', [ChecklistsController::class, 'update'])->middleware('permission:checklists.edit');
    Route::delete('checklists/{checklist}', [ChecklistsController::class, 'destroy'])->middleware('permission:checklists.delete')->name('checklists.destroy');
});
