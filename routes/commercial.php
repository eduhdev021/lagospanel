<?php

use App\Http\Controllers\BulletinController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\QuoteController;
use Illuminate\Support\Facades\Route;

Route::get('/avisos', [BulletinController::class, 'index'])->name('bulletins.index');
Route::get('/avisos/{bulletin}', [BulletinController::class, 'show'])->name('bulletins.show');
Route::middleware(['auth', 'auth.session', 'verified'])->group(function () {
    Route::get('/painel/orcamentos', [QuoteController::class, 'index'])->name('quotes.index');
    Route::get('/painel/orcamentos/{quote}', [QuoteController::class, 'show'])->name('quotes.show');
    Route::post('/painel/orcamentos/{quote}/decisao', [QuoteController::class, 'decide'])->middleware('throttle:10,1,lagos-quote-decide')->name('quotes.decide');
    Route::get('/painel/downloads', [DownloadController::class, 'index'])->name('downloads.index');
    Route::get('/painel/downloads/{asset}/arquivo', [DownloadController::class, 'fetch'])->middleware('throttle:10,1,lagos-download')->name('downloads.fetch');
    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/orcamentos', [QuoteController::class, 'index'])->name('quotes.index');
        Route::get('/orcamentos/novo', [QuoteController::class, 'form'])->name('quotes.new');
        Route::post('/orcamentos', [QuoteController::class, 'save'])->name('quotes.create');
        Route::get('/orcamentos/{quote}', [QuoteController::class, 'show'])->name('quotes.show');
        Route::get('/orcamentos/{quote}/editar', [QuoteController::class, 'form'])->name('quotes.edit');
        Route::post('/orcamentos/{quote}', [QuoteController::class, 'save'])->name('quotes.save');
        Route::post('/orcamentos/{quote}/estado', [QuoteController::class, 'transition'])->name('quotes.transition');
        Route::get('/avisos', [BulletinController::class, 'index'])->name('bulletins.index');
        Route::post('/avisos', [BulletinController::class, 'create'])->name('bulletins.create');
        Route::get('/avisos/{bulletin}', [BulletinController::class, 'show'])->name('bulletins.show');
        Route::post('/avisos/{bulletin}', [BulletinController::class, 'update'])->name('bulletins.update');
        Route::get('/downloads', [DownloadController::class, 'index'])->name('downloads.index');
        Route::post('/downloads', [DownloadController::class, 'create'])->middleware('throttle:5,1,lagos-download-upload')->name('downloads.create');
        Route::post('/downloads/{asset}/estado', [DownloadController::class, 'state'])->name('downloads.state');
        Route::get('/downloads/{asset}/arquivo', [DownloadController::class, 'fetch'])->middleware('throttle:10,1,lagos-staff-download-asset')->name('downloads.fetch');
    });
});
