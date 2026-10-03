<?php

namespace App\Provisioning;

use App\Models\PleskCustomerRequest;
use App\Models\Service;
use App\Services\PleskCustomers;
use DOMElement;
use DOMXPath;

final class PleskDriver extends HostingDriver
{
    public const DRIVER = 'plesk';

    private static function esc(string|int $s): string
    {
        return htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function request(Service $s, string $action, string $body): DOMElement
    {
        $p = $this->config($s);

        return app(PleskXml::class)->request($s->connector, $p['endpoint'], 'webspace', $action, $body);
    }

    private function value(DOMElement $node, string $path): string
    {
        $nodes = (new DOMXPath($node->ownerDocument))->query($path, $node);
        if ($nodes->length !== 1) {
            throw new ProtocolError('Campo XML ausente ou ambíguo.');
        }

        return $nodes->item(0)->textContent;
    }

    public function observe(Service $s): array
    {
        $p = $this->config($s);
        $r = $this->request($s, 'get', '<filter><name>'.self::esc($p['domain']).'</name></filter><dataset><gen_info/><hosting/><subscriptions/></dataset>');
        $status = $this->value($r, 'status');
        $base = ['remote_id' => null, 'checked_at' => now()->toIso8601String()];
        if ($status === 'error' && $this->value($r, 'errcode') === '1013') {
            return ['status' => 'absent'] + $base;
        }
        if ($status !== 'ok') {
            throw new ProtocolError('Consulta Plesk não confirmada.');
        }
        foreach (['name' => 'domain', 'owner-id' => 'owner_id', 'external-id' => 'external_id', 'dns_ip_address' => 'ip'] as $remote => $local) {
            if ($this->value($r, 'data/gen_info/'.$remote) !== (string) $p[$local]) {
                throw new ProtocolError('Identidade ou destino Plesk divergente.');
            }
        }
        $id = $this->value($r, 'id');
        if (! preg_match('/^[1-9][0-9]{0,9}$/D', $id) || (int) $id > 2147483647 || ($s->remote_id !== null && $s->remote_id !== $id) || $this->value($r, 'data/gen_info/htype') !== 'vrt_hst' || $this->value($r, 'data/hosting/vrt_hst/property[name="ftp_login"]/value') !== $p['username'] || $this->value($r, 'data/subscriptions/subscription/plan/plan-guid') !== $p['plan_guid']) {
            throw new ProtocolError('ID, login ou plano Plesk divergente.');
        }
        $state = match ($this->value($r, 'data/gen_info/status')) {
            '0' => 'active','16' => 'suspended',default => 'unavailable'
        };

        return ['status' => $state, 'remote_id' => $id, 'checked_at' => $base['checked_at']];
    }

    private function sessionService(Service $original): Service
    {
        $s = $original->fresh();
        if (! $s || $s->user_id !== $original->user_id || $s->status !== 'active' || ! $s->remote_id || $s->provisioning !== $original->provisioning || ! ($s->provisioning['auto_customer'] ?? false) || empty($s->provisioning['customer_request_id']) || $s->operations()->whereIn('status', ['pending', 'processing', 'review', 'reconciling'])->exists()) {
            throw new ProtocolError('Acesso indisponível.');
        }
        $this->config($s);

        return $s;
    }

    private function customer(Service $s): PleskCustomerRequest
    {
        $p = $s->provisioning;
        $r = PleskCustomerRequest::findOrFail($p['customer_request_id']);
        if ($r->user_id !== $s->user_id || $r->connector_id !== $s->connector_id || $r->endpoint !== $p['endpoint'] || $r->remote_id !== $p['owner_id']) {
            throw new ProtocolError('Vínculo de cliente divergente.');
        }
        app(PleskCustomers::class)->verify($r);

        return $r;
    }

    public function session(Service $original, string $ip): string
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new ProtocolError('IP inválido.');
        }
        $s = $this->sessionService($original);
        $r = $this->customer($s);
        if ($this->observe($s)['status'] !== 'active') {
            throw new ProtocolError('Assinatura remota inativa.');
        }
        $s = $this->sessionService($original);
        $result = app(PleskXml::class)->request($s->connector, $r->endpoint, 'server', 'create_session', '<login>'.self::esc($r->login).'</login><data><user_ip>'.base64_encode($ip).'</user_ip><source_server/></data>');
        if (PleskXml::value($result, 'status') !== 'ok') {
            throw new ProtocolError('Sessão recusada.');
        }
        $token = PleskXml::value($result, 'id');
        if (! preg_match('/^[A-Za-z0-9_-]{20,256}$/D', $token)) {
            throw new ProtocolError('Token de sessão inválido.');
        }
        $s = $this->sessionService($original);
        $this->customer($s);
        if ($this->observe($s)['status'] !== 'active') {
            throw new ProtocolError('Assinatura remota inativa.');
        }
        $this->sessionService($original);

        return $r->endpoint.'/enterprise/rsession_init.php?PLESKSESSID='.rawurlencode($token);
    }

    protected function mutate(Service $s, string $action, array $before): string
    {
        $p = $this->config($s);
        if ($action === 'create') {
            $body = '<gen_setup><name>'.self::esc($p['domain']).'</name><owner-id>'.$p['owner_id'].'</owner-id><htype>vrt_hst</htype><ip_address>'.self::esc($p['ip']).'</ip_address><status>0</status><external-id>'.self::esc($p['external_id']).'</external-id></gen_setup><hosting><vrt_hst><property><name>ftp_login</name><value>'.self::esc($p['username']).'</value></property><property><name>ftp_password</name><value>'.self::esc($this->secret($s)).'</value></property><ip_address>'.self::esc($p['ip']).'</ip_address></vrt_hst></hosting><plan-guid>'.self::esc($p['plan_guid']).'</plan-guid>';
            $verb = 'add';
        } else {
            $body = '<filter><id>'.self::esc($before['remote_id']).'</id></filter>';
            $verb = $action === 'terminate' ? 'del' : 'set';
            if ($verb === 'set') {
                $body .= '<values><gen_setup><status>'.($action === 'suspend' ? '16' : '0').'</status></gen_setup></values>';
            }
        }
        $r = $this->request($s, $verb, $body);
        if ($this->value($r, 'status') !== 'ok') {
            throw new ProtocolError('Mutação Plesk não confirmada.');
        }
        $id = $this->value($r, 'id');
        if (! preg_match('/^[1-9][0-9]{0,9}$/D', $id) || (int) $id > 2147483647 || ($action !== 'create' && $id !== $before['remote_id'])) {
            throw new ProtocolError('ID da mutação Plesk inválido.');
        }

        return $id;
    }
}
