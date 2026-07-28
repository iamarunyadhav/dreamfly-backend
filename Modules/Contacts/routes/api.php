<?php

use Illuminate\Support\Facades\Route;
use Modules\Contacts\Http\Controllers\ContactsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('contacts', [ContactsController::class, 'index'])->middleware('permission:contacts.view')->name('contacts.index');
    Route::post('contacts', [ContactsController::class, 'store'])->middleware('permission:contacts.create')->name('contacts.store');
    Route::get('contacts/{contact}', [ContactsController::class, 'show'])->middleware('permission:contacts.view')->name('contacts.show');
    Route::put('contacts/{contact}', [ContactsController::class, 'update'])->middleware('permission:contacts.edit')->name('contacts.update');
    Route::patch('contacts/{contact}', [ContactsController::class, 'update'])->middleware('permission:contacts.edit');
    Route::delete('contacts/{contact}', [ContactsController::class, 'destroy'])->middleware('permission:contacts.delete')->name('contacts.destroy');
});
