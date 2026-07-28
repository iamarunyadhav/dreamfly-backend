<?php

use Illuminate\Support\Facades\Route;
use Modules\Users\Http\Controllers\UsersController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('users', [UsersController::class, 'index'])->middleware('permission:users.view')->name('users.index');
    Route::post('users', [UsersController::class, 'store'])->middleware('permission:users.create')->name('users.store');
    Route::get('users/{user}', [UsersController::class, 'show'])->middleware('permission:users.view')->name('users.show');
    Route::put('users/{user}', [UsersController::class, 'update'])->middleware('permission:users.edit')->name('users.update');
    Route::patch('users/{user}', [UsersController::class, 'update'])->middleware('permission:users.edit');
    Route::delete('users/{user}', [UsersController::class, 'destroy'])->middleware('permission:users.delete')->name('users.destroy');
});
