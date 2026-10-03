<?php

namespace App\Services;

use App\Models\PterodactylControl;
use App\Models\Service;
use App\Provisioning\AaPanelConfig;
use App\Provisioning\ProtocolError;
use App\Provisioning\PterodactylDriver;
use Illuminate\Support\Facades\Http;

final class PterodactylPower
{
    private function current(Service $original): Service
    {
        $s = $original->fresh(['connector']);
        if (! $s || $s->user_id !== $original->user_id || $s->status !== 'active' || ! $s->remote_id || $s->remote_id !== $original->remote_id || $s->provisioning !== $original->provisioning || ! config('lagos.native_provisioning') || ! $s->connector?->active || $s->connector->driver !== 'pterodactyl' || ($s->provisioning['endpoint'] ?? '') !== $s->connector->endpoint || $s->operations()->whereIn('status', ['pending', 'processing', 'review', 'reconciling'])->exists()) {
            throw new ProtocolError('Controle indisponível para este serviço.');
        }
        AaPanelConfig::origin($s->connector->endpoint);

        return $s;
    }

    private function http(Service $s, string $key, string $method, string $path, ?array $data = null): array
    {
        $s = $this->current($s);
        $r = Http::withToken($key)->acceptJson()->asJson()->withoutRedirecting()->connectTimeout(3)->timeout(8)->withOptions(['verify' => true,
            'on_headers' => function ($r) {
                if ((int) $r->getHeaderLine('Content-Length') > 1048576) {
                    throw new ProtocolError('Resposta excessiva.');
                }
            },
            'progress' => function ($total, $received) {
                if ($received > 1048576) {
                    throw new ProtocolError('Resposta excessiva.');
                }
            },
        ])->send($method, $s->connector->endpoint.'/api/client'.$path, $data === null ? [] : ['json' => $data]);
        if (strlen($r->body()) > 1048576 || $r->status() !== ($method === 'POST' ? 204 : 200)) {
            throw new ProtocolError('Controle remoto não confirmado.');
        }
        if ($method === 'POST') {
            return [];
        }
        $body = $r->json();
        if (! is_array($body) || isset($body['errors'])) {
            throw new ProtocolError('Resposta remota inválida.');
        }

        return $body;
    }

    public function run(Service $s, PterodactylControl $control, string $key): void
    {
        if (! in_array($control->signal, ['start', 'stop', 'restart'], true) || $control->service_id !== $s->id || $control->user_id !== $s->user_id || $control->status !== 'processing' || $control->sent_at) {
            throw new ProtocolError('Solicitação inválida.');
        }
        $s = $this->current($s);
        $p = $s->provisioning;
        $u = $this->http($s, $key, 'GET', '/account');
        $a = $u['attributes'] ?? [];
        if (($u['object'] ?? '') !== 'user' || ($a['id'] ?? null) !== ($p['remote_user_id'] ?? null) || ($a['admin'] ?? null) !== false || strtolower($a['email'] ?? '') !== strtolower($p['email'] ?? '')) {
            throw new ProtocolError('Use somente a chave Client API da sua conta vinculada, não uma chave administrativa.');
        }
        $remote = app(PterodactylDriver::class)->observe($s, true);
        if ($remote['status'] !== 'active') {
            throw new ProtocolError('Servidor suspenso ou em transição.');
        }
        $s = $this->current($s);
        if (PterodactylControl::whereKey($control->id)->where('status', 'processing')->whereNull('sent_at')->update(['sent_at' => now()]) !== 1) {
            throw new ProtocolError('Solicitação já enviada.');
        }
        $this->http($s, $key, 'POST', '/servers/'.rawurlencode($remote['uuid']).'/power', ['signal' => $control->signal]);
        $control->update(['status' => 'accepted']);
    }
}
