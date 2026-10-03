<?php

namespace App\Http\Middleware;

use App\Support\AdminPermissions;
use Closure;
use Illuminate\Http\Request;

class RequirePermission
{
    public function handle(Request $r, Closure $next)
    {
        abort_unless($r->user()?->hasPermission(AdminPermissions::forRoute($r->route()->getName(), $r->method())), 403);

        return $next($r);
    }
}
