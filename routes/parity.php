<?php

use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\SubaccountController;
use App\Http\Controllers\UpgradeAddonController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified'])->group(function () {
    // Domínios (Cliente)
    Route::get('/painel/dominios', [DomainController::class, 'clientIndex'])->name('domains.index');
    Route::post('/painel/dominios', [DomainController::class, 'order'])->middleware('throttle:15,1,lagos-domain-order')->name('domains.order');
    Route::post('/painel/dominios/{domain}/nameservers', [DomainController::class, 'updateNameservers'])->middleware('throttle:15,1,lagos-domain-ns')->name('domains.nameservers');
    Route::post('/painel/dominios/{domain}/bloqueio', [DomainController::class, 'toggleLock'])->middleware('throttle:15,1,lagos-domain-lock')->name('domains.lock');
    Route::post('/painel/dominios/{domain}/epp', [DomainController::class, 'revealEpp'])->middleware('throttle:5,1,lagos-domain-epp')->name('domains.epp');
    Route::post('/painel/dominios/{domain}/renovar', [DomainController::class, 'renew'])->middleware('throttle:10,1,lagos-domain-renew')->name('domains.renew');

    // Upgrades/Downgrades e Addons (Cliente)
    Route::post('/painel/servicos/{service}/upgrade', [UpgradeAddonController::class, 'requestUpgrade'])->middleware('throttle:10,1,lagos-service-upgrade')->name('services.upgrade');
    Route::post('/painel/servicos/{service}/addons', [UpgradeAddonController::class, 'orderAddon'])->middleware('throttle:10,1,lagos-service-addon')->name('services.addons.order');

    // Afiliados (Cliente)
    Route::get('/painel/afiliados', [AffiliateController::class, 'clientIndex'])->name('affiliates.index');
    Route::post('/painel/afiliados/ativar', [AffiliateController::class, 'enroll'])->middleware('throttle:5,1,lagos-affiliate-enroll')->name('affiliates.enroll');
    Route::post('/painel/afiliados/resgatar', [AffiliateController::class, 'withdraw'])->middleware('throttle:5,1,lagos-affiliate-withdraw')->name('affiliates.withdraw');

    // Subcontas / Contatos autorizados (Cliente)
    Route::get('/painel/subcontas', [SubaccountController::class, 'index'])->name('subaccounts.index');
    Route::post('/painel/subcontas', [SubaccountController::class, 'store'])->middleware('throttle:10,1,lagos-subaccount-save')->name('subaccounts.store');
    Route::post('/painel/subcontas/{contact}/remover', [SubaccountController::class, 'destroy'])->name('subaccounts.destroy');

    // Administração (WHMCS / Paymenter Parity)
    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/dominios', [DomainController::class, 'adminIndex'])->name('domains.index');
        Route::post('/dominios/tlds', [DomainController::class, 'saveTld'])->name('domains.tlds.save');
        Route::post('/dominios/{domain}/estado', [DomainController::class, 'adminStatus'])->name('domains.status');

        Route::get('/addons', [UpgradeAddonController::class, 'adminIndex'])->name('addons.index');
        Route::post('/addons', [UpgradeAddonController::class, 'saveAddon'])->name('addons.save');

        Route::get('/afiliados', [AffiliateController::class, 'adminIndex'])->name('affiliates.index');
        Route::post('/afiliados/{affiliate}', [AffiliateController::class, 'adminUpdate'])->name('affiliates.update');

        Route::get('/importacao', [ImportController::class, 'index'])->name('import.index');
        Route::post('/importacao', [ImportController::class, 'run'])->middleware('throttle:5,1,lagos-panel-import')->name('import.run');
    });
});
