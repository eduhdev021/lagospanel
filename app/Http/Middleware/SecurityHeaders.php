<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        if ($request->is('entrar/social/*')) {
            $response->headers->set('Referrer-Policy', 'no-referrer');
        }
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $csp = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; base-uri 'self'; object-src 'none'";
        if (app()->isProduction()) {
            $csp .= "; frame-ancestors 'self'";
        }
        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $csp);
        }
        if ($request->user() || $request->is('entrar', 'entrar/social/*', 'duas-etapas', 'redefinir-senha/*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
