<?php

namespace App\Provisioning;

use App\Models\Service;
use DOMDocument;
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
        $xml = '<?xml version="1.0" encoding="UTF-8"?><packet version="1.6.9.1"><webspace><'.$action.'>'.$body.'</'.$action.'></webspace></packet>';
        $r = $this->http()->withHeaders(['KEY' => $s->connector->token, 'Accept' => 'text/xml'])->withBody($xml, 'text/xml')->post($p['endpoint'].'/enterprise/control/agent.php');
        $raw = $r->body();
        if ($r->status() !== 200 || strlen($raw) > 1048576 || str_contains($raw, "\0") || preg_match('/<!DOCTYPE|<!ENTITY/i', $raw)) {
            throw new ProtocolError('Resposta XML Plesk recusada.');
        }
        $old = libxml_use_internal_errors(true);
        try {
            $doc = new DOMDocument;
            if (! $doc->loadXML($raw, LIBXML_NONET) || $doc->doctype) {
                throw new ProtocolError('XML Plesk inválido.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($old);
        }
        $xp = new DOMXPath($doc);
        $nodes = $xp->query('/packet/webspace/'.$action.'/result');
        if ($nodes->length !== 1 || $xp->query('/packet/webspace/*')->length !== 1 || $xp->query('/packet/*')->length !== 1) {
            throw new ProtocolError('Resposta Plesk ambígua.');
        }

        return $nodes->item(0);
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
