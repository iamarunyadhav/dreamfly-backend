<?php

use Illuminate\Support\Facades\Route;
use Modules\Checklists\Http\Controllers\ChecklistsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('checklists', ChecklistsController::class)->names('checklists');
});
