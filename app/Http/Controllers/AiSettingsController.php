<?php

namespace App\Http\Controllers;

use App\Models\AiSetting;
use App\Services\AdminConfirmation;
use App\Services\AiDiagnostics;
use App\Services\AiFailure;
use App\Services\Audit;
use App\Services\Ollama;
use App\Services\WebResearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AiSettingsController extends Controller
{
    public function index()
    {
        return view('admin.ai-settings', ['setting' => AiSetting::find(1), 'diagnostics' => app(AiDiagnostics::class)->report()]);
    }

    public function save(Request $r)
    {
        $v = $r->validate(['endpoint' => 'required|string|max:255', 'token' => 'nullable|string|min:8|max:2000', 'clear_token' => 'sometimes|boolean', 'model' => 'nullable|string|max:180', 'active' => 'sometimes|boolean', 'version' => 'required|integer|min:0', 'web_enabled' => 'sometimes|boolean', 'web_token' => 'nullable|string|min:8|max:2000', 'clear_web_token' => 'sometimes|boolean']);
        $endpoint = Ollama::origin($v['endpoint']);
        if (! empty($v['token']) && preg_match('/[\r\n]/', $v['token'])) {
            throw ValidationException::withMessages(['token' => 'Chave inválida.']);
        }
        if (! empty($v['web_token']) && preg_match('/[\r\n]/', $v['web_token'])) {
            throw ValidationException::withMessages(['web_token' => 'Chave de pesquisa inválida.']);
        }
        if ($r->boolean('active')) {
            $r->validate(['ack' => 'accepted']);
        }
        DB::transaction(function () use ($r, $v, $endpoint) {
            // A stable lock also serializes the first singleton creation.
            DB::table('users')->where('id', $r->user()->id)->lockForUpdate()->first();
            DB::table('ai_settings')->insertOrIgnore(['id' => 1, 'endpoint' => 'https://ollama.com', 'version' => 0, 'active' => false, 'created_at' => now(), 'updated_at' => now()]);
            $s = AiSetting::lockForUpdate()->findOrFail(1);
            abort_unless($s->version === (int) $v['version'], 409, 'Configuração alterada. Recarregue antes de salvar.');
            $changed = $s->endpoint !== $endpoint || ! empty($v['token']) || $r->boolean('clear_token');
            $model = $changed ? null : ($v['model'] ?? null);
            $models = $changed ? [] : ($s->models ?? []);
            if ($model && ! in_array($model, $models, true)) {
                throw ValidationException::withMessages(['model' => 'Escolha um modelo do catálogo consultado com esta configuração.']);
            }
            if ($r->boolean('active') && (! $model || $changed)) {
                throw ValidationException::withMessages(['model' => 'Salve a conexão desativada, consulte os modelos e selecione um antes de habilitar.']);
            }
            $data = ['endpoint' => $endpoint, 'model' => $model, 'models' => $models, 'active' => $r->boolean('active'), 'version' => $s->version + 1];
            if ($changed) {
                $data['models_checked_at'] = null;
            }
            if ($r->boolean('clear_token')) {
                $data['token'] = null;
            } elseif (! empty($v['token'])) {
                $data['token'] = $v['token'];
            }
            $data['web_enabled'] = $r->boolean('web_enabled');
            if ($r->boolean('clear_web_token')) {
                $data['web_token'] = null;
            } elseif (! empty($v['web_token'])) {
                $data['web_token'] = $v['web_token'];
            }
            $s->fill($data);
            if ($s->web_enabled && ! WebResearch::key($s)) {
                throw ValidationException::withMessages(['web_token' => 'Informe chave Ollama Cloud de pesquisa, ou use a chave principal quando a origem for https://ollama.com.']);
            }
            $s->save();
            Audit::record('ai.configured', 'ai:1', ['active' => $s->active, 'version' => $s->version], $r->user()->id);
        }, 5);

        return back()->with('status', 'Configuração salva. Chave não será exibida. Alterações invalidam solicitações de IA ainda pendentes.');
    }

    public function probe(Request $r, Ollama $ollama, AdminConfirmation $confirmation)
    {
        $r->validate(['ack' => 'accepted']);
        $confirmation->verify($r);
        $s = AiSetting::findOrFail(1);
        abort_unless($s->model && in_array($s->model, $s->models ?? [], true), 409, 'Selecione e salve um modelo do catálogo antes de testar.');
        try {
            $ollama->chat($s, [['role' => 'user', 'content' => 'Responda apenas OK. Este é um teste de conexão.']]);
            $status = 'ok';
        } catch (\Throwable $error) {
            $status = AiFailure::code($error);
        }
        $saved = DB::transaction(function () use ($s, $status, $r) {
            $current = AiSetting::lockForUpdate()->findOrFail(1);
            if ($current->version !== $s->version) {
                return false;
            }$current->update(['probe_status' => $status, 'probe_version' => $s->version, 'probe_checked_at' => now()]);
            Audit::record('ai.generation_tested', 'ai:1', ['status' => $status, 'version' => $s->version], $r->user()->id);

            return true;
        });
        if (! $saved) {
            return back()->withErrors(['probe' => 'A configuração mudou durante o teste. Teste novamente a versão atual.']);
        }

        return $status === 'ok' ? back()->with('status', 'O provedor gerou uma resposta de teste. Isso confirma a geração direta, não o funcionamento da fila.') :
            back()->withErrors(['probe' => AiFailure::message($status)]);
    }

    public function models(Request $r, Ollama $ollama)
    {
        $s = AiSetting::findOrFail(1);
        try {
            $models = $ollama->models($s);
        } catch (\Throwable $error) {
            return back()->withErrors(['models' => AiFailure::message(AiFailure::code($error))]);
        }
        DB::transaction(function () use ($s, $models, $r) {
            $current = AiSetting::lockForUpdate()->findOrFail(1);
            abort_unless($current->version === $s->version, 409, 'Conexão mudou durante a consulta.');
            $data = ['models' => $models, 'models_checked_at' => now()];
            if (! in_array($current->model, $models, true)) {
                $data += ['model' => null, 'active' => false, 'version' => $current->version + 1];
            }
            $current->update($data);
            Audit::record('ai.models_refreshed', 'ai:1', ['count' => count($models)], $r->user()->id);
        });

        return back()->with('status', count($models) ? 'Catálogo consultado. Selecione o modelo e salve. A listagem não garante cota ou autorização de inferência.' : 'A API não retornou modelos disponíveis.');
    }
}
