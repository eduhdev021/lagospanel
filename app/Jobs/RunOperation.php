<?php

namespace App\Jobs;

use App\Models\Operation;
use App\Models\Service;
use App\Provisioning\AaPanelDriver;
use App\Provisioning\CpanelDriver;
use App\Provisioning\JsonDriver;
use App\Provisioning\ProtocolError;
use App\Provisioning\PterodactylDriver;
use App\Services\Audit;
use App\Services\Provisioning;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RunOperation implements ShouldQueue
{
    use Queueable;

    public $tries = 1;

    public $timeout = 60;

    public string $executionToken = '';

    public function __construct(public int $operationId)
    {
        $this->executionToken = (string) Str::uuid();
    }

    private function token(): string
    {
        return $this->executionToken ?: 'legacy-'.str_pad((string) $this->operationId, 20, '0', STR_PAD_LEFT);
    }

    public function handle(): void
    {
        $op = DB::transaction(function () {
            $op = Operation::lockForUpdate()->findOrFail($this->operationId);
            if ($op->status !== 'pending') {
                return null;
            }
            $service = Service::lockForUpdate()->findOrFail($op->service_id);
            if ($service->status === 'cancelled' || ($op->action === 'create' && $service->remote_id)) {
                $op->update(['status' => 'done']);

                return null;
            }
            if ($op->action === 'suspend' && str_starts_with($op->reference, 'overdue:') && ! $service->invoices()->whereIn('status', ['unpaid', 'overdue'])->whereDate('due_date', '<=', today()->subDays(3))->exists()) {
                $op->update(['status' => 'done']);

                return null;
            }
            if (Operation::where('service_id', $service->id)->where('id', '!=', $op->id)->whereIn('status', ['processing', 'reconciling'])->exists()) {
                throw new RuntimeException('Outra operação em execução.');
            }
            $op->update(['status' => 'processing', 'execution_token' => $this->token()]);

            return $op->fresh(['service.connector']);
        });
        if (! $op) {
            return;
        }
        try {
            $service = $op->service;
            $connector = $service->connector;
            if (! $connector || ! $connector->active) {
                throw new RuntimeException('Integração ausente ou desativada.');
            }
            if (! str_starts_with($connector->endpoint, 'https://')) {
                throw new RuntimeException('A integração exige HTTPS.');
            }
            if ($op->action !== 'create' && ! $service->remote_id) {
                throw new RuntimeException('ID remoto ausente.');
            }
            $remote = match ($connector->driver) {
                'json' => app(JsonDriver::class)->run($op),
                'cpanel' => app(CpanelDriver::class)->run($op),
                'aapanel' => app(AaPanelDriver::class)->run($op),
                'pterodactyl' => app(PterodactylDriver::class)->run($op),
                default => throw new ProtocolError('Driver de provisionamento desconhecido.'),
            };
            DB::transaction(function () use ($op, $remote) {
                $current = Operation::lockForUpdate()->findOrFail($op->id);
                if ($current->status !== 'processing' || $current->execution_token !== $this->token()) {
                    return;
                }
                $service = Service::lockForUpdate()->findOrFail($op->service_id);
                $status = ['create' => 'active', 'suspend' => 'suspended', 'unsuspend' => 'active', 'terminate' => 'cancelled'][$op->action];
                $service->update(['status' => $status, 'remote_id' => $remote ?: null] + ($op->action === 'terminate' ? ['provisioning_secret' => null] : []));
                $op->update(['status' => 'done', 'error' => null]);
                Audit::record('service.'.$op->action, 'service:'.$service->id, ['operation' => $op->id]);
                // A payment may arrive while the remote suspend request is in flight.
                if ($op->action === 'suspend' && str_starts_with($op->reference, 'overdue:') && ! $service->invoices()->whereIn('status', ['unpaid', 'overdue'])->whereDate('due_date', '<=', today()->subDays(3))->exists()) {
                    app(Provisioning::class)->enqueue($service, 'unsuspend', 'reconcile-paid:'.$op->id);
                }
            });
        } catch (Throwable $e) {
            Operation::whereKey($op->id)->where('status', 'processing')->where('execution_token', $this->token())->update(['status' => 'review', 'error' => $e instanceof ProtocolError ? $e->getMessage() : 'Falha de comunicação ou processamento. Concilie no provedor antes de repetir.']);
            Audit::record('operation.review', 'operation:'.$op->id);
        }
    }

    public function failed(?Throwable $e): void
    {
        Operation::where('id', $this->operationId)->where('status', 'processing')->where('execution_token', $this->token())->update(['status' => 'review', 'error' => 'Worker interrompido. Concilie o resultado remoto antes de repetir.']);
    }
}
