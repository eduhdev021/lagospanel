<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Notifications\TicketUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SupportDesk
{
    public const PRIORITIES = ['low' => 48, 'normal' => 24, 'high' => 8, 'urgent' => 2];

    public const LABELS = ['low' => 'Baixa', 'normal' => 'Normal', 'high' => 'Alta', 'urgent' => 'Urgente'];

    public const FILE_RULES = ['attachments' => 'nullable|array|max:3', 'attachments.*' => 'required|file|max:2048'];

    public function prepare(array $files): array
    {
        if (count($files) > 3) {
            throw ValidationException::withMessages(['attachments' => 'Máximo de 3 arquivos por mensagem.']);
        }
        $allowed = ['text/plain' => ['txt'], 'image/png' => ['png'], 'image/jpeg' => ['jpg', 'jpeg'], 'application/pdf' => ['pdf']];
        $prepared = [];
        foreach ($files as $file) {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
            if (! $file->isValid() || $file->getSize() > 2097152 || $file->getSize() < 1 || ! in_array(strtolower($file->getClientOriginalExtension()), $allowed[$mime] ?? [], true)) {
                throw ValidationException::withMessages(['attachments' => 'Use TXT, PNG, JPG ou PDF válido, não vazio, de até 2 MiB.']);
            }
            $bytes = $file->get();
            if (! mb_check_encoding($file->getClientOriginalName(), 'UTF-8')) {
                throw ValidationException::withMessages(['attachments' => 'Nome de arquivo inválido.']);
            }
            $filename = mb_substr(preg_replace('/[\x00-\x1f\x7f]/u', '_', basename(str_replace('\\', '/', $file->getClientOriginalName()))), 0, 180);
            $prepared[] = ['filename' => $filename, 'mime' => $mime, 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'payload' => base64_encode($bytes)];
        }

        return $prepared;
    }

    private function attach(Ticket $ticket, User $user, array $files, ?int $reply = null, bool $internal = false): void
    {
        // Caller holds the ticket lock; all metadata and encrypted bytes commit together.
        if (! $files) {
            return;
        }
        User::whereKey($ticket->user_id)->lockForUpdate()->firstOrFail();
        $used = TicketAttachment::whereIn('ticket_id', Ticket::select('id')->where('user_id', $ticket->user_id))->sum('size');
        if ($used + array_sum(array_column($files, 'size')) > config('support.account_attachment_bytes')) {
            throw ValidationException::withMessages(['attachments' => 'Quota de anexos da conta atingida. Respostas sem arquivos continuam disponíveis.']);
        }
        $query = TicketAttachment::where('ticket_id', $ticket->id);
        if ($query->count() + count($files) > 30 || $query->sum('size') + array_sum(array_column($files, 'size')) > 10485760) {
            throw ValidationException::withMessages(['attachments' => 'Limite do chamado: 30 arquivos e 10 MiB no total.']);
        }
        foreach ($files as $file) {
            TicketAttachment::create($file + ['ticket_id' => $ticket->id, 'ticket_reply_id' => $reply, 'user_id' => $user->id, 'is_internal' => $internal]);
        }
    }

    public function open(User $user, array $data, array $files = []): Ticket
    {
        return DB::transaction(function () use ($user, $data, $files) {
            if ($files) {
                User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            }
            $priority = $data['priority'] ?? 'normal';
            $hours = self::PRIORITIES[$priority];
            $ticket = $user->tickets()->create(['subject' => $data['subject'], 'body' => $data['body'], 'department' => $data['department'], 'priority' => $priority, 'sla_hours' => $hours, 'response_due_at' => now()->addHours($hours)]);
            $this->attach($ticket, $user, $files);
            Audit::record('ticket.created', 'ticket:'.$ticket->id, ['priority' => $priority], $user->id);

            return $ticket;
        }, 5);
    }

    public function reply(Ticket $ticket, User $user, string $body, array $files = [], bool $staff = false, string $status = 'answered', bool $internal = false): void
    {
        DB::transaction(function () use ($ticket, $user, $body, $files, $staff, $status, $internal) {
            $ticket = Ticket::lockForUpdate()->findOrFail($ticket->id);
            abort_unless($staff ? $user->hasPermission('support.manage') : $ticket->user_id === $user->id, 403);
            abort_if(! $staff && $ticket->status === 'closed', 422, 'Ticket encerrado. Reabra antes de responder.');
            abort_if(! $staff && $internal, 403);
            abort_unless(in_array($status, ['answered', 'closed'], true), 422);
            if ($files) {
                User::whereIn('id', [$ticket->user_id, $user->id])->orderBy('id')->lockForUpdate()->get();
            }
            $reply = $ticket->replies()->create(['user_id' => $user->id, 'body' => $body, 'is_staff' => $staff, 'is_internal' => $internal]);
            $this->attach($ticket, $user, $files, $reply->id, $internal);
            if (! $internal) {
                if ($staff) {
                    $ticket->fill(['status' => $status, 'response_due_at' => null, 'first_responded_at' => $ticket->first_responded_at ?? now(), 'closed_at' => $status === 'closed' ? now() : null]);
                } else {
                    $ticket->fill(['status' => 'customer_reply', 'response_due_at' => $ticket->response_due_at ?? now()->addHours($ticket->sla_hours), 'closed_at' => null]);
                }
                $ticket->save();
                if ($staff && $ticket->user_id !== $user->id) {
                    $ticket->user->notify(new TicketUpdated($ticket->id));
                }
            }
            Audit::record($internal ? 'ticket.note' : 'ticket.reply', 'ticket:'.$ticket->id, ['reply' => $reply->id, 'staff' => $staff, 'internal' => $internal], $user->id);
        }, 5);
    }

    public function triage(Ticket $ticket, User $actor, string $priority, ?int $assignee): void
    {
        abort_unless($actor->hasPermission('support.manage'), 403);
        DB::transaction(function () use ($ticket, $actor, $priority, $assignee) {
            $ticket = Ticket::lockForUpdate()->findOrFail($ticket->id);
            if ($assignee) {
                $user = User::find($assignee);
                if (! $user || ! $user->hasPermission('support.view') || ! $user->hasPermission('support.manage')) {
                    throw ValidationException::withMessages(['assigned_to' => 'Responsável precisa das permissões de visualizar e gerenciar suporte.']);
                }
            }
            $hours = self::PRIORITIES[$priority];
            if (in_array($ticket->status, ['open', 'customer_reply'], true)) {
                $due = now()->addHours($hours);
                $ticket->response_due_at = $ticket->response_due_at?->min($due) ?? $due;
            }
            $ticket->fill(['priority' => $priority, 'sla_hours' => $hours, 'assigned_to' => $assignee])->save();
            Audit::record('ticket.triage', 'ticket:'.$ticket->id, ['priority' => $priority, 'assigned_to' => $assignee], $actor->id);
        }, 5);
    }

    public function state(Ticket $ticket, User $user, string $state): void
    {
        DB::transaction(function () use ($ticket, $user, $state) {
            $ticket = Ticket::lockForUpdate()->findOrFail($ticket->id);
            abort_unless($ticket->user_id === $user->id, 404);
            abort_unless(in_array($state, ['closed', 'open'], true), 422);
            if ($state === 'closed' && $ticket->status !== 'closed') {
                $ticket->update(['status' => 'closed', 'closed_at' => now(), 'response_due_at' => null]);
            } elseif ($state === 'open' && $ticket->status === 'closed') {
                $ticket->update(['status' => 'open', 'closed_at' => null, 'response_due_at' => now()->addHours($ticket->sla_hours)]);
            } else {
                return;
            }
            Audit::record('ticket.state', 'ticket:'.$ticket->id, ['status' => $ticket->status], $user->id);
        }, 5);
    }
}
