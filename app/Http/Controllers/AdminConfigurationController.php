<?php

namespace App\Http\Controllers;

use App\Models\AdminPreference;
use App\Services\AdminConfirmation;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminConfigurationController extends Controller
{
    public function index()
    {
        return view('admin.configuration.index');
    }

    public function security(Request $r)
    {
        abort_unless($r->user()->is_admin, 403);

        return view('admin.configuration.security', ['preference' => AdminPreference::find(1)]);
    }

    public function saveSecurity(Request $r, AdminConfirmation $confirmation)
    {
        $v = $r->validate(['version' => 'required|integer|min:0', 'require_two_factor' => 'required|boolean', 'ack' => 'accepted']);
        $confirmation->verify($r);
        if ($r->boolean('require_two_factor') && ! $r->user()->fresh()->totp_secret) {
            throw ValidationException::withMessages(['require_two_factor' => 'Ative o 2FA da sua própria conta antes de torná-lo obrigatório para a equipe.']);
        }
        DB::transaction(function () use ($r, $v) {
            AdminPreference::ensure();
            $p = AdminPreference::whereKey(1)->lockForUpdate()->firstOrFail();
            abort_unless($p->version === (int) $v['version'], 409, 'Outra configuração foi salva. Recarregue esta seção.');
            $p->update(['require_two_factor' => $r->boolean('require_two_factor'), 'version' => $p->version + 1]);
            Audit::record('admin.security_updated', 'admin_preferences:1', ['require_two_factor' => $p->require_two_factor], $r->user()->id);
        }, 5);

        return back()->with('status', 'Política de acesso salva. Senha e permissões continuam obrigatórias.');
    }

    public function environment()
    {
        return view('admin.configuration.environment');
    }
}
