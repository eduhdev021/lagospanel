<?php

namespace App\Provisioning;

use App\Models\Operation;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class CpanelDriver
{
    public function config(Service $s): array
    {
        $s->load('connector');
        $c = $s->connector;
        $p = $s->provisioning;
        if (! config('lagos.native_provisioning') || ! $c || ! $c->active || $c->driver !== 'cpanel') {
            throw new ProtocolError('Provisionamento nativo desativado. Confira a configuração local e a integração.');
        }
        if (! is_array($p) || ($p['driver'] ?? null) !== 'cpanel' || ($p['endpoint'] ?? '') !== $c->endpoint || ($p['whm_user'] ?? '') !== ($c->settings['username'] ?? '') || ($p['username'] ?? '') !== $s->native_username || ! preg_match('/^[a-z]{2}[a-z0-9]{6}$/D', $s->native_username ?? '')) {
            throw new ProtocolError('Snapshot nativo ausente ou divergente; não será utilizado um destino diferente.');
        }
        NativeConfig::origin($p['endpoint'], [2087, 443]);
        if ($s->remote_id !== null && $s->remote_id !== $s->native_username) {
            throw new ProtocolError('Vínculo remoto divergente.');
        }

        return $p;
    }

    private function request(Service $s, string $method, array $parameters): array
    {
        $p = $this->config($s);
        $r = Http::acceptJson()->asForm()->withHeaders(['Authorization' => 'whm '.$p['whm_user'].':'.$s->connector->token])->withOptions(['verify' => true,
            'on_headers' => function ($response) {
                if ((int) $response->getHeaderLine('Content-Length') > 1048576) {
                    throw new ProtocolError('Resposta WHM excede o limite seguro.');
                }
            },
            'progress' => function ($total, $received) {
                if ($received > 1048576) {
                    throw new ProtocolError('Resposta WHM excede o limite seguro.');
                }
            },
        ])->withoutRedirecting()->connectTimeout(5)->timeout(15)->post($p['endpoint'].'/json-api/'.$method, ['api.version' => 1] + $parameters);
        if (strlen($r->body()) > 1048576) {
            throw new ProtocolError('Resposta WHM excede o limite seguro.');
        }
        $d = $r->json();
        if (! $r->successful() || ! is_array($d) || ! in_array($d['metadata']['result'] ?? null, [1, '1'], true) || ($d['metadata']['command'] ?? null) !== $method || ! in_array($d['metadata']['version'] ?? null, [1, '1'], true)) {
            throw new ProtocolError('WHM não confirmou '.$method.'. HTTP '.$r->status().'. Revise no servidor; a saída bruta não é armazenada.');
        }

        return $d;
    }

    public function observe(Service $s): array
    {
        $p = $this->config($s);
        $d = $this->request($s, 'listaccts', ['searchtype' => 'user', 'searchmethod' => 'exact', 'search' => $p['username'], 'want' => 'user,domain,owner,plan,email,suspended']);
        $accounts = $d['data']['acct'] ?? null;
        if (! is_array($accounts) || ! array_is_list($accounts) || count($accounts) > 1) {
            throw new ProtocolError('Consulta WHM ambígua ou incompleta.');
        }
        if (count($accounts) === 0) {
            return ['status' => 'absent', 'username' => $p['username'], 'checked_at' => now()->toIso8601String()];
        }
        $a = $accounts[0];
        foreach (['user' => 'username', 'domain' => 'domain', 'owner' => 'whm_user', 'plan' => 'plan', 'email' => 'email'] as $remote => $local) {
            if (! isset($a[$remote]) || ! is_string($a[$remote]) || $a[$remote] !== $p[$local]) {
                throw new ProtocolError('Identidade/pacote da conta WHM diverge do contrato. Nenhuma alteração foi autorizada.');
            }
        }
        if (! in_array($a['suspended'] ?? null, [0, 1, '0', '1'], true)) {
            throw new ProtocolError('Estado WHM inválido.');
        }

        return ['status' => (string) $a['suspended'] === '1' ? 'suspended' : 'active', 'username' => $p['username'], 'domain' => $p['domain'], 'plan' => $p['plan'], 'checked_at' => now()->toIso8601String()];
    }

    public function run(Operation $op): string
    {
        $s = $op->service;
        $p = $this->config($s);
        if (! in_array($op->action, ['create', 'suspend', 'unsuspend', 'terminate'], true)) {
            throw new ProtocolError('Ação nativa não suportada.');
        }
        if (in_array($op->action, ['create', 'unsuspend'], true) && ! $s->invoices()->where('status', 'paid')->exists()) {
            throw new ProtocolError('Ativação nativa exige pagamento confirmado.');
        }
        $before = $this->observe($s);
        if ($op->action === 'create' && $before['status'] !== 'absent') {
            throw new ProtocolError('Conta já existe no WHM. Não será adotada ou sobrescrita automaticamente.');
        }
        $target = ['create' => 'active', 'suspend' => 'suspended', 'unsuspend' => 'active', 'terminate' => 'absent'][$op->action];
        if ($op->action !== 'create' && $before['status'] === $target) {
            return $p['username'];
        }
        if ($op->action !== 'create' && $before['status'] === 'absent') {
            throw new ProtocolError('Conta não localizada para esta ação.');
        }
        if ($op->action === 'create') {
            $secret = DB::transaction(function () use ($s) {
                $row = Service::lockForUpdate()->findOrFail($s->id);
                if (! $row->provisioning_secret) {
                    $row->update(['provisioning_secret' => bin2hex(random_bytes(20)).'aA1!']);
                }

                return $row->provisioning_secret;
            });
            $method = 'createacct';
            $params = ['username' => $p['username'], 'domain' => $p['domain'], 'plan' => $p['plan'], 'owner' => $p['whm_user'], 'contactemail' => $p['email'], 'password' => $secret, 'forcedns' => 0, 'savepkg' => 0];
        } elseif ($op->action === 'terminate') {
            $method = 'removeacct';
            $params = ['username' => $p['username'], 'keepdns' => 0];
        } else {
            $method = $op->action === 'suspend' ? 'suspendacct' : 'unsuspendacct';
            $params = ['user' => $p['username']];
            if ($op->action === 'suspend') {
                $params['reason'] = 'LagosPanel service '.$s->id;
            }
        }
        if (Operation::whereKey($op->id)->where('status', 'processing')->where('execution_token', $op->execution_token)->update(['sent_at' => now()]) !== 1) {
            throw new ProtocolError('Execução substituída; envio bloqueado.');
        }
        $this->request($s, $method, $params);
        $after = $this->observe($s);
        if ($after['status'] !== $target) {
            throw new ProtocolError('WHM respondeu, mas o estado final não foi confirmado. Conciliação necessária.');
        }

        return $p['username'];
    }
}
