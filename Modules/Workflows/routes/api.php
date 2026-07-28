<?php

use Illuminate\Support\Facades\Route;
use Modules\Workflows\Http\Controllers\CaseStepsController;
use Modules\Workflows\Http\Controllers\WorkflowsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    // Case runtime (per-client workflow execution). Gated on client permissions
    // because the operational staff who run cases hold clients.* not workflows.*.
    Route::get('clients/{client}/case-steps', [CaseStepsController::class, 'index'])->middleware('permission:clients.view')->name('clients.case-steps.index');
    Route::post('clients/{client}/case-steps/initialize', [CaseStepsController::class, 'initialize'])->middleware('permission:clients.edit')->name('clients.case-steps.initialize');
    Route::post('case-steps/{caseStep}/advance', [CaseStepsController::class, 'advance'])->middleware('permission:clients.edit')->name('case-steps.advance');
    Route::post('case-steps/{caseStep}/hold', [CaseStepsController::class, 'hold'])->middleware('permission:clients.edit')->name('case-steps.hold');
    Route::post('case-steps/{caseStep}/resume', [CaseStepsController::class, 'resume'])->middleware('permission:clients.edit')->name('case-steps.resume');
    Route::patch('case-steps/{caseStep}', [CaseStepsController::class, 'update'])->middleware('permission:clients.edit')->name('case-steps.update');

    Route::get('workflows', [WorkflowsController::class, 'index'])->middleware('permission:workflows.view')->name('workflows.index');
    Route::post('workflows', [WorkflowsController::class, 'store'])->middleware('permission:workflows.create')->name('workflows.store');
    Route::get('workflows/{workflow}', [WorkflowsController::class, 'show'])->middleware('permission:workflows.view')->name('workflows.show');
    Route::put('workflows/{workflow}', [WorkflowsController::class, 'update'])->middleware('permission:workflows.edit')->name('workflows.update');
    Route::patch('workflows/{workflow}', [WorkflowsController::class, 'update'])->middleware('permission:workflows.edit');
    Route::delete('workflows/{workflow}', [WorkflowsController::class, 'destroy'])->middleware('permission:workflows.delete')->name('workflows.destroy');
});
