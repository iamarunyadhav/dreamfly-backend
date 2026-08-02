<?php

use Illuminate\Support\Facades\Route;
use Modules\CommonUsers\Http\Controllers\CommonUsersController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('common-users', [CommonUsersController::class, 'index'])->middleware('permission:common-users.view')->name('common-users.index');
    Route::post('common-users', [CommonUsersController::class, 'store'])->middleware('permission:common-users.create')->name('common-users.store');
    Route::get('common-users/{commonUser}', [CommonUsersController::class, 'show'])->middleware('permission:common-users.view')->name('common-users.show');
    Route::put('common-users/{commonUser}', [CommonUsersController::class, 'update'])->middleware('permission:common-users.edit')->name('common-users.update');
    Route::patch('common-users/{commonUser}', [CommonUsersController::class, 'update'])->middleware('permission:common-users.edit');
    Route::delete('common-users/{commonUser}', [CommonUsersController::class, 'destroy'])->middleware('permission:common-users.delete')->name('common-users.destroy');
    Route::post('common-users/{commonUserId}/restore', [CommonUsersController::class, 'restore'])
        ->middleware('permission:common-users.edit')->name('common-users.restore');

    Route::get('common-users/{commonUser}/documents', [CommonUsersController::class, 'documents'])
        ->middleware('permission:common-users.view')->name('common-users.documents.index');
    Route::post('common-users/{commonUser}/documents', [CommonUsersController::class, 'uploadDocument'])
        ->middleware('permission:common-users.edit')->name('common-users.documents.store');
    Route::post('common-users/{commonUser}/profile-photo', [CommonUsersController::class, 'uploadProfilePhoto'])
        ->middleware('permission:common-users.edit')->name('common-users.profile-photo.store');
    Route::post('common-users/{commonUser}/payments', [CommonUsersController::class, 'recordPayment'])
        ->middleware(['permission:common-users.edit', 'permission:payments.create'])->name('common-users.payments.store');

    Route::post('common-users/{commonUser}/convert', [CommonUsersController::class, 'convert'])
        ->middleware(['permission:common-users.edit', 'permission:clients.convert'])
        ->name('common-users.convert');
});
