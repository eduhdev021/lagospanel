<?php

namespace App\Services;

use App\Models\Connector;
use App\Models\PleskCustomerRequest;
use App\Models\User;
use App\Provisioning\PleskXml;
use App\Provisioning\ProtocolError;
use DOMElement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PleskCustomers
{
    private function enabled(PleskCustomerRequest $r): Connector
    {
        $c = Connector::findOrFail($r->connector_id);
        $u = User::findOrFail($r->user_id);
        if (! config('lagos.native_provisioning') || ! $c->active || $c->driver !== 'plesk' || ! $c->token || $c->endpoint !== $r->endpoint || ! $u->hasVerifiedEmail() || strtolower($u->email) !== $r->email) {
            throw new ProtocolError('Identidade ou integração Plesk alterada.');
        }

        return $c;
    }

    private function query(PleskCustomerRequest $r, string $kind, string $value): ?DOMElement
    {
        $c = $this->enabled($r);
        $body = '<filter><'.$kind.'>'.PleskXml::esc($value).'</'.$kind.'></filter><dataset><gen_info/></dataset>';
        $node = app(PleskXml::class)->request($c, $r->endpoint, 'customer', 'get', $body);
        if (PleskXml::value($node, 'status') === 'error' && PleskXml::value($node, 'errcode') === '1013') {
            return null;
        }

        return $node;
    }

    private function identity(?DOMElement $node, PleskCustomerRequest $r): int
    {
        if (! $node || PleskXml::value($node, 'status') !== 'ok') {
            throw new ProtocolError('Cliente Plesk não confirmado.');
        }
        $id = PleskXml::id(PleskXml::value($node, 'id'));
        foreach (['login' => 'login', 'external-id' => 'external_id', 'email' => 'email'] as $key => $field) {
            $value = PleskXml::value($node, 'data/gen_info/'.$key);
            if ($key === 'email') {
                $value = strtolower($value);
            }if ($value !== $r->$field) {
                throw new ProtocolError('Cliente Plesk divergente.');
            }
        }
        if (PleskXml::value($node, 'data/gen_info/status') !== '0' || ($r->remote_id !== null && $r->remote_id !== $id)) {
            throw new ProtocolError('ID ou estado de cliente Plesk divergente.');
        }

        return $id;
    }

    public function verify(PleskCustomerRequest $r): int
    {
        if ($r->status !== 'done' || ! $r->sent_at || ! $r->remote_id) {
            throw new ProtocolError('Cliente não confirmado.');
        }

        return $this->identity($this->query($r, 'id', (string) $r->remote_id), $r);
    }

    public function prepare(Connector $connector, User $user): PleskCustomerRequest
    {
        $r = DB::transaction(function () use ($connector, $user) {
            $c = Connector::whereKey($connector->id)->lockForUpdate()->firstOrFail();
            $u = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $r = PleskCustomerRequest::where('connector_id', $c->id)->where('user_id', $u->id)->first();
            if (! $r) {
                $r = PleskCustomerRequest::create(['connector_id' => $c->id, 'user_id' => $u->id, 'endpoint' => $c->endpoint, 'email' => strtolower($u->email), 'name' => mb_substr(trim($u->name) ?: 'Cliente', 0, 60), 'login' => 'lg'.str_replace('-', '', (string) Str::uuid()), 'external_id' => (string) Str::uuid(), 'secret' => Str::password(14)]);
            }
            $this->enabled($r);
            if ($r->status === 'done') {
                return $r;
            }
            abort_if($r->status === 'processing' && $r->started_at?->gt(now()->subMinutes(2)), 409);
            $r->update(['status' => 'processing', 'started_at' => now(), 'execution_token' => (string) Str::uuid()]);

            return $r;
        }, 5);
        if ($r->status === 'done') {
            $this->verify($r);

            return $r;
        }
        try {
            $node = $this->query($r, 'login', $r->login);
            if (! $r->sent_at) {
                if ($node !== null) {
                    throw new ProtocolError('Login preexistente não será adotado.');
                }
                $c = $this->enabled($r);
                if (PleskCustomerRequest::whereKey($r->id)->where('status', 'processing')->where('execution_token', $r->execution_token)->whereNull('sent_at')->update(['sent_at' => now()]) !== 1) {
                    throw new ProtocolError('Execução substituída.');
                }
                $r->sent_at = now();
                $body = '<gen_info><pname>'.PleskXml::esc($r->name).'</pname><login>'.PleskXml::esc($r->login).'</login><passwd>'.PleskXml::esc($r->secret).'</passwd><status>0</status><email>'.PleskXml::esc($r->email).'</email><external-id>'.PleskXml::esc($r->external_id).'</external-id></gen_info>';
                $created = app(PleskXml::class)->request($c, $r->endpoint, 'customer', 'add', $body);
                if (PleskXml::value($created, 'status') !== 'ok') {
                    throw new ProtocolError('Criação não confirmada.');
                }
                $id = PleskXml::id(PleskXml::value($created, 'id'));
                if (PleskCustomerRequest::whereKey($r->id)->where('status', 'processing')->where('execution_token', $r->execution_token)->update(['remote_id' => $id]) !== 1) {
                    throw new ProtocolError('Execução substituída.');
                }
                $r->remote_id = $id;
                $node = $this->query($r, 'login', $r->login);
            }
            $id = $this->identity($node, $r);
            $r->remote_id = $id;
            $this->identity($this->query($r, 'id', (string) $id), $r);
            DB::transaction(function () use ($r, $id) {
                Connector::whereKey($r->connector_id)->lockForUpdate()->firstOrFail();
                $current = PleskCustomerRequest::whereKey($r->id)->lockForUpdate()->firstOrFail();
                User::whereKey($r->user_id)->lockForUpdate()->firstOrFail();
                $this->enabled($current);
                if ($current->status !== 'processing' || $current->execution_token !== $r->execution_token) {
                    throw new ProtocolError('Execução substituída.');
                }
                $current->update(['status' => 'done', 'remote_id' => $id, 'secret' => null]);
                Audit::record('plesk.customer_prepared', 'plesk_customer:'.$r->id, ['remote_id' => $id], $r->user_id);
            }, 5);
        } catch (\Throwable) {
            PleskCustomerRequest::whereKey($r->id)->where('status', 'processing')->where('execution_token', $r->execution_token)->update(['status' => 'review']);
        }

        return $r->fresh();
    }
}
