<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Services\SupportDesk;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

class SupportController extends Controller
{
    public function thread(Request $r, Ticket $ticket)
    {
        $admin = $r->routeIs('admin.*');
        abort_unless($admin || $ticket->user_id === $r->user()->id, 404);
        $ticket->load(['user', 'assignee', 'attachments' => fn ($q) => $q->whereNull('ticket_reply_id')->when(! $admin, fn ($q) => $q->where('is_internal', false))]);
        $replies = $ticket->replies()->when(! $admin, fn ($q) => $q->where('is_internal', false))->with('user', 'attachments')->latest('id')->paginate(30);
        $ticket->setRelation('replies', $replies->getCollection());
        $staff = $admin ? User::with('staffRole')->where(fn ($q) => $q->where('is_admin', true)->orWhereNotNull('staff_role_id'))->get()->filter(fn ($u) => $u->hasPermission('support.view') && $u->hasPermission('support.manage')) : collect();

        return view($admin ? 'admin.tickets' : 'client.tickets', ['tickets' => new LengthAwarePaginator([$ticket], 1, 1), 'threadReplies' => $replies, 'staff' => $staff]);
    }

    public function triage(Request $r, Ticket $ticket, SupportDesk $desk)
    {
        $v = $r->validate(['priority' => 'required|in:low,normal,high,urgent', 'assigned_to' => 'nullable|integer|exists:users,id']);
        $desk->triage($ticket, $r->user(), $v['priority'], isset($v['assigned_to']) ? (int) $v['assigned_to'] : null);

        return back()->with('status', 'Triagem atualizada.');
    }

    public function state(Request $r, Ticket $ticket, SupportDesk $desk)
    {
        $v = $r->validate(['status' => 'required|in:open,closed']);
        $desk->state($ticket, $r->user(), $v['status']);

        return back()->with('status', 'Estado do chamado atualizado.');
    }

    public function download(Request $r, TicketAttachment $attachment)
    {
        if (! $r->routeIs('admin.*')) {
            abort_unless($attachment->ticket->user_id === $r->user()->id && ! $attachment->is_internal, 404);
        }
        try {
            $bytes = base64_decode($attachment->payload, true);
        } catch (DecryptException $e) {
            abort(409, 'Anexo indisponível: falha de integridade.');
        }
        abort_unless($bytes !== false && hash_equals($attachment->sha256, hash('sha256', $bytes)), 409, 'Anexo indisponível: falha de integridade.');

        return response($bytes, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $attachment->filename, preg_replace('/[^A-Za-z0-9._-]/', '_', Str::ascii($attachment->filename))),
            'Cache-Control' => 'no-store, private', 'Content-Security-Policy' => "default-src 'none'; sandbox", 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
