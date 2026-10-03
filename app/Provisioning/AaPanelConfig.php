<?php

namespace App\Provisioning;

use App\Models\Connector;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AaPanelConfig
{
    public static function origin(string $url): string
    {
        $port = parse_url($url, PHP_URL_PORT) ?? 443;
        if (! is_int($port) || $port < 1 || $port > 65535 || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages(['endpoint' => 'Origem HTTPS aaPanel inválida.']);
        }

        return NativeConfig::origin($url, [$port]);
    }

    public static function product(array $data): array
    {
        $suffix = strtolower(trim($data['aapanel_domain_suffix'] ?? ''));
        $version = $data['aapanel_php_version'] ?? '';
        if (strlen($suffix) > 190 || ! str_contains($suffix, '.') || ! filter_var($suffix, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || filter_var($suffix, FILTER_VALIDATE_IP)) {
            throw ValidationException::withMessages(['aapanel_domain_suffix' => 'Informe domínio-base ASCII válido sob seu controle.']);
        }
        if (! preg_match('/^(00|[5-9][0-9])$/D', $version)) {
            throw ValidationException::withMessages(['aapanel_php_version' => 'Use código PHP instalado, como 82, ou 00 para estático.']);
        }

        return ['driver' => 'aapanel', 'domain_suffix' => $suffix, 'php_version' => $version];
    }

    public static function snapshot(Service $s, Product $product, Connector $c): void
    {
        if (($product->provisioning['driver'] ?? '') !== 'aapanel' || $s->configuration) {
            throw ValidationException::withMessages(['product' => 'Configuração aaPanel inválida ou opções não mapeadas.']);
        }
        $cfg = self::product(['aapanel_domain_suffix' => $product->provisioning['domain_suffix'] ?? '', 'aapanel_php_version' => $product->provisioning['php_version'] ?? '']);
        $prefix = $c->settings['prefix'] ?? '';
        $id = base_convert((string) $s->id, 10, 36);
        if (! preg_match('/^[a-z]{2}$/D', $prefix) || strlen($id) > 6) {
            throw ValidationException::withMessages(['product' => 'Namespace aaPanel inválido ou esgotado.']);
        }
        $name = $prefix.str_pad($id, 6, '0', STR_PAD_LEFT);
        $domain = $name.'.'.$cfg['domain_suffix'];
        $nonce = (string) Str::uuid();
        $s->update(['native_username' => $name, 'provisioning' => ['driver' => 'aapanel', 'endpoint' => self::origin($c->endpoint), 'domain' => $domain, 'path' => '/www/wwwroot/'.$domain.'-'.$nonce, 'php_version' => $cfg['php_version'], 'marker' => 'LagosPanel:'.$nonce]]);
    }
}
