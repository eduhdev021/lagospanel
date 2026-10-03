<?php

namespace App\Jobs;

use App\Models\Connector;
use App\Models\Operation;
use App\Models\Service;
use App\Models\User;
use App\Provisioning\ProtocolError;
use App\Services\Audit;
use App\Services\PleskCustomers;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class PreparePleskCustomer implements ShouldQueue
{
    use Queueable;

    public $tries = 20;

    public $timeout = 45;

    public function __construct(public int $operationId, public string $executionToken) {}

    private function valid(Operation $o, Service $s): void
    {
        $p = $s->provisioning;
        $c = $s->connector;
        $u = $s->user;
        if (! config('lagos.native_provisioning') || ! $c?->active || $c->driver !== 'plesk' || ($p['driver'] ?? '') !== 'plesk' || ! ($p['auto_customer'] ?? false) || ! empty($p['owner_id']) || $p['endpoint'] !== $c->endpoint || $o->action !== 'create' || $o->sent_at || $s->remote_id || $s->status === 'cancelled' || ! $u->hasVerifiedEmail() || strtolower($u->email) !== strtolower($p['email']) || ! $s->invoices()->where('status', 'paid')->exists()) {
            throw new ProtocolError('Preparação de cliente bloqueada.');
        }
    }

    public function handle(): void
    {
        $o = Operation::with('service.connector', 'service.user')->find($this->operationId);
        if (! $o || $o->status !== 'processing' || $o->execution_token !== $this->executionToken) {
            return;
        }
        try {
            $s = $o->service;
            $this->valid($o, $s);
            $r = app(PleskCustomers::class)->prepare($s->connector, $s->user);
            if ($r->status !== 'done') {
                throw new ProtocolError('Cliente requer revisão.');
            }
            DB::transaction(function () use ($r) {
                $o = Operation::whereKey($this->operationId)->lockForUpdate()->firstOrFail();
                if ($o->status !== 'processing' || $o->execution_token !== $this->executionToken) {
                    return;
                }
                $s = Service::whereKey($o->service_id)->lockForUpdate()->firstOrFail();
                $s->setRelation('connector', Connector::whereKey($s->connector_id)->lockForUpdate()->firstOrFail());
                $s->setRelation('user', User::whereKey($s->user_id)->lockForUpdate()->firstOrFail());
                $this->valid($o, $s);
                $r = $r->fresh();
                if ($r->status !== 'done' || $r->connector_id !== $s->connector_id || $r->user_id !== $s->user_id || $r->email !== strtolower($s->user->email) || $r->endpoint !== $s->connector->endpoint) {
                    throw new ProtocolError('Vínculo divergente.');
                }
                $p = $s->provisioning;
                $p['owner_id'] = $r->remote_id;
                $p['customer_request_id'] = $r->id;
                $s->update(['provisioning' => $p]);
                $o->update(['status' => 'pending', 'execution_token' => null, 'error' => null]);
                RunOperation::dispatch($o->id)->onConnection('database')->afterCommit();
                Audit::record('operation.plesk_customer_prepared', 'operation:'.$o->id);
            }, 5);
        } catch (HttpExceptionInterface $e) {
            if ($e->getStatusCode() === 409) {
                $this->release(10);

                return;
            }$this->review();
        } catch (\Throwable) {
            $this->review();
        }
    }

    private function review(): void
    {
        Operation::whereKey($this->operationId)->where('status', 'processing')->where('execution_token', $this->executionToken)->update(['status' => 'review', 'error' => 'Cliente Plesk não confirmado. Confira o provedor e use Retomar preparação de conta; nenhuma assinatura foi enviada nesta etapa.']);
    }

    public function failed(?\Throwable $e): void
    {
        $this->review();
    }
}
