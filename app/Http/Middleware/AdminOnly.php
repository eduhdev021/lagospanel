<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->isStaff(), 403);

        if (config('admin_access.require_two_factor', false) && app()->isProduction() && ! $request->user()->totp_secret) {
            return redirect()->route('profile')->withErrors(['security' => 'Ative a autenticação em duas etapas antes de acessar a administração.']);
        }

        return $next($request);
    }
}
