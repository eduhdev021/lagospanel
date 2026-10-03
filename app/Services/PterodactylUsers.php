<?php

namespace App\Services;

use App\Models\Connector;
use App\Models\PterodactylAccount;
use App\Models\PterodactylAccountRequest;
use App\Models\User;
use App\Provisioning\AaPanelConfig;
use App\Provisioning\ProtocolError;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class PterodactylUsers
{
    public function create(Connector $connector, User $user, array $names, int $actor): PterodactylAccountRequest
    {
        $request = DB::transaction(function () use ($connector, $user, $names, $actor) {
            $c = Connector::whereKey($connector->id)->lockForUpdate()->firstOrFail();
            $u = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->enabled($c);
            abort_unless($u->hasVerifiedEmail(), 422, 'Confirme o e-mail do cliente antes de criar sua conta remota.');
            $existing = PterodactylAccountRequest::where('connector_id', $c->id)->where('user_id', $u->id)->first();
            if ($existing) {
                abort_unless($existing->email === strtolower($u->email) && $existing->endpoint === $c->endpoint && $existing->first_name === $names['first_name'] && $existing->last_name === $names['last_name'], 409, 'Solicitação existente possui dados diferentes. Confira o resultado, sem criar outra conta.');

                return $existing;
            }
            abort_if(PterodactylAccount::where('connector_id', $c->id)->where('user_id', $u->id)->exists(), 409, 'Cliente já vinculado.');
            $r = PterodactylAccountRequest::create(['connector_id' => $c->id, 'user_id' => $u->id, 'endpoint' => AaPanelConfig::origin($c->endpoint), 'email' => strtolower($u->email), 'first_name' => $names['first_name'], 'last_name' => $names['last_name'], 'username' => 'lg'.substr(str_replace('-', '', (string) Str::uuid()), 0, 28), 'external_id' => 'lagos-user-'.Str::uuid()]);
            Audit::record('pterodactyl.user_requested', 'pterodactyl_request:'.$r->id, [], $actor);

            return $r;
        }, 5);

        return $this->execute($request, true, $actor);
    }

    public function inspect(PterodactylAccountRequest $request, int $actor): PterodactylAccountRequest
    {
        return $this->execute($request, false, $actor);
    }

    private function enabled(Connector $c): void
    {
        if (! config('lagos.native_provisioning') || ! $c->active || $c->driver !== 'pterodactyl' || ! $c->token) {
            throw new ProtocolError('Chamadas Pterodactyl desativadas ou sem chave.');
        }
        AaPanelConfig::origin($c->endpoint);
    }

    private function connector(PterodactylAccountRequest $r): Connector
    {
        $c = Connector::findOrFail($r->connector_id);
        $this->enabled($c);
        $u = User::findOrFail($r->user_id);
        if ($c->endpoint !== $r->endpoint || ! $u->hasVerifiedEmail() || strtolower($u->email) !== $r->email) {
            throw new ProtocolError('Destino ou identidade local mudou. Revisão manual necessária.');
        }

        return $c;
    }

    private function http(PterodactylAccountRequest $r, string $method, string $path, ?array $payload = null): ?array
    {
        $c = $this->connector($r);
        if (! PterodactylAccountRequest::whereKey($r->id)->where('status', 'processing')->where('execution_token', $r->execution_token)->exists()) {
            throw new ProtocolError('Execução substituída.');
        }
        $response = Http::withToken($c->token)->withHeaders(['Accept' => 'Application/vnd.pterodactyl.v1+json'])->asJson()->withoutRedirecting()->connectTimeout(3)->timeout(8)->withOptions(['verify' => true,
            'on_headers' => function ($response) {
                if ((int) $response->getHeaderLine('Content-Length') > 1048576) {
                    throw new ProtocolError('Resposta excessiva.');
                }
            },
            'progress' => function ($total, $received) {
                if ($received > 1048576) {
                    throw new ProtocolError('Resposta excessiva.');
                }
            },
        ])->send($method, $r->endpoint.'/api/application'.$path, $payload === null ? [] : ['json' => $payload]);
        $body = $response->json();
        if (strlen($response->body()) > 1048576) {
            throw new ProtocolError('Resposta excessiva.');
        }
        if ($method === 'GET' && $response->status() === 404 && ($body['errors'][0]['code'] ?? '') === 'NotFoundHttpException') {
            return null;
        }
        if ($response->status() !== ($method === 'POST' ? 201 : 200) || ! is_array($body) || isset($body['errors'])) {
            throw new ProtocolError('Resposta não confirmada.');
        }

        return $body;
    }

    private function identity(?array $body, PterodactylAccountRequest $r): int
    {
        $a = $body['attributes'] ?? [];
        if (($body['object'] ?? '') !== 'user' || ! is_int($a['id'] ?? null) || $a['id'] < 1 || $a['id'] > 2147483647 || ($a['external_id'] ?? '') !== $r->external_id || ($a['username'] ?? '') !== $r->username || strtolower($a['email'] ?? '') !== $r->email || ($a['root_admin'] ?? null) !== false || ($r->remote_user_id !== null && $r->remote_user_id !== $a['id'])) {
            throw new ProtocolError('Identidade remota divergente.');
        }

        return $a['id'];
    }

    private function execute(PterodactylAccountRequest $request, bool $allowCreate, int $actor): PterodactylAccountRequest
    {
        $r = DB::transaction(function () use ($request) {
            // Same lock order as manual binding; network I/O is outside transactions.
            Connector::whereKey($request->connector_id)->lockForUpdate()->firstOrFail();
            $r = PterodactylAccountRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->connector($r);
            if ($r->status === 'done') {
                return $r;
            }
            abort_if($r->status === 'processing' && $r->started_at?->gt(now()->subMinutes(2)), 409, 'Criação/conferência em andamento. Aguarde antes de conferir novamente.');
            abort_if(PterodactylAccount::where('connector_id', $r->connector_id)->where('user_id', $r->user_id)->exists(), 409, 'Cliente já vinculado; revise a solicitação remota separadamente.');
            $r->update(['status' => 'processing', 'execution_token' => (string) Str::uuid(), 'started_at' => now()]);

            return $r;
        }, 5);
        if ($r->status === 'done') {
            return $r;
        }
        try {
            $path = '/users/external/'.rawurlencode($r->external_id);
            $body = $this->http($r, 'GET', $path);
            if (! $r->sent_at) {
                // Unknown preexisting external IDs are never adopted, even if other fields match.
                if ($body !== null || ! $allowCreate) {
                    throw new ProtocolError('Solicitação ainda não enviada ou identidade remota preexistente.');
                }
                $this->connector($r);
                $changed = PterodactylAccountRequest::whereKey($r->id)->where('status', 'processing')->where('execution_token', $r->execution_token)->whereNull('sent_at')->update(['sent_at' => now()]);
                if ($changed !== 1) {
                    throw new ProtocolError('Execução substituída.');
                }
                $r->sent_at = now();
                $created = $this->http($r, 'POST', '/users', ['external_id' => $r->external_id, 'email' => $r->email, 'username' => $r->username, 'first_name' => $r->first_name, 'last_name' => $r->last_name, 'root_admin' => false, 'language' => 'en']);
                $id = $this->identity($created, $r);
                if (PterodactylAccountRequest::whereKey($r->id)->where('status', 'processing')->where('execution_token', $r->execution_token)->update(['remote_user_id' => $id]) !== 1) {
                    throw new ProtocolError('Execução substituída.');
                }
                $r->remote_user_id = $id;
                $body = $this->http($r, 'GET', $path);
            }
            // A sent request is read-only forever, including an absent/ambiguous result.
            $id = $this->identity($body, $r);
            $r->remote_user_id = $id;
            $this->identity($this->http($r, 'GET', '/users/'.$id), $r);
            DB::transaction(function () use ($r, $id, $actor) {
                Connector::whereKey($r->connector_id)->lockForUpdate()->firstOrFail();
                $current = PterodactylAccountRequest::whereKey($r->id)->lockForUpdate()->firstOrFail();
                User::whereKey($current->user_id)->lockForUpdate()->firstOrFail();
                $this->connector($current);
                if ($current->status !== 'processing' || $current->execution_token !== $r->execution_token) {
                    throw new ProtocolError('Execução substituída.');
                }
                $mapped = PterodactylAccount::where('connector_id', $r->connector_id)->where(fn ($q) => $q->where('user_id', $r->user_id)->orWhere('remote_user_id', $id))->first();
                if ($mapped && ((int) $mapped->user_id !== (int) $r->user_id || $mapped->remote_user_id !== $id)) {
                    throw new ProtocolError('Vínculo já ocupado.');
                }
                if (! $mapped) {
                    PterodactylAccount::create(['connector_id' => $r->connector_id, 'user_id' => $r->user_id, 'remote_user_id' => $id]);
                }
                $current->update(['status' => 'done', 'remote_user_id' => $id]);
                Audit::record('pterodactyl.user_linked_after_readback', 'pterodactyl_request:'.$r->id, ['remote_user_id' => $id], $actor);
            }, 5);
        } catch (\Throwable) {
            // Never expose or log raw provider errors, credentials or customer payloads.
            $changed = PterodactylAccountRequest::whereKey($r->id)->where('status', 'processing')->where('execution_token', $r->execution_token)->update(['status' => 'review']);
            if ($changed) {
                Audit::record('pterodactyl.user_requires_review', 'pterodactyl_request:'.$r->id, [], $actor);
            }
        }

        return $r->fresh();
    }
}
