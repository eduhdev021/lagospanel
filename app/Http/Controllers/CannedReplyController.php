<?php

namespace App\Http\Controllers;

use App\Models\CannedReply;
use App\Models\Ticket;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CannedReplyController extends Controller
{
    public function index(Request $r, ?CannedReply $template = null)
    {
        return view('admin.canned-replies', ['templates' => CannedReply::orderBy('title')->orderBy('id')->paginate(20), 'editing' => $template]);
    }

    public function save(Request $r, ?CannedReply $template = null)
    {
        $v = $r->validate(['title' => 'required|string|max:150', 'body' => 'required|string|max:10000', 'department' => 'nullable|in:support,billing', 'active' => 'sometimes|boolean', 'version' => $template ? 'required|integer|min:1' : 'nullable']);
        DB::transaction(function () use ($v, $r, $template) {
            $data = ['title' => $v['title'], 'body' => $v['body'], 'department' => $v['department'] ?? null, 'active' => $r->boolean('active')];
            if ($template) {
                $row = CannedReply::lockForUpdate()->findOrFail($template->id);
                abort_unless($row->version === (int) $v['version'], 409, 'Modelo alterado por outra pessoa. Recarregue antes de editar.');
                $row->update($data + ['version' => $row->version + 1]);
            } else {
                $row = CannedReply::create($data);
            }
            Audit::record('support.template.saved', 'canned_reply:'.$row->id, ['version' => $row->version, 'active' => $row->active], $r->user()->id);
        }, 5);

        return redirect()->route('admin.tickets.templates')->with('status', 'Resposta pronta salva.');
    }

    public function content(Request $r, Ticket $ticket, CannedReply $template)
    {
        abort_unless($template->active && (! $template->department || $template->department === $ticket->department), 404);

        return response()->json(['body' => $template->body])->header('Cache-Control', 'no-store, private');
    }
}
