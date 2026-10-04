<?php

use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\ApiAuthenticate;
use App\Http\Middleware\LoadSiteConfiguration;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

require_once __DIR__.'/runtime.php';

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([__DIR__.'/../app/Console/Commands'])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trimStrings(except: ['client_secret', 'db_password', 'smtp_password', 'setup_key', 'web_token']);
        $middleware->append(LoadSiteConfiguration::class);
        $middleware->web(append: [SecurityHeaders::class]);
        $middleware->alias(['admin' => AdminOnly::class, 'permission' => RequirePermission::class, 'api.token' => ApiAuthenticate::class]);
        $middleware->redirectGuestsTo('/entrar');
        $middleware->redirectUsersTo('/painel');
        $middleware->validateCsrfTokens(except: ['webhooks/stripe', 'webhooks/mercadopago', 'webhooks/efi', 'webhooks/efi/*']);
        if (env('APP_ENV') === 'local') {
            $middleware->trustProxies(at: '*');
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['values', 'client_secret', 'token', 'web_token', 'smtp_password', 'setup_key', 'db_password', 'code', 'password', 'password_confirmation']);
        $exceptions->shouldRenderJsonWhen(fn ($request, $e) => $request->is('webhooks/*', 'api/*') || $request->expectsJson());
    })->create();
