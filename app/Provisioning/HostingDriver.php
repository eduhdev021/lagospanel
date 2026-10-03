<?php

namespace App\Provisioning;

use App\Models\Operation;
use App\Models\Service;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

abstract class HostingDriver
{
    protected function config(Service $s): array
    {
        $current = $s->fresh(['connector']);
        $c = $current?->connector;
        $p = $s->provisioning;
        if (! config('lagos.native_provisioning') || ! $c?->active || ! $c->token || $c->driver !== static::DRIVER || ($p['driver'] ?? '') !== static::DRIVER || ($p['connector_id'] ?? null) !== $current->connector_id || ($p['endpoint'] ?? '') !== $c->endpoint || $current->provisioning !== $p || $s->native_username !== ($p['username'] ?? '') || ! preg_match('/^[a-z]{2}[a-z0-9]{6}$/D', $s->native_username ?? '')) {
            throw new ProtocolError('Configuração de hospedagem ausente, pausada ou divergente.');
        }
        NativeConfig::origin($c->endpoint, static::DRIVER === 'directadmin' ? [2222, 443] : [8443, 443]);
        if (static::DRIVER === 'directadmin' && ($p['creator'] ?? '') !== ($c->settings['username'] ?? '')) {
            throw new ProtocolError('Operador DirectAdmin divergente.');
        }
        $s->setRelation('connector', $c);

        return $p;
    }

    protected function http(): PendingRequest
    {
        return Http::withoutRedirecting()->connectTimeout(3)->timeout(8)->withOptions(['verify' => true, 'on_headers' => function ($r) {
            if ((int) $r->getHeaderLine('Content-Length') > 1048576) {
                throw new ProtocolError('Resposta excessiva.');
            }
        }, 'progress' => function ($total, $received) {
            if ($received > 1048576) {
                throw new ProtocolError('Resposta excessiva.');
            }
        }]);
    }

    protected function secret(Service $s): string
    {
        return DB::transaction(function () use ($s) {
            $row = Service::whereKey($s->id)->lockForUpdate()->firstOrFail();
            if (! $row->provisioning_secret) {
                $row->update(['provisioning_secret' => bin2hex(random_bytes(20)).'aA1!']);
            }

            return $row->provisioning_secret;
        });
    }

    abstract public function observe(Service $s): array;

    abstract protected function mutate(Service $s, string $action, array $before): string;

    public function run(Operation $op): string
    {
        $s = $op->service;
        $this->config($s);
        $target = ['create' => 'active', 'suspend' => 'suspended', 'unsuspend' => 'active', 'terminate' => 'absent'][$op->action] ?? throw new ProtocolError('Ação inválida.');
        if (in_array($op->action, ['create', 'unsuspend'], true) && ! $s->invoices()->where('status', 'paid')->exists()) {
            throw new ProtocolError('Ativação exige pagamento confirmado.');
        }
        $before = $this->observe($s);
        if ($op->action === 'create' && $before['status'] !== 'absent') {
            throw new ProtocolError('Conta existente não será adotada nem sobrescrita.');
        }
        if ($op->action !== 'create' && $before['status'] === $target) {
            return $s->remote_id ?? $before['remote_id'];
        }
        if ($op->action !== 'create' && ! in_array($before['status'], ['active', 'suspended'], true)) {
            throw new ProtocolError('Conta ausente ou em estado não gerenciado.');
        }
        if (Operation::whereKey($op->id)->where('status', 'processing')->where('execution_token', $op->execution_token)->update(['sent_at' => now()]) !== 1) {
            throw new ProtocolError('Execução substituída.');
        }
        $mutationId = $this->mutate($s, $op->action, $before);
        $after = $this->observe($s);
        if ($after['status'] !== $target || ($target !== 'absent' && $after['remote_id'] !== $mutationId)) {
            throw new ProtocolError('Estado final não confirmado. Concilie no provedor.');
        }

        return $after['remote_id'] ?? $before['remote_id'] ?? $s->native_username;
    }
}
