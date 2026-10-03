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
        $v = Validator::make($p, [
            'egg' => 'required|integer|min:1|max:2147483647', 'location' => 'required|integer|min:1|max:2147483647',
            'docker_image' => 'required|string|max:255', 'startup' => 'required|string|max:2000', 'environment' => 'present|array|max:50', 'environment.*' => 'nullable|string|max:2000',
            'memory' => 'required|integer|min:128|max:1048576', 'disk' => 'required|integer|min:128|max:104857600', 'cpu' => 'required|integer|min:1|max:6400',
            'swap' => 'required|integer|min:0|max:1048576', 'io' => 'required|integer|min:10|max:1000',
            'databases' => 'required|integer|min:0|max:100', 'allocations' => 'required|integer|min:1|max:100', 'backups' => 'required|integer|min:0|max:100',
        ])->validate();
        foreach (array_keys($v['environment']) as $key) {
            if (! preg_match('/^[A-Z][A-Z0-9_]{0,99}$/D', (string) $key)) {
                throw ValidationException::withMessages(['ptero_config' => 'Nomes das variáveis devem usar maiúsculas, números e sublinhado.']);
            }
        }
        foreach (['egg', 'location', 'memory', 'disk', 'cpu', 'swap', 'io', 'databases', 'allocations', 'backups'] as $key) {
            $v[$key] = (int) $v[$key];
        }

        return ['driver' => 'pterodactyl'] + $v;
    }

    public static function snapshot(Service $s, Product $p): void
    {
        if ($s->configuration || ($p->provisioning['driver'] ?? '') !== 'pterodactyl') {
            throw ValidationException::withMessages(['product' => 'Opções não mapeadas ou plano Pterodactyl inválido.']);
        }
        $account = PterodactylAccount::where('connector_id', $p->connector_id)->where('user_id', $s->user_id)->first();
        if (! $account) {
            throw ValidationException::withMessages(['product' => 'Antes da contratação, a equipe deve vincular sua conta Pterodactyl. Abra um chamado.']);
        }
        $cfg = self::product(['ptero_config' => json_encode($p->provisioning)]);
        $s->update(['provisioning' => $cfg + ['endpoint' => AaPanelConfig::origin($p->connector->endpoint), 'external_id' => 'lagos-'.Str::uuid(), 'remote_user_id' => $account->remote_user_id, 'email' => $s->user->email]]);
    }
}
