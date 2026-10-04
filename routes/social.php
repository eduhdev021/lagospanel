<?php

use App\Http\Controllers\HomepageController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\SocialSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/entrar/social/concluir', [SocialAuthController::class, 'complete'])->name('social.complete');
    Route::post('/entrar/social/concluir', [SocialAuthController::class, 'register'])->middleware('throttle:5,1,social-register')->name('social.register');
    Route::get('/entrar/social/{provider}', [SocialAuthController::class, 'start'])->middleware('throttle:15,1,social-start')->name('social.start');
});
Route::get('/entrar/social/{provider}/retorno', [SocialAuthController::class, 'callback'])->middleware('throttle:20,1,social-callback')->name('social.callback');
Route::middleware(['auth', 'auth.session', 'verified'])->group(function () {
    Route::get('/admin/configuracoes/pagina-inicial', [HomepageController::class, 'settings'])->middleware('admin')->name('admin.settings.homepage');
    Route::post('/admin/configuracoes/pagina-inicial', [HomepageController::class, 'save'])->middleware('admin')->name('admin.settings.homepage.save');
    Route::post('/painel/perfil/social/{provider}', [SocialAuthController::class, 'link'])->middleware('throttle:5,1,social-link')->name('social.link');
    Route::delete('/painel/perfil/social/{provider}', [SocialAuthController::class, 'unlink'])->middleware('throttle:5,1,social-unlink')->name('social.unlink');
    Route::get('/admin/configuracoes/login-social', [SocialSettingsController::class, 'index'])->middleware('admin')->name('admin.settings.social');
    Route::post('/admin/configuracoes/login-social/{provider}', [SocialSettingsController::class, 'save'])->middleware(['admin', 'throttle:10,1,social-settings'])->name('admin.settings.social.save');
});
