<?php

use Illuminate\Support\Facades\Route;
use Modules\Forms\Http\Controllers\FormsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('forms', FormsController::class)->names('forms');
});
