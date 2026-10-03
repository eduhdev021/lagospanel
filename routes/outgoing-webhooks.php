<?php

use App\Http\Controllers\OutgoingWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/webhooks')->name('admin.webhooks.')->middleware(['auth', 'auth.session', 'verified', 'admin'])->group(function () {
    Route::get('/', [OutgoingWebhookController::class, 'index'])->name('index');
    Route::get('/entregas/{delivery}', [OutgoingWebhookController::class, 'show'])->name('show');
    Route::post('/', [OutgoingWebhookController::class, 'create'])->middleware('throttle:5,1,lagos-webhook-admin')->name('create');
    Route::post('/destinos/{endpoint}', [OutgoingWebhookController::class, 'state'])->middleware('throttle:5,1,lagos-webhook-admin')->name('state');
    Route::post('/entregas/{delivery}/repetir', [OutgoingWebhookController::class, 'retry'])->middleware('throttle:5,1,lagos-webhook-admin')->name('retry');
});
