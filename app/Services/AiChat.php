<?php

namespace App\Services;

use App\Jobs\AnswerAiTurn;
use App\Models\AiSetting;
use App\Models\AiThread;
use App\Models\AiTurn;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AiChat
{
    public function send(User $user, AiThread $thread, string $text, string $key, ?string $query = null): AiTurn
    {
        return DB::transaction(function () use ($user, $thread, $text, $key, $query) {
            $u = User::lockForUpdate()->findOrFail($user->id);
            $t = AiThread::lockForUpdate()->findOrFail($thread->id);
            abort_unless($t->user_id === $u->id, 404);
            if ($old = $t->turns()->where('request_key', $key)->first()) {
                abort_unless(hash_equals($old->user_text, $text) && $old->web_query === $query, 409, 'Referência usada para outra mensagem.');

                return $old;
            }
            $s = AiSetting::find(1);
            abort_unless($s?->active && $s->model && in_array($s->model, $s->models ?? [], true), 409, 'Chat de IA indisponível. Abra um chamado humano.');
            if ($query !== null) {
                abort_unless($s->web_enabled && WebResearch::key($s), 409, 'Pesquisa web indisponível.');
            }
            // Recover stale local workers without ever resending an uncertain inference.
            AiTurn::whereIn('ai_thread_id', AiThread::where('user_id', $u->id)->select('id'))->whereIn('status', ['queued', 'processing'])->where('updated_at', '<', now()->subMinutes(5))->update(['status' => 'failed', 'execution_token' => null, 'failure_code' => 'worker_expired']);
            abort_if(AiTurn::whereIn('ai_thread_id', AiThread::where('user_id', $u->id)->select('id'))->whereIn('status', ['queued', 'processing'])->exists(), 409, 'Aguarde a resposta pendente.');
            abort_if($t->turns()->count() >= 20, 422, 'Limite de 20 mensagens por conversa. Crie outra conversa.');
            $day = today()->toDateString();
            DB::table('ai_daily_usages')->insertOrIgnore(['user_id' => $u->id, 'day' => $day, 'requests' => 0]);
            $usage = DB::table('ai_daily_usages')->where('user_id', $u->id)->where('day', $day);
            abort_if($usage->value('requests') >= config('ai.daily_requests'), 429, 'Limite diário de IA atingido. O suporte humano continua disponível.');
            $usage->increment('requests');
            $turn = $t->turns()->create(['request_key' => $key, 'user_text' => $text, 'settings_version' => $s->version, 'model' => $s->model, 'status' => 'queued', 'web_requested' => $query !== null, 'web_query' => $query]);
            $t->touch();
            AnswerAiTurn::dispatch($turn->id)->onConnection('database')->afterCommit();
            Audit::record('ai.message_queued', 'ai_turn:'.$turn->id, [], $u->id);

            return $turn;
        }, 5);
    }
}
