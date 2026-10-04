<?php

namespace App\Provisioning;

use App\Models\Connector;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Validation\ValidationException;

final class NativeConfig
{
    public static function origin(string $url, array $ports): string
    {
        $p = parse_url($url);
        if (! $p || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment']) || ! in_array($p['port'] ?? 443, $ports, true) || ! in_array($p['path'] ?? '', ['', '/'], true)) {
            throw ValidationException::withMessages(['endpoint' => 'Informe uma origem HTTPS sem caminho, credenciais ou query, na porta permitida.']);
        }

        return rtrim($url, '/');
    }

    public static function product(?Connector $connector, array $input): ?array
    {
        if (! $connector || $connector->driver === 'json') {
            return null;
        }
        if (in_array($connector->driver, ['directadmin', 'plesk'], true)) {
            return HostingConfig::product($connector->driver, $input);
        }
        if (in_array($connector->driver, ['proxmox', 'virtualizor'], true)) {
            return VpsConfig::product($connector->driver, $input);
        }
        if ($connector->driver === 'pterodactyl') {
            return PterodactylConfig::product($input);
        }
        if ($connector->driver === 'aapanel') {
            return AaPanelConfig::product($input);
        }
        if ($connector->driver !== 'cpanel') {
            throw ValidationException::withMessages(['connector_id' => 'Driver não suportado.']);
        }
        $plan = trim($input['cpanel_plan'] ?? '');
        $suffix = strtolower(trim($input['cpanel_domain_suffix'] ?? ''));
        if (! preg_match('/^[A-Za-z0-9_.-]{1,100}$/D', $plan)) {
            throw ValidationException::withMessages(['cpanel_plan' => 'Informe o nome exato do pacote WHM (letras, números, ponto, hífen ou sublinhado).']);
        }
        if (strlen($suffix) > 190 || ! str_contains($suffix, '.') || ! filter_var($suffix, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || filter_var($suffix, FILTER_VALIDATE_IP)) {
            throw ValidationException::withMessages(['cpanel_domain_suffix' => 'Informe um domínio-base ASCII válido sob seu controle; use punycode para IDN.']);
        }

        return ['driver' => 'cpanel', 'plan' => $plan, 'domain_suffix' => $suffix];
    }

    public static function snapshot(Service $service, Product $product): void
    {
        $c = $product->connector;
        if (! $c || $c->driver === 'json') {
            return;
        }
        if (in_array($c->driver, ['directadmin', 'plesk'], true)) {
            HostingConfig::snapshot($service, $product, $c);

            return;
        }
        if (in_array($c->driver, ['proxmox', 'virtualizor'], true)) {
            VpsConfig::snapshot($service, $product, $c);

            return;
        }
        if ($c->driver === 'pterodactyl') {
            PterodactylConfig::snapshot($service, $product);

            return;
        }
        if ($c->driver === 'aapanel') {
            AaPanelConfig::snapshot($service, $product, $c);

            return;
        }
        if ($c->driver !== 'cpanel' || ($product->provisioning['driver'] ?? null) !== 'cpanel') {
            throw ValidationException::withMessages(['product' => 'Produto sem configuração nativa válida.']);
        }
        if ($service->configuration) {
            throw ValidationException::withMessages(['product' => 'Opções configuráveis ainda não são mapeadas no WHM. Use um produto separado por pacote.']);
        }
        $cfg = self::product($c, ['cpanel_plan' => $product->provisioning['plan'] ?? '', 'cpanel_domain_suffix' => $product->provisioning['domain_suffix'] ?? '']);
        $prefix = $c->settings['prefix'] ?? '';
        $whm = $c->settings['username'] ?? '';
        if (! preg_match('/^[a-z]{2}$/D', $prefix) || ! preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $whm)) {
            throw ValidationException::withMessages(['product' => 'Integração WHM incompleta.']);
        }
        $id = base_convert((string) $service->id, 10, 36);
        if (strlen($id) > 6) {
            throw ValidationException::withMessages(['product' => 'Capacidade do namespace WHM excedida.']);
        }
        $username = $prefix.str_pad($id, 6, '0', STR_PAD_LEFT);
        $service->update(['native_username' => $username, 'provisioning' => [
            'driver' => 'cpanel', 'username' => $username, 'domain' => $username.'.'.$cfg['domain_suffix'], 'plan' => $cfg['plan'],
            'endpoint' => self::origin($c->endpoint, [2087, 443]), 'whm_user' => $whm, 'email' => $service->user->email,
            'client_url' => self::origin($c->settings['client_url'] ?? ('https://'.parse_url($c->endpoint, PHP_URL_HOST).':2083'), [2083, 443]),
        ]]);
    }
}
