<?php

namespace App\Http\Controllers;

use App\Models\Connector;
use App\Models\PterodactylAccount;
use App\Models\PterodactylAccountRequest;
use App\Models\User;
use App\Provisioning\ProtocolError;
use App\Services\Audit;
use App\Services\PterodactylUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PterodactylAccountController extends Controller
{
    public function index(Request $r, Connector $connector)
    {
        abort_unless($connector->driver === 'pterodactyl' && $r->user()->hasPermission('customers.view'), 403);

        return view('admin.pterodactyl-accounts', ['connector' => $connector, 'requests' => PterodactylAccountRequest::with('user')->where('connector_id', $connector->id)->latest()->paginate(15, ['*'], 'requests_page'), 'accounts' => PterodactylAccount::with('user')->where('connector_id', $connector->id)->latest()->paginate(25)]);
    }

    public function create(Request $r, Connector $connector)
    {
        abort_unless($connector->driver === 'pterodactyl' && $r->user()->hasPermission('customers.view'), 403);
        $v = $r->validate(['user_id' => 'required|integer|exists:users,id', 'remote_user_id' => 'required|integer|min:1|max:2147483647', 'ack' => 'accepted']);
        DB::transaction(function () use ($connector, $v, $r) {
            Connector::whereKey($connector->id)->lockForUpdate()->firstOrFail();
            abort_if(PterodactylAccountRequest::where('connector_id', $connector->id)->where('user_id', $v['user_id'])->where('status', 'processing')->exists(), 409, 'Existe criação/conferência remota em andamento. Confira o resultado antes de vincular manualmente.');
            abort_if(PterodactylAccount::where('connector_id', $connector->id)->where(fn ($q) => $q->where('user_id', $v['user_id'])->orWhere('remote_user_id', $v['remote_user_id']))->exists(), 409, 'Conta ou cliente já vinculado nesta integração.');
            $a = PterodactylAccount::create(['connector_id' => $connector->id, 'user_id' => $v['user_id'], 'remote_user_id' => $v['remote_user_id']]);
            Audit::record('pterodactyl.account_linked', 'pterodactyl_account:'.$a->id, [], $r->user()->id);
        }, 5);

        return back()->with('status', 'Vínculo registrado. Titular, e-mail e ausência de privilégio administrador serão conferidos na API antes de provisionar.');
    }

    public function provision(Request $r, Connector $connector, PterodactylUsers $service)
    {
        abort_unless($connector->driver === 'pterodactyl' && $r->user()->hasPermission('customers.view'), 403);
        $v = $r->validate(['user_id' => 'required|integer|exists:users,id', 'first_name' => 'required|string|max:64|not_regex:/[\x00-\x1f\x7f]/', 'last_name' => 'required|string|max:64|not_regex:/[\x00-\x1f\x7f]/', 'ack' => 'accepted']);
        try {
            $result = $service->create($connector, User::findOrFail($v['user_id']), ['first_name' => $v['first_name'], 'last_name' => $v['last_name']], $r->user()->id);
        } catch (ProtocolError) {
            return back()->withErrors(['pterodactyl' => 'Chamadas desativadas, identidade alterada ou configuração inválida. Confira a integração.']);
        }

        return $this->result($result);
    }

    public function inspect(Request $r, Connector $connector, PterodactylAccountRequest $accountRequest, PterodactylUsers $service)
    {
        abort_unless($connector->driver === 'pterodactyl' && $r->user()->hasPermission('customers.view'), 403);
        abort_unless((int) $accountRequest->connector_id === (int) $connector->id, 404);
        try {
            $result = $service->inspect($accountRequest, $r->user()->id);
        } catch (ProtocolError) {
            return back()->withErrors(['pterodactyl' => 'Chamadas desativadas ou identidade alterada. Confira a integração antes de consultar.']);
        }

        return $this->result($result);
    }

    private function result(PterodactylAccountRequest $result)
    {
        if ($result->status === 'done') {
            return back()->with('status', 'Conta remota confirmada e vinculada. O acesso e a definição de senha são feitos no Pterodactyl; confira o SMTP daquele painel.');
        }

        return back()->withErrors(['pterodactyl' => 'Resultado pendente de revisão. Após uma tentativa enviada, o painel somente consulta e nunca repete a criação. Confira também o Pterodactyl; uma falha de e-mail pode ocorrer depois de criar a conta.']);
    }
}
