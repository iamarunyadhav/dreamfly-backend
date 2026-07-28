<?php

use Illuminate\Support\Facades\Route;
use Modules\Invoices\Http\Controllers\InvoicesController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('invoices', [InvoicesController::class, 'index'])->middleware('permission:invoices.view')->name('invoices.index');
    Route::post('invoices', [InvoicesController::class, 'store'])->middleware('permission:invoices.create')->name('invoices.store');
    Route::get('invoices/{invoice}', [InvoicesController::class, 'show'])->middleware('permission:invoices.view')->name('invoices.show');
    Route::post('invoices/{invoice}/generate-pdf', [InvoicesController::class, 'generatePdf'])->middleware('permission:invoices.generate')->name('invoices.generate-pdf');
    Route::post('invoices/{invoice}/share', [InvoicesController::class, 'share'])->middleware('permission:invoices.share')->name('invoices.share');
    Route::post('invoices/{invoice}/record-payment', [InvoicesController::class, 'recordPayment'])->middleware('permission:invoices.record_payment')->name('invoices.record-payment');
    Route::post('invoices/{invoice}/status', [InvoicesController::class, 'updateStatus'])->middleware('permission:invoices.edit')->name('invoices.status');
    Route::put('invoices/{invoice}', [InvoicesController::class, 'update'])->middleware('permission:invoices.edit')->name('invoices.update');
    Route::patch('invoices/{invoice}', [InvoicesController::class, 'update'])->middleware('permission:invoices.edit');
    Route::delete('invoices/{invoice}', [InvoicesController::class, 'destroy'])->middleware('permission:invoices.delete')->name('invoices.destroy');
});
