<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;

Route::prefix('v1/auth')->name('auth.')->group(function () {
    // Cap credential-stuffing: 5 failed-or-otherwise attempts per minute per IP+email.
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login');

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::put('profile', [AuthController::class, 'updateProfile'])->name('profile.update');
    });
});
