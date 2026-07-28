<?php

use Illuminate\Support\Facades\Route;
use Modules\Files\Http\Controllers\FilesController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('files', FilesController::class)->names('files');
});
