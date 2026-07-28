<?php

use Illuminate\Support\Facades\Route;
use Modules\Payments\Http\Controllers\PaymentsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('payments', [PaymentsController::class, 'index'])->middleware('permission:payments.view')->name('payments.index');
    Route::post('payments', [PaymentsController::class, 'store'])->middleware('permission:payments.create')->name('payments.store');
    Route::get('payments/{payment}', [PaymentsController::class, 'show'])->middleware('permission:payments.view')->name('payments.show');
    Route::post('payments/{payment}/receipt', [PaymentsController::class, 'uploadReceipt'])->middleware('permission:payments.edit')->name('payments.receipt');
    Route::post('payments/{payment}/verify', [PaymentsController::class, 'verify'])->middleware('permission:payments.verify')->name('payments.verify');
    Route::post('payments/{payment}/reject', [PaymentsController::class, 'reject'])->middleware('permission:payments.verify')->name('payments.reject');
    Route::put('payments/{payment}', [PaymentsController::class, 'update'])->middleware('permission:payments.edit')->name('payments.update');
    Route::patch('payments/{payment}', [PaymentsController::class, 'update'])->middleware('permission:payments.edit');
    Route::delete('payments/{payment}', [PaymentsController::class, 'destroy'])->middleware('permission:payments.delete')->name('payments.destroy');
});
