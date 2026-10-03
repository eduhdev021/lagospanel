<?php

namespace App\Jobs;

use App\Models\Operation;
use App\Models\Service;
use App\Provisioning\PterodactylDriver;
use App\Services\Audit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PollPterodactyl implements ShouldQueue
{
    use Queueable;

    public $tries = 1;

    public $timeout = 25;

    public string $claim;

    public function __construct(public int $operationId, public string $originalToken, public int $attempt = 1)
    {
        $this->claim = (string) Str::uuid();
    }

    public function handle(): void
    {
        $op = DB::transaction(function () {
            $o = Operation::lockForUpdate()->find($this->operationId);
            if (! $o || $o->status !== 'review' || $o->execution_token !== $this->originalToken || $o->action !== 'create' || ! $o->sent_at || $o->service->connector?->driver !== 'pterodactyl') {
                return null;
            }
            $o->update(['status' => 'reconciling', 'execution_token' => $this->claim]);

            return $o;
        });
        if (! $op) {
            return;
        }
        try {
            $seen = app(PterodactylDriver::class)->observe($op->service);
            DB::transaction(function () use ($seen) {
                $o = Operation::lockForUpdate()->findOrFail($this->operationId);
                if ($o->status !== 'reconciling' || $o->execution_token !== $this->claim) {
                    return;
                }
                $s = Service::lockForUpdate()->findOrFail($o->service_id);
                if ($seen['status'] === 'active' && $s->status !== 'cancelled' && $s->invoices()->where('status', 'paid')->exists()) {
                    $s->update(['status' => 'active', 'remote_id' => $seen['remote_id']]);
                    $o->update(['status' => 'done', 'inspection' => $seen, 'error' => null]);
                    Audit::record('service.create', 'service:'.$s->id, ['operation' => $o->id, 'installation_poll' => true]);
                } else {
                    $o->update(['status' => 'review', 'execution_token' => $this->originalToken, 'inspection' => $seen, 'error' => 'Instalação Pterodactyl não concluída. Estado: '.$seen['status'].'. Nenhuma criação será repetida.']);
                    if ($seen['status'] === 'installing' && $this->attempt < 20) {
                        self::dispatch($o->id, $this->originalToken, $this->attempt + 1)->onConnection('database')->delay(now()->addSeconds(30))->afterCommit();
                    }
                }
            });
        } catch (\Throwable) {
            $this->failed(null);
        }
    }

    public function failed(?\Throwable $e): void
    {
        Operation::whereKey($this->operationId)->where('status', 'reconciling')->where('execution_token', $this->claim)->update(['status' => 'review', 'error' => 'Consulta de instalação interrompida. Concilie no Pterodactyl; não repita a criação.']);
    }
}
