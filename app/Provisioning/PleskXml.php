<?php

namespace App\Provisioning;

use App\Models\Connector;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;

final class PleskXml
{
    public static function esc(string|int $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    public static function value(DOMElement $node, string $path): string
    {
        $nodes = (new DOMXPath($node->ownerDocument))->query($path, $node);
        if ($nodes->length !== 1) {
            throw new ProtocolError('Campo XML ausente ou ambíguo.');
        }

        return $nodes->item(0)->textContent;
    }

    public static function id(string $id): int
    {
        if (! preg_match('/^[1-9][0-9]{0,9}$/D', $id) || (int) $id > 2147483647) {
            throw new ProtocolError('ID Plesk inválido.');
        }

        return (int) $id;
    }

    public function request(Connector $connector, string $endpoint, string $operator, string $action, string $body): DOMElement
    {
        $c = $connector->fresh();
        if (! config('lagos.native_provisioning') || ! $c?->active || $c->driver !== 'plesk' || ! $c->token || $c->endpoint !== $endpoint) {
            throw new ProtocolError('Integração Plesk desativada ou alterada.');
        }
        NativeConfig::origin($endpoint, [8443, 443]);
        $allowed = ['webspace' => ['get', 'add', 'set', 'del'], 'customer' => ['get', 'add'], 'server' => ['create_session']];
        if (! in_array($action, $allowed[$operator] ?? [], true)) {
            throw new ProtocolError('Operação XML não autorizada.');
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?><packet version="1.6.9.1"><'.$operator.'><'.$action.'>'.$body.'</'.$action.'></'.$operator.'></packet>';
        $r = Http::withoutRedirecting()->connectTimeout(3)->timeout(8)->withOptions(['verify' => true, 'on_headers' => function ($r) {
            if ((int) $r->getHeaderLine('Content-Length') > 1048576) {
                throw new ProtocolError('Resposta excessiva.');
            }
        }, 'progress' => function ($total, $received) {
            if ($received > 1048576) {
                throw new ProtocolError('Resposta excessiva.');
            }
        }])->withHeaders(['KEY' => $c->token, 'Accept' => 'text/xml'])->withBody($xml, 'text/xml')->post($endpoint.'/enterprise/control/agent.php');
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
        $nodes = $xp->query('/packet/'.$operator.'/'.$action.'/result');
        if ($nodes->length !== 1 || $xp->query('/packet/'.$operator.'/*')->length !== 1 || $xp->query('/packet/*')->length !== 1) {
            throw new ProtocolError('Resposta Plesk ambígua.');
        }

        return $nodes->item(0);
    }
}
