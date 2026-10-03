<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WebInstallerController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/instalar', [WebInstallerController::class, 'index'])->name('setup.index');
Route::post('/instalar/autorizar', [WebInstallerController::class, 'unlock'])->middleware('throttle:5,1,lagos-setup-unlock')->name('setup.unlock');
Route::post('/instalar/concluir', [WebInstallerController::class, 'finish'])->middleware('throttle:3,1,lagos-setup-finish')->name('setup.finish');
Route::get('/', fn () => redirect()->route('store'));
Route::get('/loja', [PortalController::class, 'store'])->name('store');
Route::view('/termos', 'terms')->name('terms');
Route::middleware('guest')->group(function () {
    Route::get('/duas-etapas', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/duas-etapas', [TwoFactorController::class, 'confirm'])->middleware('throttle:10,1,lagos-two-factor-confirm')->name('two-factor.confirm');
    Route::view('/entrar', 'auth.login')->name('login');
    Route::post('/entrar', [AuthController::class, 'login'])->middleware('throttle:15,1,lagos-login');
    Route::get('/registrar', function () {
        abort_unless(config('site.registration_enabled'), 403, 'Novos cadastros estão desativados.');

        return view('auth.register');
    })->name('register');
    Route::post('/registrar', [AuthController::class, 'register'])->middleware('throttle:5,1,lagos-register');
    Route::view('/esqueci-senha', 'auth.forgot')->name('password.request');
    Route::post('/esqueci-senha', [AuthController::class, 'forgot'])->middleware('throttle:5,1,lagos-password-email')->name('password.email');
    Route::get('/redefinir-senha/{token}', fn (Request $r, string $token) => view('auth.reset', ['token' => $token, 'email' => $r->query('email', '')]))->name('password.reset');
    Route::post('/redefinir-senha', [AuthController::class, 'reset'])->middleware('throttle:10,1,lagos-password-reset')->name('password.update');
});
Route::post('/sair', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::middleware('auth')->group(function () {
    Route::view('/verificar-email', 'auth.verify')->name('verification.notice');
    Route::post('/verificar-email', function (Request $r) {
        $r->user()->sendEmailVerificationNotification();

        return back()->with('status', 'Solicitação de envio registrada.');
    })->middleware('throttle:3,1,lagos-verification-email')->name('verification.send');
    Route::get('/email/verificar/{id}/{hash}', function (EmailVerificationRequest $r) {
        $r->fulfill();

        return redirect()->route('dashboard');
    })->middleware(['signed', 'throttle:6,1,lagos-verification-verify'])->name('verification.verify');
});
Route::middleware(['auth', 'auth.session', 'verified'])->group(function () {
    Route::post('/painel/2fa/iniciar', [TwoFactorController::class, 'begin'])->middleware('throttle:5,1,lagos-two-factor-begin')->name('two-factor.begin');
    Route::post('/painel/2fa/ativar', [TwoFactorController::class, 'enable'])->middleware('throttle:5,1,lagos-two-factor-enable')->name('two-factor.enable');
    Route::post('/painel/2fa/desativar', [TwoFactorController::class, 'disable'])->middleware('throttle:5,1,lagos-two-factor-disable')->name('two-factor.disable');
    Route::get('/painel', [PortalController::class, 'dashboard'])->name('dashboard');
    Route::post('/pedidos', [PortalController::class, 'order'])->middleware('throttle:20,1,lagos-order-create')->name('orders.create');
    Route::get('/painel/faturas', [PortalController::class, 'invoices'])->name('invoices.index');
    Route::get('/painel/faturas/{invoice}', [PortalController::class, 'invoice'])->name('invoices.show');
    Route::post('/painel/faturas/{invoice}/saldo', [PortalController::class, 'walletPay'])->middleware('throttle:10,1,lagos-wallet-pay')->name('invoices.wallet');
    Route::post('/painel/faturas/{invoice}/pagar', [PortalController::class, 'gateway'])->middleware('throttle:10,1,lagos-gateway-checkout')->name('invoices.gateway');
    Route::get('/painel/servicos', [PortalController::class, 'services'])->name('services.index');
    Route::post('/painel/servicos/{service}/cancelamento', [PortalController::class, 'cancellation'])->name('services.cancel');
    Route::get('/painel/suporte', [PortalController::class, 'tickets'])->name('tickets.index');
    Route::post('/painel/suporte', [PortalController::class, 'ticketCreate'])->middleware('throttle:10,1,lagos-ticket-create')->name('tickets.create');
    Route::post('/painel/suporte/{ticket}/responder', [PortalController::class, 'reply'])->middleware('throttle:20,1,lagos-ticket-reply')->name('tickets.reply');
    Route::get('/painel/perfil', [PortalController::class, 'profile'])->name('profile');
    Route::post('/painel/perfil', [PortalController::class, 'profileUpdate'])->name('profile.update');
    Route::post('/painel/saldo', [PortalController::class, 'deposit'])->middleware('throttle:5,1,lagos-deposit')->name('wallet.deposit');
    Route::prefix('/admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/produtos', [AdminController::class, 'products'])->name('products');
        Route::get('/produtos/{product}/editar', [AdminController::class, 'products'])->name('products.edit');
        Route::post('/produtos', [AdminController::class, 'saveProduct'])->name('products.create');
        Route::post('/produtos/{product}', [AdminController::class, 'saveProduct'])->name('products.update');
        Route::get('/faturas', [AdminController::class, 'invoices'])->name('invoices');
        Route::post('/faturas/{invoice}/confirmar', [AdminController::class, 'paid'])->name('invoices.paid');
        Route::get('/servicos', [AdminController::class, 'services'])->name('services');
        Route::post('/servicos/{service}', [AdminController::class, 'serviceAction'])->name('services.action');
        Route::get('/clientes', [AdminController::class, 'users'])->name('users');
        Route::get('/suporte', [AdminController::class, 'tickets'])->name('tickets');
        Route::post('/suporte/{ticket}', [AdminController::class, 'reply'])->middleware('throttle:30,1,lagos-staff-reply')->whereNumber('ticket')->name('tickets.reply');
        Route::get('/integracoes', [AdminController::class, 'connectors'])->name('connectors');
        Route::post('/integracoes', [AdminController::class, 'connectorCreate'])->name('connectors.create');
        Route::get('/cupons', [AdminController::class, 'coupons'])->name('coupons');
        Route::post('/cupons', [AdminController::class, 'couponCreate'])->name('coupons.create');
        Route::get('/operacoes', [AdminController::class, 'operations'])->name('operations');
        Route::get('/auditoria', [AdminController::class, 'audit'])->name('audit');
    });
});
Route::post('/webhooks/stripe', [WebhookController::class, 'stripe'])->middleware('throttle:120,1,lagos-webhook-stripe');
Route::post('/webhooks/mercadopago', [WebhookController::class, 'mercadoPago'])->middleware('throttle:120,1,lagos-webhook-mercadopago');

require __DIR__.'/expansion.php';
require __DIR__.'/commercial.php';
foreach (Route::getRoutes() as $route) {
    if (str_starts_with($route->getName() ?? '', 'admin.')) {
        $route->middleware('permission');
    }
}
