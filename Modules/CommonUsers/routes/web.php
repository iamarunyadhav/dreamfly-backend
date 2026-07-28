<?php

use Illuminate\Support\Facades\Route;
use Modules\CommonUsers\Http\Controllers\CommonUsersController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('commonusers', CommonUsersController::class)->names('commonusers');
});
