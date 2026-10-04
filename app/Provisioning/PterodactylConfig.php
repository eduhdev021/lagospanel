<?php

namespace App\Provisioning;

use App\Models\Product;
use App\Models\PterodactylAccount;
use App\Models\Service;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PterodactylConfig
{
    public static function product(array $input): array
    {
        $text = $input['ptero_config'] ?? '';
        try {
            $p = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['ptero_config' => 'Informe JSON válido para o plano Pterodactyl.']);
        }
        if (! is_array($p)) {
            throw ValidationException::withMessages(['ptero_config' => 'Configuração inválida.']);
        }
        // Compatibilidade com o formato antigo do LagosPanel e aliases do addon Paymenter.
        $p['egg_id'] = $p['egg_id'] ?? $p['egg'] ?? null;
        $p['nest_id'] = $p['nest_id'] ?? $p['nest'] ?? null;
        $p['location_ids'] = $p['location_ids'] ?? (isset($p['location']) ? [$p['location']] : null);
        $p['additional_allocations'] = $p['additional_allocations'] ?? max(0, ((int) ($p['allocations'] ?? 1)) - 1);
        $p['start_on_completion'] = $p['start_on_completion'] ?? true;
        $p['skip_scripts'] = $p['skip_scripts'] ?? false;
        $p['oom_killer'] = $p['oom_killer'] ?? false;
        $p['dedicated_ip'] = $p['dedicated_ip'] ?? false;
        $p['port_range'] = $p['port_range'] ?? [];
        $p['port_array'] = $p['port_array'] ?? [];
        $v = Validator::make($p, [
            'auto_account' => 'sometimes|boolean',
            'egg_id' => 'required|integer|min:1|max:2147483647', 'nest_id' => 'nullable|integer|min:1|max:2147483647',
            'location_ids' => 'required|array|min:1|max:20', 'location_ids.*' => 'integer|min:1|max:2147483647',
            'docker_image' => 'required|string|max:255', 'startup' => 'required|string|max:2000', 'environment' => 'present|array|max:50', 'environment.*' => 'nullable|string|max:2000',
            'memory' => 'required|integer|min:128|max:1048576', 'disk' => 'required|integer|min:128|max:104857600', 'cpu' => 'required|integer|min:1|max:6400',
            'swap' => 'required|integer|min:-1|max:1048576', 'io' => 'required|integer|min:10|max:1000',
            'databases' => 'required|integer|min:0|max:100', 'allocations' => 'required|integer|min:1|max:100', 'additional_allocations' => 'required|integer|min:0|max:99', 'backups' => 'required|integer|min:0|max:100',
            'node' => 'nullable|integer|min:1|max:2147483647', 'cpu_pinning' => 'nullable|string|max:100',
            'port_range' => 'array|max:100', 'port_range.*' => 'string|max:32', 'port_array' => 'array|max:100',
            'port_array.*' => 'string|max:100', 'dedicated_ip' => 'boolean', 'skip_scripts' => 'boolean',
            'start_on_completion' => 'boolean', 'oom_killer' => 'boolean',
        ])->validate();
        foreach (array_keys($v['environment']) as $key) {
            if (! preg_match('/^[A-Z][A-Z0-9_]{0,99}$/D', (string) $key)) {
                throw ValidationException::withMessages(['ptero_config' => 'Nomes das variáveis devem usar maiúsculas, números e sublinhado.']);
            }
        }
        foreach (['egg_id', 'nest_id', 'memory', 'disk', 'cpu', 'swap', 'io', 'databases', 'allocations', 'additional_allocations', 'backups', 'node'] as $key) {
            if (! array_key_exists($key, $v) || $v[$key] === null) {
                continue;
            }
            $v[$key] = (int) $v[$key];
        }

        $v['egg'] = $v['egg_id'];
        $v['location'] = (int) $v['location_ids'][0];

        return ['driver' => 'pterodactyl'] + $v;
    }

    public static function snapshot(Service $s, Product $p): void
    {
        if ($s->configuration || ($p->provisioning['driver'] ?? '') !== 'pterodactyl') {
            throw ValidationException::withMessages(['product' => 'Opções não mapeadas ou plano Pterodactyl inválido.']);
        }
        $account = PterodactylAccount::where('connector_id', $p->connector_id)->where('user_id', $s->user_id)->first();
        $auto = (bool) ($p->provisioning['auto_account'] ?? false);
        if (! $account && ! $auto) {
            throw ValidationException::withMessages(['product' => 'Antes da contratação, a equipe deve vincular sua conta Pterodactyl. Abra um chamado.']);
        }
        if (! $account && (! $s->user->hasVerifiedEmail() || ! $p->connector->active || ! config('lagos.native_provisioning'))) {
            throw ValidationException::withMessages(['product' => 'Criação automática exige e-mail verificado e integração nativa ativa.']);
        }
        $cfg = self::product(['ptero_config' => json_encode($p->provisioning)]);
        $parts = preg_split('/\s+/u', trim($s->user->name), 2);
        $names = ['first_name' => mb_substr($parts[0] ?: 'Cliente', 0, 64), 'last_name' => mb_substr($parts[1] ?? 'Cliente', 0, 64)];
        $s->update(['provisioning' => $cfg + ['endpoint' => AaPanelConfig::origin($p->connector->endpoint), 'external_id' => 'lagos-'.Str::uuid(), 'remote_user_id' => $account?->remote_user_id, 'account_names' => $names, 'email' => $s->user->email]]);
    }
}
