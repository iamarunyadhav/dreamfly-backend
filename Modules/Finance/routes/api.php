<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\DailyClosingsController;
use Modules\Finance\Http\Controllers\FinanceController;
use Modules\Finance\Http\Controllers\PayablesController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    // Reports.
    Route::get('finance/summary', [FinanceController::class, 'summary'])->middleware('permission:finance.view')->name('finance.summary');
    Route::get('finance/receivables', [FinanceController::class, 'receivables'])->middleware('permission:finance.view')->name('finance.receivables');

    // Payables - the mirror of receivables (money the consultancy owes out).
    Route::get('finance/payables/summary', [PayablesController::class, 'summary'])->middleware('permission:finance.view')->name('finance.payables.summary');
    Route::get('finance/payables', [PayablesController::class, 'index'])->middleware('permission:finance.view')->name('finance.payables.index');
    Route::post('finance/payables', [PayablesController::class, 'store'])->middleware('permission:finance.create')->name('finance.payables.store');
    Route::get('finance/payables/{payable}', [PayablesController::class, 'show'])->middleware('permission:finance.view')->name('finance.payables.show');
    Route::put('finance/payables/{payable}', [PayablesController::class, 'update'])->middleware('permission:finance.edit')->name('finance.payables.update');
    Route::post('finance/payables/{payable}/pay', [PayablesController::class, 'pay'])->middleware('permission:finance.edit')->name('finance.payables.pay');
    Route::post('finance/payables/{payable}/cancel', [PayablesController::class, 'cancel'])->middleware('permission:finance.edit')->name('finance.payables.cancel');
    Route::delete('finance/payables/{payable}', [PayablesController::class, 'destroy'])->middleware('permission:finance.delete')->name('finance.payables.destroy');

    // Daily closing.
    Route::get('finance/daily-closings', [DailyClosingsController::class, 'index'])->middleware('permission:finance.view')->name('finance.daily-closings.index');
    Route::get('finance/daily-closings/compute', [DailyClosingsController::class, 'compute'])->middleware('permission:finance.view')->name('finance.daily-closings.compute');
    Route::post('finance/daily-closings/close', [DailyClosingsController::class, 'close'])->middleware('permission:finance.edit')->name('finance.daily-closings.close');
    Route::post('finance/daily-closings/{dailyClosing}/reopen', [DailyClosingsController::class, 'reopen'])->middleware('permission:finance.edit')->name('finance.daily-closings.reopen');
    Route::post('finance/daily-closings/{dailyClosing}/pdf', [DailyClosingsController::class, 'pdf'])->middleware('permission:finance.view')->name('finance.daily-closings.pdf');
    Route::post('finance/daily-closings/{dailyClosing}/send-to-admin', [DailyClosingsController::class, 'sendToAdmin'])->middleware('permission:finance.view')->name('finance.daily-closings.send-to-admin');

    // Ledger.
    Route::get('finance/ledger', [FinanceController::class, 'index'])->middleware('permission:finance.view')->name('finance.index');
    Route::post('finance/ledger', [FinanceController::class, 'store'])->middleware('permission:finance.create')->name('finance.store');
    Route::post('finance/ledger/adjust', [FinanceController::class, 'adjust'])->middleware('permission:finance.create')->name('finance.adjust');
    Route::get('finance/ledger/{ledgerEntry}', [FinanceController::class, 'show'])->middleware('permission:finance.view')->name('finance.show');
    Route::put('finance/ledger/{ledgerEntry}', [FinanceController::class, 'update'])->middleware('permission:finance.edit')->name('finance.update');
    Route::patch('finance/ledger/{ledgerEntry}', [FinanceController::class, 'update'])->middleware('permission:finance.edit');
    Route::delete('finance/ledger/{ledgerEntry}', [FinanceController::class, 'destroy'])->middleware('permission:finance.delete')->name('finance.destroy');
});
