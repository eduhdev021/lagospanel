<?php

namespace App\Provisioning;

use App\Models\Operation;
use App\Models\Service;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;

final class AaPanelDriver
{
    private CookieJar $cookies;

    public function __construct()
    {
        $this->cookies = new CookieJar;
    }

    public function config(Service $s): array
    {
        $s->load('connector');
        $c = $s->connector;
        $p = $s->provisioning;
        if (! config('lagos.native_provisioning') || ! $c || ! $c->active || $c->driver !== 'aapanel') {
            throw new ProtocolError('Chamadas aaPanel desativadas.');
        }
        if (! is_array($p) || ($p['driver'] ?? '') !== 'aapanel' || ($p['endpoint'] ?? '') !== $c->endpoint || ($p['path'] ?? '') !== '/www/wwwroot/'.($p['domain'] ?? '').'-'.substr($p['marker'] ?? '', 11) || ! preg_match('/^LagosPanel:[a-f0-9-]{36}$/D', $p['marker'] ?? '')) {
            throw new ProtocolError('Snapshot aaPanel ausente ou divergente.');
        }
        AaPanelConfig::origin($p['endpoint']);

        return $p;
    }

    private function request(Service $s, string $route, array $data = []): array
    {
        $p = $this->config($s);
        $time = now()->timestamp;
        $r = Http::acceptJson()->asForm()->withoutRedirecting()->connectTimeout(5)->timeout(10)->withOptions(['verify' => true, 'cookies' => $this->cookies,
            'on_headers' => function ($r) {
                if ((int) $r->getHeaderLine('Content-Length') > 1048576) {
                    throw new ProtocolError('Resposta aaPanel excessiva.');
                }
            },
            'progress' => function ($total, $received) {
                if ($received > 1048576) {
                    throw new ProtocolError('Resposta aaPanel excessiva.');
                }
            },
        ])->post($p['endpoint'].$route, $data + ['request_time' => $time, 'request_token' => md5((string) $time.md5($s->connector->token))]);
        if (! $r->successful() || strlen($r->body()) > 1048576 || ! is_array($d = $r->json()) || (array_key_exists('status', $d) && $d['status'] !== true)) {
            throw new ProtocolError('aaPanel não confirmou a requisição. HTTP '.$r->status().'. Consulte o provedor sem copiar a saída bruta.');
        }

        return $d;
    }

    public function observe(Service $s): array
    {
        $p = $this->config($s);
        $d = $this->request($s, '/data?action=getData&table=sites', ['p' => 1, 'limit' => 100, 'search' => $p['domain'], 'order' => 'id desc']);
        $rows = $d['data'] ?? null;
        if (! is_array($rows) || ! array_is_list($rows) || count($rows) >= 100) {
            throw new ProtocolError('Listagem aaPanel incompleta ou saturada; ausência não foi comprovada.');
        }
        $matches = [];
        foreach ($rows as $r) {
            if (! is_array($r) || ! isset($r['name']) || ! is_string($r['name'])) {
                throw new ProtocolError('Listagem aaPanel inválida.');
            }if ($r['name'] === $p['domain']) {
                $matches[] = $r;
            }
        }
        if (count($matches) > 1) {
            throw new ProtocolError('Identidade aaPanel ambígua.');
        }
        if (! $matches && $rows) {
            throw new ProtocolError('Busca aaPanel não retornou identidade exata; ausência não comprovada.');
        }
        if (! $matches) {
            return ['status' => 'absent', 'remote_id' => null, 'checked_at' => now()->toIso8601String()];
        }
        $r = $matches[0];
        $id = $r['id'] ?? null;
        if ((! is_int($id) && ! is_string($id)) || ! preg_match('/^[1-9][0-9]{0,18}$/D', (string) $id) || ($r['path'] ?? null) !== $p['path'] || ($r['ps'] ?? null) !== $p['marker'] || ($s->remote_id !== null && $s->remote_id !== (string) $id) || ! in_array($r['status'] ?? null, [0, 1, '0', '1'], true)) {
            throw new ProtocolError('ID, diretório, marcador ou estado aaPanel diverge do serviço. Nenhuma mutação autorizada.');
        }

        return ['status' => (string) $r['status'] === '1' ? 'active' : 'suspended', 'remote_id' => (string) $id, 'domain' => $p['domain'], 'checked_at' => now()->toIso8601String()];
    }

    public function run(Operation $op): string
    {
        $s = $op->service;
        $p = $this->config($s);
        if (! in_array($op->action, ['create', 'suspend', 'unsuspend', 'terminate'], true)) {
            throw new ProtocolError('Ação aaPanel desconhecida.');
        }
        if (in_array($op->action, ['create', 'unsuspend'], true) && ! $s->invoices()->where('status', 'paid')->exists()) {
            throw new ProtocolError('Ativação aaPanel exige pagamento.');
        }
        $before = $this->observe($s);
        $target = ['create' => 'active', 'suspend' => 'suspended', 'unsuspend' => 'active', 'terminate' => 'absent'][$op->action];
        if ($op->action === 'create' && $before['status'] !== 'absent') {
            throw new ProtocolError('Site existente não será adotado automaticamente.');
        }
        if ($op->action !== 'create' && $before['status'] === $target) {
            return $s->remote_id ?? $before['remote_id'];
        }
        if ($op->action !== 'create' && $before['status'] === 'absent') {
            throw new ProtocolError('Site aaPanel ausente para esta operação.');
        }
        if ($op->action === 'create') {
            $versions = $this->request($s, '/site?action=GetPHPVersion');
            $found = false;
            if (! array_is_list($versions)) {
                throw new ProtocolError('Lista de versões PHP inválida.');
            }
            foreach ($versions as $v) {
                if (is_array($v) && (string) ($v['version'] ?? '') === $p['php_version']) {
                    $found = true;
                }
            }
            if (! $found) {
                throw new ProtocolError('Versão PHP selecionada não está instalada no aaPanel.');
            }
            $action = 'AddSite';
            $data = ['webname' => json_encode(['domain' => $p['domain'], 'domainlist' => [], 'count' => 0]), 'path' => $p['path'], 'type_id' => 0, 'type' => 'PHP', 'version' => $p['php_version'], 'port' => 80, 'ps' => $p['marker'], 'ftp' => 'false', 'sql' => 'false'];
        } elseif ($op->action === 'terminate') {
            $action = 'DeleteSite';
            $data = ['id' => $before['remote_id'], 'webname' => $p['domain']];
        } else {
            $action = $op->action === 'suspend' ? 'SiteStop' : 'SiteStart';
            $data = ['id' => $before['remote_id'], 'name' => $p['domain']];
        }
        if (Operation::whereKey($op->id)->where('status', 'processing')->where('execution_token', $op->execution_token)->update(['sent_at' => now()]) !== 1) {
            throw new ProtocolError('Execução aaPanel substituída; envio bloqueado.');
        }
        $response = $this->request($s, '/site?action='.$action, $data);
        if ($op->action === 'create' && (($response['ftpStatus'] ?? false) === true || ($response['databaseStatus'] ?? false) === true)) {
            throw new ProtocolError('aaPanel reportou recursos extras não solicitados. Revise no provedor.');
        }
        if (($response[$op->action === 'create' ? 'siteStatus' : 'status'] ?? null) !== true) {
            throw new ProtocolError('aaPanel não confirmou a mutação.');
        }
        $after = $this->observe($s);
        if ($after['status'] !== $target || ($op->action !== 'create' && $after['remote_id'] !== null && $after['remote_id'] !== $before['remote_id'])) {
            throw new ProtocolError('Estado final aaPanel não confirmado.');
        }

        return $after['remote_id'] ?? $before['remote_id'];
    }
}
