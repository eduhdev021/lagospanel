<?php

namespace App\Provisioning;

use App\Models\Operation;
use Illuminate\Support\Facades\Http;

final class JsonDriver
{
    public function run(Operation $op): string
    {
        $s = $op->service;
        $c = $s->connector;
        $response = Http::withToken($c->token)->acceptJson()->withoutRedirecting()->timeout(20)->withHeaders(['Idempotency-Key' => $op->reference])->post(rtrim($c->endpoint, '/').'/'.$op->action, ['service_id' => $s->id, 'external_id' => 'lagos-'.$s->id, 'remote_id' => $s->remote_id, 'name' => $s->name, 'configuration' => $s->configuration ?? [], 'customer_email' => $s->user->email]);
        $data = $response->json();
        if (! $response->successful() || ! is_array($data) || ($data['success'] ?? null) !== true) {
            throw new ProtocolError('Provedor JSON não confirmou a operação. HTTP '.$response->status());
        }
        $remote = $data['remote_id'] ?? $s->remote_id ?? '';
        if (is_int($remote)) {
            $remote = (string) $remote;
        }
        if (! is_string($remote) || strlen($remote) > 255 || ($op->action === 'create' && $remote === '')) {
            throw new ProtocolError('Resposta JSON sem ID remoto válido.');
        }

        return $remote;
    }
}
