<?php

namespace App\Jobs;

use App\Models\Connector;
use App\Models\Operation;
use App\Models\PterodactylAccount;
use App\Models\PterodactylAccountRequest;
use App\Models\Service;
use App\Models\User;
use App\Provisioning\ProtocolError;
use App\Services\Audit;
use App\Services\PterodactylUsers;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class PreparePterodactylAccount implements ShouldQueue
{
    use Queueable;

    public $tries = 20;

    public $timeout = 45;

    public function __construct(public int $operationId, public string $executionToken) {}

    private function valid(Operation $op, Service $s): void
    {
        $p = $s->provisioning;
        $c = $s->connector;
        $u = $s->user;
        if (! config('lagos.native_provisioning') || ! $c?->active || $c->driver !== 'pterodactyl' || ($p['driver'] ?? '') !== 'pterodactyl' || ($p['endpoint'] ?? '') !== $c->endpoint || ! ($p['auto_account'] ?? false) || ! empty($p['remote_user_id']) || $op->action !== 'create' || $op->sent_at || $s->remote_id || $s->status === 'cancelled' || ! $u->hasVerifiedEmail() || strtolower($u->email) !== strtolower($p['email'] ?? '') || ! $s->invoices()->where('status', 'paid')->exists()) {
            throw new ProtocolError('Preparação de conta bloqueada. Confira pagamento, identidade e integração.');
        }
    }

    public function handle(): void
    {
        $op = Operation::with('service.connector', 'service.user')->find($this->operationId);
        if (! $op || $op->status !== 'processing' || $op->execution_token !== $this->executionToken) {
            return;
        }
        try {
            $s = $op->service;
            $this->valid($op, $s);
            $account = PterodactylAccount::where('connector_id', $s->connector_id)->where('user_id', $s->user_id)->first();
            if (! $account) {
                $request = PterodactylAccountRequest::where('connector_id', $s->connector_id)->where('user_id', $s->user_id)->first();
                $names = $request ? ['first_name' => $request->first_name, 'last_name' => $request->last_name] : $s->provisioning['account_names'];
                $result = app(PterodactylUsers::class)->create($s->connector, $s->user, $names, $s->user_id);
                if ($result->status !== 'done') {
                    throw new ProtocolError('Conta remota requer conferência; criação de servidor não foi enviada.');
                }
                $account = PterodactylAccount::where('connector_id', $s->connector_id)->where('user_id', $s->user_id)->firstOrFail();
            }
            DB::transaction(function () use ($account) {
                $op = Operation::whereKey($this->operationId)->lockForUpdate()->firstOrFail();
                if ($op->status !== 'processing' || $op->execution_token !== $this->executionToken) {
                    return;
                }
                $s = Service::whereKey($op->service_id)->lockForUpdate()->firstOrFail();
                $s->setRelation('connector', Connector::whereKey($s->connector_id)->lockForUpdate()->firstOrFail());
                $s->setRelation('user', User::whereKey($s->user_id)->lockForUpdate()->firstOrFail());
                $this->valid($op, $s);
                $current = PterodactylAccount::whereKey($account->id)->firstOrFail();
                if ((int) $current->connector_id !== (int) $s->connector_id || (int) $current->user_id !== (int) $s->user_id || $current->remote_user_id !== $account->remote_user_id) {
                    throw new ProtocolError('Vínculo alterado.');
                }
                $p = $s->provisioning;
                $p['remote_user_id'] = $current->remote_user_id;
                $s->update(['provisioning' => $p]);
                $op->update(['status' => 'pending', 'execution_token' => null, 'error' => null]);
                Audit::record('operation.account_prepared', 'operation:'.$op->id, ['remote_user_id' => $current->remote_user_id]);
                RunOperation::dispatch($op->id)->onConnection('database')->afterCommit();
            }, 5);
        } catch (HttpExceptionInterface $e) {
            $busy = $e->getStatusCode() === 409 && PterodactylAccountRequest::where('connector_id', $op->service->connector_id)->where('user_id', $op->service->user_id)->where('status', 'processing')->where('started_at', '>', now()->subMinutes(2))->exists();
            $linked = $e->getStatusCode() === 409 && PterodactylAccount::where('connector_id', $op->service->connector_id)->where('user_id', $op->service->user_id)->exists();
            if ($busy || $linked) {
                $this->release(10);

                return;
            }
            $this->review();
        } catch (Throwable) {
            $this->review();
        }
    }

    private function review(): void
    {
        $changed = Operation::whereKey($this->operationId)->where('status', 'processing')->where('execution_token', $this->executionToken)->update(['status' => 'review', 'error' => 'Preparação de conta Pterodactyl não concluída. Confira a conta remota e use Retomar preparação; nenhum servidor foi criado nesta etapa.']);
        if ($changed) {
            Audit::record('operation.account_review', 'operation:'.$this->operationId);
        }
    }

    public function failed(?Throwable $e): void
    {
        $this->review();
    }
}
