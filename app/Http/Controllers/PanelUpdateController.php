<?php

namespace App\Http\Controllers;

use App\Models\AdminPreference;
use App\Models\PanelUpdate;
use App\Services\AdminConfirmation;
use App\Services\Audit;
use App\Services\PanelUpdateSource;
use App\Services\PanelWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PanelUpdateController extends Controller
{
    private function root(Request $r): void
    {
        abort_unless($r->user()?->is_admin, 403);
    }

    public function index(Request $r)
    {
        $this->root($r);

        return view('admin.configuration.updates', ['updates' => PanelUpdate::latest()->paginate(15), 'preference' => AdminPreference::find(1)]);
    }

    public function check(Request $r, PanelUpdateSource $source, PanelWorkspace $workspace)
    {
        $this->root($r);
        try {
            $current = $workspace->head();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['update' => 'O processo web não conseguiu ler o checkout Git. Confira acesso do usuário PHP-FPM ao Git, ao HEAD e às referências, e se proc_open está disponível. Nenhum arquivo foi alterado.']);
        }
        try {
            $target = $source->latest();
        } catch (\Throwable $error) {
            $reason = $error->getMessage() === 'A main ainda não tem CI aprovado.' ? 'A versão publicada ainda não possui CI aprovado. Aguarde a conclusão dos testes no GitHub.' : 'Não foi possível validar a versão no GitHub. Confira DNS, TLS, saída HTTPS e limite de consultas à API pública.';
            throw ValidationException::withMessages(['update' => $reason.' Nenhum arquivo foi alterado.']);
        }
        $u = PanelUpdate::create(['user_id' => $r->user()->id, 'source_sha' => $current, 'target_sha' => $target, 'status' => $current === $target ? 'current' : 'checked', 'phase' => 'checked']);
        Audit::record('panel.update_checked', 'panel_update:'.$u->id, ['target' => $target], $r->user()->id);

        return redirect()->route('admin.settings.updates')->with('status', $current === $target ? 'O código já está no commit consultado.' : 'Atualização encontrada. Confira a comparação e os requisitos antes de aprovar.');
    }

    public function approve(Request $r, PanelUpdate $update, AdminConfirmation $confirmation)
    {
        $this->root($r);
        $r->validate(['ack' => 'accepted']);
        $confirmation->verify($r);
        abort_unless(config('panel_updates.enabled'), 409, 'Atualizador desabilitado no servidor.');
        DB::transaction(function () use ($r, $update) {
            AdminPreference::ensure();
            $p = AdminPreference::whereKey(1)->lockForUpdate()->firstOrFail();
            abort_unless($p->updater_heartbeat_at?->gt(now()->subMinute()), 409, 'Worker de atualização não está pronto.');
            abort_if(PanelUpdate::whereIn('status', ['pending', 'running'])->exists(), 409, 'Já existe atualização em andamento.');
            $u = PanelUpdate::whereKey($update->id)->lockForUpdate()->firstOrFail();
            abort_unless($u->status === 'checked' && $u->created_at->gt(now()->subMinutes(15)), 409, 'Consulta expirada ou já utilizada. Consulte novamente.');
            $u->update(['user_id' => $r->user()->id, 'status' => 'pending', 'approved_at' => now(), 'phase' => 'queued']);
            Audit::record('panel.update_approved', 'panel_update:'.$u->id, ['target' => $u->target_sha], $r->user()->id);
        }, 5);

        return back()->with('status', 'Atualização autorizada. O worker fará as verificações e o backup antes de aplicar.');
    }

    public function cancel(Request $r, PanelUpdate $update)
    {
        $this->root($r);
        DB::transaction(function () use ($r, $update) {
            $u = PanelUpdate::whereKey($update->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($u->status, ['pending', 'checked'], true), 409);
            $u->update(['status' => 'cancelled', 'finished_at' => now(), 'phase' => 'cancelled']);
            Audit::record('panel.update_cancelled', 'panel_update:'.$u->id, [], $r->user()->id);
        }, 5);

        return back()->with('status', 'Solicitação cancelada; código e banco não foram atualizados.');
    }

    public function state(Request $r, PanelUpdate $update)
    {
        $this->root($r);

        return response()->json(['status' => $update->status, 'phase' => $update->phase, 'events' => $update->events ?? []])->header('Cache-Control', 'no-store, private');
    }
}
