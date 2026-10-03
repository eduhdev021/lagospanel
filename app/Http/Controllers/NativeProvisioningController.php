<?php

namespace App\Http\Controllers;

use App\Jobs\RunOperation;
use App\Models\Connector;
use App\Models\Operation;
use App\Models\Service;
use App\Models\User;
use App\Provisioning\AaPanelDriver;
use App\Provisioning\CpanelDriver;
use App\Provisioning\ProtocolError;
use App\Provisioning\PterodactylDriver;
use App\Services\Audit;
use App\Services\Provisioning;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class NativeProvisioningController extends Controller
{
    public function connector(Request $r, Connector $connector)
    {
        $v = $r->validate(['token' => 'nullable|string|min:16|max:2000', 'active' => 'sometimes|boolean']);
        if (isset($v['token']) && preg_match('/[\r\n]/', $v['token'])) {
            throw ValidationException::withMessages(['token' => 'Token inválido.']);
        }
        if (in_array($connector->driver, ['cpanel', 'aapanel', 'pterodactyl'], true) && $r->boolean('active')) {
            $r->validate(['ack_native' => 'accepted']);
        }
        $connector->fill(['active' => $r->boolean('active')]);
        if (! empty($v['token'])) {
            $connector->token = $v['token'];
        }$connector->save();
        Audit::record('connector.updated', 'connector:'.$connector->id, ['active' => $connector->active, 'rotated' => ! empty($v['token'])], $r->user()->id);

        return back()->with('status', 'Integração atualizada. Destino e identidade permanecem imutáveis.');
    }

    public function credentials(Request $r, Service $service)
    {
        abort_unless($service->user_id === $r->user()->id, 404);
        $v = $r->validate(['password' => 'required|string|max:256', 'code' => 'nullable|string|max:30']);
        $access = DB::transaction(function () use ($r, $service, $v) {
            $u = User::lockForUpdate()->findOrFail($r->user()->id);
            if (! Hash::check($v['password'], $u->password)) {
                throw ValidationException::withMessages(['password' => 'Senha atual inválida.']);
            }
            if ($u->totp_secret) {
                $step = Totp::step($u->totp_secret, $v['code'] ?? '', $u->totp_last_step);
                if ($step === null) {
                    throw ValidationException::withMessages(['code' => 'Informe um código novo do autenticador.']);
                }$u->forceFill(['totp_last_step' => $step])->save();
            }
            $s = Service::lockForUpdate()->findOrFail($service->id);
            $p = $s->provisioning;
            abort_unless($s->user_id === $u->id && $s->status === 'active' && ($p['driver'] ?? '') === 'cpanel' && $s->remote_id === $s->native_username && $s->provisioning_secret, 404);
            Audit::record('service.credentials_viewed', 'service:'.$s->id, [], $u->id);

            return ['username' => $p['username'], 'domain' => $p['domain'], 'url' => $p['client_url'], 'password' => $s->provisioning_secret];
        });

        // Render directly; never copy the initial password into flash/session, mail or API.
        return response()->view('client.native-access', ['access' => $access])->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function inspect(Request $r, Operation $operation)
    {
        abort_unless($r->user()->hasPermission('services.manage'), 403);
        $v = $r->validate(['decision' => 'required|in:inspect,confirm,retry', 'note' => 'required|string|min:10|max:500', 'ack' => 'required|accepted']);
        $claim = (string) Str::uuid();
        DB::transaction(function () use ($operation, $claim) {
            $op = Operation::lockForUpdate()->findOrFail($operation->id);
            abort_unless(in_array($op->service->connector?->driver, ['cpanel', 'aapanel', 'pterodactyl'], true), 422);
            abort_unless($op->status === 'review', 409, 'Apenas operações em revisão podem ser conciliadas.');
            $op->update(['status' => 'reconciling', 'execution_token' => $claim]);
        });
        try {
            $operation->refresh()->load('service.connector');
            $driver = app(match ($operation->service->connector->driver) {
                'pterodactyl' => PterodactylDriver::class,'aapanel' => AaPanelDriver::class,default => CpanelDriver::class
            });
            $observation = $driver->observe($operation->service);
            DB::transaction(function () use ($operation, $observation, $v, $r, $claim) {
                $op = Operation::lockForUpdate()->findOrFail($operation->id);
                $s = Service::lockForUpdate()->findOrFail($op->service_id);
                abort_unless($op->status === 'reconciling' && $op->execution_token === $claim, 409);
                $op->fill(['inspection' => $observation, 'review_note' => $v['note']]);
                $target = ['create' => 'active', 'suspend' => 'suspended', 'unsuspend' => 'active', 'terminate' => 'absent'][$op->action] ?? null;
                if ($v['decision'] === 'confirm') {
                    if ($observation['status'] !== $target || ($op->action === 'create' && ! $op->sent_at)) {
                        throw new ProtocolError('Não é possível confirmar este resultado: estado divergente ou criação nunca enviada.');
                    }
                    if (in_array($op->action, ['create', 'unsuspend'], true) && ! $s->invoices()->where('status', 'paid')->exists()) {
                        throw new ProtocolError('Confirmação de ativação exige pagamento.');
                    }
                    $s->update(['status' => $target === 'absent' ? 'cancelled' : $target, 'remote_id' => $observation['remote_id'] ?? ($s->provisioning['driver'] === 'cpanel' ? $s->native_username : $s->remote_id)] + ($op->action === 'terminate' ? ['provisioning_secret' => null] : []));
                    $op->fill(['status' => 'done', 'error' => null])->save();
                    if ($op->action === 'suspend' && str_starts_with($op->reference, 'overdue:') && ! $s->invoices()->whereIn('status', ['unpaid', 'overdue'])->whereDate('due_date', '<=', today()->subDays(3))->exists()) {
                        app(Provisioning::class)->enqueue($s, 'unsuspend', 'reconcile-paid:'.$op->id);
                    }
                } elseif ($v['decision'] === 'retry') {
                    if (($op->action === 'create' && $observation['status'] !== 'absent') || $observation['status'] === $target || ($op->action !== 'create' && $observation['status'] === 'absent')) {
                        throw new ProtocolError('Reenvio incompatível com a observação atual.');
                    }
                    $op->fill(['status' => 'pending', 'error' => null])->save();
                    RunOperation::dispatch($op->id)->onConnection('database');
                } else {
                    $op->fill(['status' => 'review'])->save();
                }
                Audit::record('operation.reconciled', 'operation:'.$op->id, ['decision' => $v['decision'], 'observed' => $observation['status'], 'note' => $v['note']], $r->user()->id);
            }, 5);
        } catch (Throwable $e) {
            Operation::whereKey($operation->id)->where('status', 'reconciling')->where('execution_token', $claim)->update(['status' => 'review', 'error' => $e instanceof ProtocolError ? $e->getMessage() : 'Consulta/conciliação não concluída. Nenhuma repetição automática foi autorizada.']);

            return back()->withErrors(['operation' => 'Conciliação não concluída. Consulte os detalhes da operação.']);
        }

        return back()->with('status', 'Conciliação registrada. Reenvio, quando autorizado, será processado pela fila.');
    }

    public function interrupted(Request $r, Operation $operation)
    {
        abort_unless($r->user()->hasPermission('services.manage'), 403);
        $r->validate(['note' => 'required|string|min:10|max:500', 'ack' => 'required|accepted']);
        DB::transaction(function () use ($operation, $r) {
            $op = Operation::lockForUpdate()->findOrFail($operation->id);
            abort_unless(in_array($op->service->connector?->driver, ['cpanel', 'aapanel', 'pterodactyl'], true) && in_array($op->status, ['processing', 'reconciling'], true) && $op->updated_at->lt(now()->subMinutes(5)), 409);
            $op->update(['status' => 'review', 'execution_token' => null, 'error' => 'Worker/consulta interrompido. Consulte o provedor e verifique que não há execução remota em andamento.', 'review_note' => $r->input('note')]);
            Audit::record('operation.interrupted', 'operation:'.$op->id, ['note' => $r->input('note')], $r->user()->id);
        });

        return back()->with('status', 'Operação movida para revisão; nenhuma chamada remota foi executada.');
    }
}
