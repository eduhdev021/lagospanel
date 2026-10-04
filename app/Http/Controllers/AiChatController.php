<?php

namespace App\Http\Controllers;

use App\Models\AiSetting;
use App\Models\AiThread;
use App\Models\AiTurn;
use App\Models\User;
use App\Services\AiChat;
use App\Services\AiFailure;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiChatController extends Controller
{
    public function index(Request $r, ?AiThread $thread = null)
    {
        if ($thread) {
            abort_unless($thread->user_id === $r->user()->id, 404);
        }

        return view('client.ai-chat', ['threads' => AiThread::where('user_id', $r->user()->id)->with('firstTurn')->latest('updated_at')->paginate(20), 'thread' => $thread, 'turns' => $thread?->turns()->orderBy('id')->get() ?? collect(), 'available' => AiSetting::find(1)?->active ?? false, 'webAvailable' => AiSetting::find(1)?->web_enabled ?? false, 'pending' => $thread?->turns()->whereIn('status', ['queued', 'processing'])->exists() ?? false]);
    }

    private function messageRules(bool $consented): array
    {
        return ['body' => 'required|string|max:2000', 'request_key' => 'required|uuid', 'consent' => $consented ? 'sometimes|accepted' : 'accepted', 'web_requested' => 'sometimes|boolean', 'web_query' => 'nullable|required_if:web_requested,1|string|max:400', 'web_consent' => 'accepted_if:web_requested,1'];
    }

    private function payload(AiTurn $turn): array
    {
        return ['id' => $turn->id, 'request_key' => $turn->request_key, 'body' => $turn->user_text, 'status' => $turn->status, 'failure_message' => $turn->status === 'failed' ? AiFailure::message($turn->failure_code) : null, 'answer' => $turn->status === 'done' ? $turn->assistant_text : null, 'web_requested' => $turn->web_requested, 'web_status' => $turn->web_status, 'sources' => $turn->status === 'done' ? array_map(fn ($s) => array_intersect_key($s, array_flip(['id', 'title', 'url'])), $turn->web_sources ?? []) : []];
    }

    private function accepted(Request $r, AiThread $thread, AiTurn $turn)
    {
        if ($r->expectsJson()) {
            return response()->json(['thread' => ['id' => $thread->id, 'title' => $thread->displayTitle(), 'url' => route('ai.show', $thread), 'send_url' => route('ai.send', $thread), 'state_url' => route('ai.state', $thread), 'rename_url' => route('ai.rename', $thread), 'delete_url' => route('ai.delete', $thread)], 'turn' => $this->payload($turn)], 202)->header('Cache-Control', 'no-store, private');
        }

        return redirect()->route('ai.show', $thread);
    }

    public function create(Request $r, AiChat $chat)
    {
        $v = $r->has('body') ? $r->validate($this->messageRules(false)) : null;
        [$t,$turn] = DB::transaction(function () use ($r, $v, $chat) {
            User::lockForUpdate()->findOrFail($r->user()->id);
            $existing = $v ? AiThread::where('user_id', $r->user()->id)->where('creation_key', $v['request_key'])->first() : null;
            if (! $existing) {
                abort_if(AiThread::where('user_id', $r->user()->id)->count() >= 20, 422, 'Limite de 20 conversas. Exclua uma conversa para continuar.');
            }
            $t = $existing ?? AiThread::create(['user_id' => $r->user()->id, 'creation_key' => $v['request_key'] ?? null, 'consented_at' => $v ? now() : null]);
            $turn = $v ? $chat->send($r->user(), $t, $v['body'], $v['request_key'], $r->boolean('web_requested') ? $v['web_query'] : null) : null;

            return [$t, $turn];
        }, 5);

        return $turn ? $this->accepted($r, $t, $turn) : redirect()->route('ai.show', $t);
    }

    public function send(Request $r, AiThread $thread, AiChat $chat)
    {
        abort_unless($thread->user_id === $r->user()->id, 404);
        $v = $r->validate($this->messageRules((bool) $thread->consented_at));
        $turn = DB::transaction(function () use ($r, $thread, $chat, $v) {
            $turn = $chat->send($r->user(), $thread, $v['body'], $v['request_key'], $r->boolean('web_requested') ? $v['web_query'] : null);
            if (! $thread->consented_at) {
                $thread->update(['consented_at' => now()]);
            }

            return $turn;
        }, 5);

        return $this->accepted($r, $thread, $turn);
    }

    public function rename(Request $r, AiThread $thread)
    {
        abort_unless($thread->user_id === $r->user()->id, 404);
        $v = $r->validate(['title' => 'required|string|max:80']);
        $thread->update($v);

        return $r->expectsJson() ? response()->json(['title' => $thread->title])->header('Cache-Control', 'no-store, private') : back()->with('status', 'Conversa renomeada.');
    }

    public function state(Request $r, AiThread $thread)
    {
        abort_unless($thread->user_id === $r->user()->id, 404);

        // Expire abandoned local leases; never resend an uncertain inference.
        AiTurn::where('ai_thread_id', $thread->id)->whereIn('status', ['queued', 'processing'])->where('updated_at', '<', now()->subMinutes(5))->update(['status' => 'failed', 'execution_token' => null, 'failure_code' => 'worker_expired']);

        return response()->json(['turns' => $thread->turns()->orderBy('id')->get()->map(fn ($t) => $this->payload($t))])->header('Cache-Control', 'no-store, private');
    }

    public function delete(Request $r, AiThread $thread)
    {
        DB::transaction(function () use ($r, $thread) {
            User::lockForUpdate()->findOrFail($r->user()->id);
            $t = AiThread::lockForUpdate()->findOrFail($thread->id);
            abort_unless($t->user_id === $r->user()->id, 404);
            $t->delete();
            Audit::record('ai.conversation_deleted', 'ai_thread:'.$thread->id, [], $r->user()->id);
        });

        return redirect()->route('ai.index')->with('status', 'Conversa removida deste painel. Isso não apaga registros já recebidos pelo provedor ou backups.');
    }
}
