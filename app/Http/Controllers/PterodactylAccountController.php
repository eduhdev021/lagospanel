<?php

namespace App\Http\Controllers;

use App\Models\Connector;
use App\Models\PterodactylAccount;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PterodactylAccountController extends Controller
{
    public function index(Request $r, Connector $connector)
    {
        abort_unless($connector->driver === 'pterodactyl' && $r->user()->hasPermission('customers.view'), 403);

        return view('admin.pterodactyl-accounts', ['connector' => $connector, 'accounts' => PterodactylAccount::with('user')->where('connector_id', $connector->id)->latest()->paginate(25)]);
    }

    public function create(Request $r, Connector $connector)
    {
        abort_unless($connector->driver === 'pterodactyl' && $r->user()->hasPermission('customers.view'), 403);
        $v = $r->validate(['user_id' => 'required|integer|exists:users,id', 'remote_user_id' => 'required|integer|min:1|max:2147483647', 'ack' => 'accepted']);
        DB::transaction(function () use ($connector, $v, $r) {
            Connector::whereKey($connector->id)->lockForUpdate()->firstOrFail();
            abort_if(PterodactylAccount::where('connector_id', $connector->id)->where(fn ($q) => $q->where('user_id', $v['user_id'])->orWhere('remote_user_id', $v['remote_user_id']))->exists(), 409, 'Conta ou cliente já vinculado nesta integração.');
            $a = PterodactylAccount::create(['connector_id' => $connector->id, 'user_id' => $v['user_id'], 'remote_user_id' => $v['remote_user_id']]);
            Audit::record('pterodactyl.account_linked', 'pterodactyl_account:'.$a->id, [], $r->user()->id);
        }, 5);

        return back()->with('status','Vínculo registrado. Titular, e-mail e ausência de privilégio administrador serão conferidos na API antes de provisionar.');
    }
}
