<?php

namespace App\Http\Controllers;

use App\Models\AiSetting;
use App\Models\AiThread;
use App\Models\User;
use App\Services\AiChat;
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

        return view('client.ai-chat', ['threads' => AiThread::where('user_id', $r->user()->id)->latest()->paginate(20), 'thread' => $thread, 'turns' => $thread?->turns()->orderBy('id')->get() ?? collect(), 'available' => AiSetting::find(1)?->active ?? false, 'webAvailable' => AiSetting::find(1)?->web_enabled ?? false]);
    }

    public function create(Request $r)
    {
        $t = DB::transaction(function () use ($r) {
            User::lockForUpdate()->findOrFail($r->user()->id);
            abort_if(AiThread::where('user_id', $r->user()->id)->count() >= 20, 422, 'Apague uma conversa antes de criar outra. Limite: 20.');

            return AiThread::create(['user_id' => $r->user()->id]);
        }, 5);

        return redirect()->route('ai.show', $t);
    }

    public function send(Request $r, AiThread $thread, AiChat $chat)
    {
        abort_unless($thread->user_id === $r->user()->id, 404);
        $v = $r->validate(['body' => 'required|string|max:2000', 'request_key' => 'required|uuid', 'consent' => 'accepted', 'web_requested' => 'sometimes|boolean', 'web_query' => 'nullable|required_if:web_requested,1|string|max:400', 'web_consent' => 'accepted_if:web_requested,1']);
        $chat->send($r->user(), $thread, $v['body'], $v['request_key'], $r->boolean('web_requested') ? $v['web_query'] : null);

        return back()->with('status', 'Mensagem na fila. Aguarde a resposta ou atualize a conversa.');
    }

    public function state(Request $r, AiThread $thread)
    {
        abort_unless($thread->user_id === $r->user()->id, 404);

        return response()->json(['turns' => $thread->turns()->orderBy('id')->get()->map(fn ($t) => ['id' => $t->id, 'status' => $t->status, 'answer' => $t->status === 'done' ? $t->assistant_text : null, 'web_status' => $t->web_status, 'sources' => $t->status === 'done' ? array_map(fn ($s) => array_intersect_key($s, array_flip(['id', 'title', 'url'])), $t->web_sources ?? []) : []])])->header('Cache-Control', 'no-store, private');
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
