<?php

use App\Http\Controllers\ApiAccessController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1,lagos-api-global')->prefix('v1')->group(function () {
    Route::get('/services', [ApiAccessController::class, 'services'])->middleware('api.token:services:read');
    Route::get('/invoices', [ApiAccessController::class, 'invoices'])->middleware('api.token:invoices:read');
    Route::post('/tickets', [ApiAccessController::class, 'ticket'])->middleware('api.token:tickets:write');
});
