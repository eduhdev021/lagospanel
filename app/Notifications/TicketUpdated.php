<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
        return (new MailMessage)->subject('Atualização do chamado #'.$this->ticketId)->line('A equipe respondeu ao seu chamado. Entre no painel para consultar a mensagem e os anexos.')->action('Consultar chamados', url('/painel/suporte'));
    }
}
