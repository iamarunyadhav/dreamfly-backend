<?php

use Illuminate\Support\Facades\Route;
use Modules\Communications\Http\Controllers\AlertTemplatesController;
use Modules\Communications\Http\Controllers\ChannelSettingsController;
use Modules\Communications\Http\Controllers\DeliveryWebhooksController;
use Modules\Communications\Http\Controllers\MessageTemplatesController;
use Modules\Communications\Http\Controllers\MessagesController;

Route::prefix('v1')->group(function () {
    Route::post('communications/webhooks/{channel}', [DeliveryWebhooksController::class, 'store'])->name('communications.webhooks.store');
});

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('communications/channel-settings', [ChannelSettingsController::class, 'show'])->middleware('permission:communications.view')->name('communications.channel-settings.show');
    Route::put('communications/channel-settings', [ChannelSettingsController::class, 'update'])->middleware('permission:communications.update')->name('communications.channel-settings.update');

    Route::get('communications/templates', [MessageTemplatesController::class, 'index'])->middleware('permission:communications.view')->name('communications.templates.index');
    Route::post('communications/templates', [MessageTemplatesController::class, 'store'])->middleware('permission:communications.create')->name('communications.templates.store');
    Route::get('communications/templates/{template}', [MessageTemplatesController::class, 'show'])->middleware('permission:communications.view')->name('communications.templates.show');
    Route::put('communications/templates/{template}', [MessageTemplatesController::class, 'update'])->middleware('permission:communications.edit')->name('communications.templates.update');
    Route::patch('communications/templates/{template}', [MessageTemplatesController::class, 'update'])->middleware('permission:communications.edit');
    Route::delete('communications/templates/{template}', [MessageTemplatesController::class, 'destroy'])->middleware('permission:communications.delete')->name('communications.templates.destroy');

    Route::get('communications/messages', [MessagesController::class, 'index'])->middleware('permission:communications.view')->name('communications.messages.index');
    Route::post('communications/messages', [MessagesController::class, 'store'])->middleware('permission:communications.create')->name('communications.messages.store');

    Route::get('communications/alerts', [AlertTemplatesController::class, 'index'])->middleware('permission:communications.view')->name('communications.alerts.index');
    Route::post('communications/alerts', [AlertTemplatesController::class, 'store'])->middleware('permission:communications.create')->name('communications.alerts.store');
    Route::put('communications/alerts/{alert}', [AlertTemplatesController::class, 'update'])->middleware('permission:communications.update')->name('communications.alerts.update');
    Route::delete('communications/alerts/{alert}', [AlertTemplatesController::class, 'destroy'])->middleware('permission:communications.delete')->name('communications.alerts.destroy');
});
