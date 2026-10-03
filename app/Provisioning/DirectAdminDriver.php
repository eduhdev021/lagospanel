<?php

namespace App\Provisioning;

use App\Models\Service;

final class DirectAdminDriver extends HostingDriver
{
    public const DRIVER = 'directadmin';

    private function request(Service $s, string $path, ?array $data = null): ?array
    {
        $p = $this->config($s);
        $http = $this->http()->acceptJson()->withBasicAuth($p['creator'], $s->connector->token);
        $r = $data === null ? $http->get($p['endpoint'].$path) : $http->asForm()->post($p['endpoint'].$path, ['json' => 'yes'] + $data);
        if (strlen($r->body()) > 1048576) {
            throw new ProtocolError('Resposta excessiva.');
        }$d = $r->json();
        if ($data === null && $r->status() === 404 && is_array($d) && ($d['type'] ?? '') === 'NOT_FOUND') {
            return null;
        }
        if ($r->status() !== 200 || ! is_array($d) || isset($d['type']) || ($data !== null && ! in_array($d['error'] ?? null, [0, '0'], true))) {
            throw new ProtocolError('DirectAdmin não confirmou a requisição.');
        }

        return $d;
    }

    public function observe(Service $s): array
    {
        $p = $this->config($s);
        $a = $this->request($s, '/api/users/'.rawurlencode($p['username']).'/config');
        $base = ['remote_id' => null, 'checked_at' => now()->toIso8601String()];
        if ($a === null) {
            return ['status' => 'absent'] + $base;
        }
        foreach (['username' => 'username', 'creator' => 'creator', 'domain' => 'domain', 'email' => 'email', 'package' => 'plan', 'ip' => 'ip'] as $remote => $local) {
            if (($a[$remote] ?? null) !== $p[$local]) {
                throw new ProtocolError('Identidade, pacote ou IP DirectAdmin divergente.');
            }
        }
        if (($a['userType'] ?? '') !== 'user' || ! is_bool($a['suspended'] ?? null) || ($s->remote_id !== null && $s->remote_id !== $p['username'])) {
            throw new ProtocolError('Tipo, estado ou ID DirectAdmin divergente.');
        }

        return ['status' => $a['suspended'] ? 'suspended' : 'active', 'remote_id' => $p['username'], 'checked_at' => $base['checked_at']];
    }

    protected function mutate(Service $s, string $action, array $before): string
    {
        $p = $this->config($s);
        if ($action === 'create') {
            $secret = $this->secret($s);
            $this->request($s, '/CMD_API_ACCOUNT_USER', ['action' => 'create', 'add' => 'Submit', 'username' => $p['username'], 'email' => $p['email'], 'passwd' => $secret, 'passwd2' => $secret, 'domain' => $p['domain'], 'package' => $p['plan'], 'ip' => $p['ip'], 'notify' => 'no']);

            return $p['username'];
        }
        $data = ['select0' => $p['username']];
        $data += $action === 'terminate' ? ['confirmed' => 'Confirm', 'delete' => 'yes'] : [$action === 'suspend' ? 'dosuspend' : 'dounsuspend' => 'yes'];
        $this->request($s, '/CMD_API_SELECT_USERS', $data);

        return $p['username'];
    }
}
