<?php

namespace App\Http\Middleware;

use App\Services\SiteConfiguration;
use Closure;
use Illuminate\Http\Request;

class LoadSiteConfiguration
{
    public function handle(Request $r, Closure $next)
    {
        if (is_file(config('setup.state_path')) && ! is_file(config('setup.lock_path'))) {
            if (! $r->is('instalar', 'instalar/*')) {
                return response('Instalação em andamento. Acesso reservado ao operador.', 503)->header('Cache-Control', 'no-store');
            }
        } elseif (! $r->is('instalar', 'instalar/*')) {
            try {
                app(SiteConfiguration::class)->apply();
            } catch (\Throwable) {
                return response('Configuração do site indisponível. Contate o operador.', 503);
            }
        }

        return $next($r);
    }
}
