<?php

use Illuminate\Support\Facades\Route;
use Modules\Invoices\Http\Controllers\InvoicesController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('invoices', InvoicesController::class)->names('invoices');
});
