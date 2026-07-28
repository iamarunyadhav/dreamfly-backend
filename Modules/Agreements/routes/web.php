<?php

use Illuminate\Support\Facades\Route;
use Modules\Agreements\Http\Controllers\AgreementsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('agreements', AgreementsController::class)->names('agreements');
});
