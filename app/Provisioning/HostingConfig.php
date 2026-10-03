<?php

namespace App\Provisioning;

use App\Models\Connector;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class HostingConfig
{
    public static function product(string $driver, array $input): array
    {
        try {
            $data = json_decode($input['hosting_config'] ?? '', true, 16, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['hosting_config' => 'Informe JSON válido do plano de hospedagem.']);
        }
        if (! is_array($data)) {
            throw ValidationException::withMessages(['hosting_config' => 'Plano inválido.']);
        }
        $rules = ['domain_suffix' => 'required|string|max:190', 'ip' => 'required|ip'];
        $rules += $driver === 'directadmin' ? ['plan' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/D']] : ['plan_guid' => 'required|uuid', 'auto_customer' => 'sometimes|boolean', 'owner_id' => ! empty($data['auto_customer']) ? 'prohibited' : 'required|integer|min:1|max:2147483647'];
        $v = Validator::make($data, $rules)->validate();
        $v['domain_suffix'] = strtolower($v['domain_suffix']);
        if (! str_contains($v['domain_suffix'], '.') || ! filter_var($v['domain_suffix'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || filter_var($v['domain_suffix'], FILTER_VALIDATE_IP)) {
            throw ValidationException::withMessages(['hosting_config' => 'Domínio-base ASCII inválido.']);
        }
        if (isset($v['owner_id'])) {
            $v['owner_id'] = (int) $v['owner_id'];
        }

        if ($driver === 'plesk') {
            $v['auto_customer'] = (bool) ($v['auto_customer'] ?? false);
        }

        return ['driver' => $driver] + $v;
    }

    public static function snapshot(Service $s, Product $product, Connector $c): void
    {
        if ($s->configuration || ($product->provisioning['driver'] ?? '') !== $c->driver) {
            throw ValidationException::withMessages(['product' => 'Plano inválido ou opções sem mapeamento.']);
        }
        $p = self::product($c->driver, ['hosting_config' => json_encode($product->provisioning)]);
        if ($p['auto_customer'] ?? false) {
            if (! $s->user->hasVerifiedEmail() || ! $c->active || ! config('lagos.native_provisioning')) {
                throw ValidationException::withMessages(['product' => 'Conta automática exige e-mail verificado e integração ativa.']);
            }
            $p['owner_id'] = null;
        }
        $prefix = $c->settings['prefix'] ?? '';
        $id = base_convert((string) $s->id, 10, 36);
        if (! preg_match('/^[a-z]{2}$/D', $prefix) || strlen($id) > 6) {
            throw ValidationException::withMessages(['product' => 'Prefixo inválido ou namespace esgotado.']);
        }
        $username = $prefix.str_pad($id, 6, '0', STR_PAD_LEFT);
        $endpoint = NativeConfig::origin($c->endpoint, $c->driver === 'directadmin' ? [2222, 443] : [8443, 443]);
        $s->update(['native_username' => $username, 'provisioning' => $p + ['connector_id' => $c->id, 'endpoint' => $endpoint, 'client_url' => $endpoint, 'username' => $username, 'domain' => $username.'.'.$p['domain_suffix'], 'creator' => $c->settings['username'] ?? null, 'email' => $s->user->email, 'external_id' => (string) Str::uuid()]]);
    }
}
