<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public function __construct(public int $invoiceId, public bool $paid = false)
    {
        $this->onConnection('database')->beforeCommit();
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return Invoice::whereKey($this->invoiceId)->where('user_id', $notifiable->id)->whereIn('status', $this->paid ? ['paid'] : ['unpaid', 'overdue'])->exists();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->paid ? 'Pagamento confirmado' : 'Nova fatura LagosPanel')->greeting('Olá, '.$notifiable->name)->line($this->paid ? 'Seu pagamento foi registrado. A ativação do serviço pode depender da equipe ou da integração.' : 'Uma fatura está disponível na sua conta.')->action('Ver fatura', url('/painel/faturas/'.$this->invoiceId));
    }
}
