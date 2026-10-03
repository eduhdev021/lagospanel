<?php

namespace App\Provisioning;

use App\Jobs\PollPterodactyl;
use App\Models\Operation;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class PterodactylDriver
{
    private function config(Service $s): array
    {
        $s->load('connector');
        $c = $s->connector;
        $p = $s->provisioning;
        if (! config('lagos.native_provisioning') || ! $c || ! $c->active || $c->driver !== 'pterodactyl') {
            throw new ProtocolError('Chamadas Pterodactyl desativadas.');
        }
        if (! is_array($p) || ($p['driver'] ?? '') !== 'pterodactyl' || ($p['endpoint'] ?? '') !== $c->endpoint || ! preg_match('/^lagos-[a-f0-9-]{36}$/D', $p['external_id'] ?? '') || ! is_int($p['remote_user_id'] ?? null) || $p['remote_user_id'] < 1) {
            throw new ProtocolError('Snapshot Pterodactyl inválido ou divergente.');
        }
        AaPanelConfig::origin($p['endpoint']);

        return $p;
    }

    private function request(Service $s, string $method, string $path, array $data = [], bool $absence = false): ?array
    {
        $p = $this->config($s);
        $r = Http::withToken($s->connector->token)->withHeaders(['Accept' => 'Application/vnd.pterodactyl.v1+json'])->asJson()->withoutRedirecting()->connectTimeout(3)->timeout(8)->withOptions(['verify' => true,
            'on_headers' => function ($r) {
                if ((int) $r->getHeaderLine('Content-Length') > 1048576) {
                    throw new ProtocolError('Resposta Pterodactyl excessiva.');
                }
            },
            'progress' => function ($total, $received) {
                if ($received > 1048576) {
                    throw new ProtocolError('Resposta Pterodactyl excessiva.');
                }
            },
        ])->send($method, $p['endpoint'].'/api/application'.$path, $data ? ['json' => $data] : []);
        $d = $r->json();
        if ($absence && $r->status() === 404 && is_array($d) && ($d['errors'][0]['code'] ?? '') === 'NotFoundHttpException') {
            return null;
        }
        if ($method !== 'GET' && $path !== '/servers') {
            if ($r->status() !== 204) {
                throw new ProtocolError('Mutação Pterodactyl não confirmada.');
            }

            return [];
        }
        if ($method === 'POST' && ($r->status() !== 201 || ($d['object'] ?? '') !== 'server')) {
            throw new ProtocolError('Criação Pterodactyl não confirmada.');
        }
        if (! $r->successful() || strlen($r->body()) > 1048576 || ! is_array($d) || isset($d['errors'])) {
            throw new ProtocolError('Pterodactyl não confirmou a requisição. HTTP '.$r->status().'. Revise o provedor sem copiar a resposta bruta.');
        }

        return $d;
    }

    public function observe(Service $s, bool $clientIdentity = false): array
    {
        $p = $this->config($s);
        // Verify owner before trusting even a 404 from the external server lookup.
        $u = $this->request($s, 'GET', '/users/'.$p['remote_user_id']);
        $a = $u['attributes'] ?? [];
        if (($u['object'] ?? '') !== 'user' || ($a['id'] ?? null) !== $p['remote_user_id'] || ($a['root_admin'] ?? null) !== false || strtolower($a['email'] ?? '') !== strtolower($p['email'])) {
            throw new ProtocolError('Titular Pterodactyl divergente ou administrador remoto.');
        }
        $d = $this->request($s, 'GET', '/servers/external/'.rawurlencode($p['external_id']), [], true);
        $base = ['remote_id' => null, 'checked_at' => now()->toIso8601String()];
        if ($d === null) {
            return ['status' => 'absent'] + $base;
        }
        $a = $d['attributes'] ?? [];
        $id = $a['id'] ?? null;
        if (($d['object'] ?? '') !== 'server' || ! is_int($id) || $id < 1 || ($a['external_id'] ?? null) !== $p['external_id'] || ($a['user'] ?? null) !== $p['remote_user_id'] || ($a['egg'] ?? null) !== $p['egg'] || ($s->remote_id !== null && $s->remote_id !== (string) $id)) {
            throw new ProtocolError('Identidade, titular ou egg Pterodactyl divergente.');
        }
        foreach (['memory', 'disk', 'cpu', 'swap', 'io'] as $key) {
            if (($a['limits'][$key] ?? null) !== $p[$key]) {
                throw new ProtocolError('Recursos Pterodactyl divergem do plano.');
            }
        }
        foreach (['databases', 'allocations', 'backups'] as $key) {
            if (($a['feature_limits'][$key] ?? null) !== $p[$key]) {
                throw new ProtocolError('Limites adicionais Pterodactyl divergentes.');
            }
        }
        if (($a['container']['image'] ?? null) !== $p['docker_image']) {
            throw new ProtocolError('Imagem Pterodactyl divergente.');
        }
        if (! array_key_exists('status', $a) || ! is_bool($a['suspended'] ?? null)) {
            throw new ProtocolError('Estado Pterodactyl inválido.');
        }
        $state = match ($a['status']) {
            null => $a['suspended'] ? 'invalid' : 'active','suspended' => $a['suspended'] ? 'suspended' : 'invalid','installing' => 'installing','install_failed' => 'install_failed',default => 'unavailable'
        };
        if ($state === 'invalid') {
            throw new ProtocolError('Estado Pterodactyl contraditório.');
        }

        $extra = [];
        if ($clientIdentity) {
            if (! is_string($a['uuid'] ?? null) || ! Str::isUuid($a['uuid']) || ($a['identifier'] ?? null) !== substr($a['uuid'], 0, 8)) {
                throw new ProtocolError('Identificador de controle inválido.');
            }
            $extra = ['uuid' => $a['uuid']];
        }

        return ['status' => $state, 'remote_id' => (string) $id, 'checked_at' => $base['checked_at']] + $extra;
    }

    public function run(Operation $op): string
    {
        $s = $op->service;
        $p = $this->config($s);
        $target = ['create' => 'active', 'suspend' => 'suspended', 'unsuspend' => 'active', 'terminate' => 'absent'][$op->action] ?? throw new ProtocolError('Ação Pterodactyl inválida.');
        if (in_array($op->action, ['create', 'unsuspend'], true) && ! $s->invoices()->where('status', 'paid')->exists()) {
            throw new ProtocolError('Ativação exige pagamento.');
        }
        $before = $this->observe($s);
        if ($op->action === 'create' && $before['status'] !== 'absent') {
            throw new ProtocolError('Servidor existente não será adotado automaticamente.');
        }
        if ($op->action !== 'create' && $before['status'] === $target) {
            return $s->remote_id ?? $before['remote_id'];
        }
        if ($op->action !== 'create' && ! in_array($before['status'], ['active', 'suspended'], true)) {
            throw new ProtocolError('Servidor ausente ou em transição. Concilie antes de alterar.');
        }
        $data = [];
        $method = 'POST';
        $path = '/servers';
        if ($op->action === 'create') {
            $data = ['external_id' => $p['external_id'], 'name' => 'LagosPanel '.$s->id, 'description' => $p['external_id'], 'user' => $p['remote_user_id'], 'egg' => $p['egg'], 'docker_image' => $p['docker_image'], 'startup' => $p['startup'], 'environment' => (object) $p['environment'],
                'limits' => array_intersect_key($p, array_flip(['memory', 'disk', 'cpu', 'swap', 'io'])),
                'feature_limits' => array_intersect_key($p, array_flip(['databases', 'allocations', 'backups'])),
                'deploy' => ['locations' => [$p['location']], 'dedicated_ip' => false, 'port_range' => []], 'start_on_completion' => true, 'skip_scripts' => false, 'oom_disabled' => false];
        } else {
            $path .= '/'.$before['remote_id'];
            if ($op->action === 'terminate') {
                $method = 'DELETE';
            } else {
                $path .= '/'.$op->action;
            }
        }
        if (Operation::whereKey($op->id)->where('status', 'processing')->where('execution_token', $op->execution_token)->update(['sent_at' => now()]) !== 1) {
            throw new ProtocolError('Execução Pterodactyl substituída.');
        }
        $mutation = $this->request($s, $method, $path, $data);
        if ($op->action === 'create' && (! is_int($mutation['attributes']['id'] ?? null) || $mutation['attributes']['id'] < 1 || ($mutation['attributes']['external_id'] ?? null) !== $p['external_id'] || ($mutation['attributes']['user'] ?? null) !== $p['remote_user_id'])) {
            throw new ProtocolError('Identidade retornada na criação Pterodactyl diverge do contrato.');
        }
        $after = $this->observe($s);
        if ($op->action === 'create' && $after['remote_id'] !== (string) $mutation['attributes']['id']) {
            throw new ProtocolError('ID criado e ID consultado no Pterodactyl divergem.');
        }
        if ($op->action === 'create' && $after['status'] === 'installing') {
            PollPterodactyl::dispatch($op->id, $op->execution_token)->onConnection('database')->delay(now()->addSeconds(30));
        }
        if ($after['status'] !== $target) {
            throw new ProtocolError('Estado final Pterodactyl ainda não confirmado ('.$after['status'].'). Consulte e concilie após instalação; não repita a criação.');
        }
        if ($op->action !== 'create' && $after['remote_id'] !== null && $after['remote_id'] !== $before['remote_id']) {
            throw new ProtocolError('ID remoto mudou após operação.');
        }

        return $after['remote_id'] ?? $before['remote_id'];
    }
}
