<?php

namespace App\Services;

use App\Jobs\RunOperation;
use App\Models\Operation;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class Provisioning
{
    public function enqueue(Service $service, string $action, string $reference): Operation
    {
        return DB::transaction(function () use ($service, $action, $reference) {
            $service = Service::lockForUpdate()->findOrFail($service->id);
            if ($old = Operation::where('reference', $reference)->first()) {
                if ($old->service_id !== $service->id || $old->action !== $action) {
                    throw ValidationException::withMessages(['operation' => 'Referência já vinculada a outra operação.']);
                }

                return $old;
            }
            if (! in_array($action, ['create', 'suspend', 'unsuspend', 'terminate'], true)) {
                throw ValidationException::withMessages(['operation' => 'Ação inválida.']);
            }
            if ($open = Operation::where('service_id', $service->id)->whereIn('status', ['pending', 'processing', 'review', 'reconciling'])->first()) {
                if ($open->action !== $action) {
                    throw ValidationException::withMessages(['operation' => 'Há uma operação diferente em andamento ou revisão. Concilie antes de alterar o estado.']);
                }

                return $open;
            }
            $operation = Operation::create(['service_id' => $service->id, 'action' => $action, 'reference' => $reference]);
            RunOperation::dispatch($operation->id)->onConnection('database');

            return $operation;
        }, 5);
    }

    public function manuallySet(Service $service, string $status, int $actor, string $note): void
    {
        DB::transaction(function () use ($service, $status, $actor, $note) {
            $service = Service::lockForUpdate()->findOrFail($service->id);
            if ($service->connector_id) {
                throw ValidationException::withMessages(['service' => 'Use uma operação da integração para este serviço.']);
            }
            if ($status === 'active' && ! $service->invoices()->where('status', 'paid')->exists()) {
                throw ValidationException::withMessages(['service' => 'Confirme o pagamento antes de ativar.']);
            }
            if ($service->status === 'cancelled') {
                throw ValidationException::withMessages(['service' => 'Serviço encerrado.']);
            }
            $service->update(['status' => $status]);
            Audit::record('service.manual', 'service:'.$service->id, ['status' => $status, 'note' => $note], $actor);
        });
    }
}
