<?php

use Illuminate\Support\Facades\Route;
use Modules\Folders\Http\Controllers\FoldersController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('folders', FoldersController::class)->names('folders');
});
