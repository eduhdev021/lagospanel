<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Audit;
use App\Services\OutgoingWebhooks;
use App\Services\WebhookDestination;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OutgoingWebhookController extends Controller
{
    private function confirm(Request $r): void
    {
        $v = $r->validate(['password' => 'required|string|max:256', 'code' => 'nullable|string|max:30']);
        DB::transaction(function () use ($r, $v) {
            $u = User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            if (! Hash::check($v['password'], $u->password)) {
                throw ValidationException::withMessages(['password' => 'Senha atual inválida.']);
            }
            if ($u->totp_secret) {
                $step = Totp::step($u->totp_secret, $v['code'] ?? '', $u->totp_last_step);
                if ($step === null) {
                    throw ValidationException::withMessages(['code' => 'Informe um código novo do autenticador.']);
                }$u->forceFill(['totp_last_step' => $step])->save();
            }
        }, 5);
    }

    public function index()
    {
        return view('admin.webhooks', ['endpoints' => WebhookEndpoint::latest()->paginate(15, ['*'], 'destinations'), 'deliveries' => WebhookDelivery::with('endpoint')->latest()->paginate(25, ['*'], 'deliveries'), 'events' => array_keys(OutgoingWebhooks::EVENTS)]);
    }

    public function show(WebhookDelivery $delivery)
    {
        return view('admin.webhook-delivery', ['delivery' => $delivery->load('endpoint'), 'attempts' => $delivery->history()->latest('id')->paginate(25)]);
    }

    public function create(Request $r)
    {
        $v = $r->validate(['name' => 'required|string|max:100', 'url' => 'required|string|max:1000', 'events' => 'required|array|min:1|max:20', 'events.*' => ['required', 'string', 'distinct', Rule::in(array_keys(OutgoingWebhooks::EVENTS))], 'ack' => 'accepted']);
        WebhookDestination::validate($v['url']);
        $this->confirm($r);
        $secret = bin2hex(random_bytes(32));
        $e = DB::transaction(function () use ($r, $v, $secret) {
            $e = WebhookEndpoint::create(['name' => $v['name'], 'url' => $v['url'], 'events' => $v['events'], 'secret' => $secret, 'active' => true]);
            Audit::record('webhook.created', 'webhook_endpoint:'.$e->id, [], $r->user()->id);

            return $e;
        }, 5);

        return response()->view('admin.webhook-secret', ['endpoint' => $e, 'secret' => $secret])->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function state(Request $r, WebhookEndpoint $endpoint)
    {
        $v = $r->validate(['active' => 'required|boolean']);
        $this->confirm($r);
        DB::transaction(function () use ($r, $endpoint, $v) {
            $e = WebhookEndpoint::whereKey($endpoint->id)->lockForUpdate()->firstOrFail();
            $e->update(['active' => (bool) $v['active']]);
            Audit::record('webhook.state', 'webhook_endpoint:'.$e->id, ['active' => $e->active], $r->user()->id);
        }, 5);

        return back()->with('status', 'Destino atualizado. Entregas já em trânsito não podem ser recolhidas.');
    }

    public function retry(Request $r, WebhookDelivery $delivery)
    {
        $v = $r->validate(['note' => 'required|string|min:10|max:500', 'ack' => 'accepted']);
        $this->confirm($r);
        DB::transaction(function () use ($r, $delivery, $v) {
            $d = WebhookDelivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($d->status, ['failed', 'cancelled'], true) && $d->endpoint->active && $d->attempts < 100, 409);
            $d->update(['status' => 'retry', 'max_attempts' => min(100, $d->attempts + 5), 'retry_base' => $d->attempts, 'next_attempt_at' => now(), 'execution_token' => null]);
            Audit::record('webhook.retry_requested', 'webhook_delivery:'.$d->id, ['note' => $v['note']], $r->user()->id);
        }, 5);

        return back()->with('status','Retentativa autorizada com o mesmo ID de evento. O destinatário deve deduplicar entregas.');
    }
}
