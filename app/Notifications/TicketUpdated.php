<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Support\EmailTemplate;

class TicketUpdated extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public function __construct(public int $ticketId)
    {
        $this->onConnection('database')->beforeCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return Ticket::whereKey($this->ticketId)->where('user_id', $notifiable->id)->exists();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return EmailTemplate::message('Atualização do chamado #'.$this->ticketId, ['eyebrow' => 'ATENDIMENTO', 'title' => 'A equipe respondeu ao seu chamado', 'greeting' => 'Olá, '.$notifiable->name.'!', 'intro' => 'Há uma nova mensagem no seu atendimento. Consulte o histórico completo e responda pelo painel.', 'details' => [['label' => 'Chamado', 'value' => '#'.$this->ticketId], ['label' => 'Canal', 'value' => 'Central de suporte']], 'action_url' => url('/painel/suporte'), 'action_label' => 'Abrir atendimento']);
    }
}
