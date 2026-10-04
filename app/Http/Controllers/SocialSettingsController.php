<?php

namespace App\Http\Controllers;

use App\Models\SocialProvider;
use App\Services\AdminConfirmation;
use App\Services\Audit;
use App\Services\SiteConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SocialSettingsController extends Controller
{
    public function index(Request $r)
    {
        abort_unless($r->user()->is_admin, 403);

        return view('admin.configuration.social', ['settings' => SocialProvider::all()->keyBy('provider')]);
    }

    public function save(Request $r, string $provider, AdminConfirmation $confirmation)
    {
        abort_unless($r->user()->is_admin && isset(SocialProvider::LABELS[$provider]), 403);
        $v = $r->validate(['enabled' => 'required|boolean', 'client_id' => 'nullable|string|max:255|regex:/^[A-Za-z0-9._-]+$/', 'client_secret' => 'nullable|string|max:4096', 'clear_secret' => 'sometimes|boolean', 'version' => 'required|integer|min:0']);
        $confirmation->verify($r);
        DB::transaction(function () use ($provider, $v, $r) {
            SocialProvider::insertOrIgnore(['provider' => $provider, 'version' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $s = SocialProvider::whereKey($provider)->lockForUpdate()->firstOrFail();
            abort_unless($s->version === (int) $v['version'], 409, 'Configuração alterada. Recarregue a página.');
            $s->client_id = $v['client_id'] ?? null;
            if ($r->boolean('clear_secret')) {
                $s->client_secret = null;
            } elseif (! empty($v['client_secret'])) {
                $s->client_secret = $v['client_secret'];
            }
            if ($r->boolean('enabled')) {
                SiteConfiguration::origin(config('app.url'));
                if (! $s->client_id || ! $s->client_secret) {
                    throw ValidationException::withMessages(['client_secret' => 'Informe Client ID e Client Secret antes de ativar.']);
                }
            }
            $s->enabled = $r->boolean('enabled');
            $s->version++;
            $s->save();
            Audit::record('social.provider_updated', 'provider:'.$provider, ['enabled' => $s->enabled, 'version' => $s->version], $r->user()->id);
        }, 5);

        return back()->with('status', 'Provedor salvo. Cadastre o endereço de retorno exatamente como exibido e teste com uma conta de cliente.');
    }
}
