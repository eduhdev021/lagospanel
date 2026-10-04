<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Support\EmailTemplate;

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
        $invoice = Invoice::findOrFail($this->invoiceId);
        $subject = $this->paid ? 'Pagamento confirmado · Fatura #'.$invoice->id : 'Nova fatura disponível · #'.$invoice->id;
        return EmailTemplate::message($subject, ['eyebrow' => $this->paid ? 'PAGAMENTO CONFIRMADO' : 'NOVA FATURA', 'title' => $this->paid ? 'Pagamento recebido' : 'Sua fatura está pronta', 'greeting' => 'Olá, '.$notifiable->name.'!', 'intro' => $this->paid ? 'Recebemos o pagamento e atualizamos o status da sua fatura.' : 'Uma nova fatura foi criada para você. Consulte os detalhes e escolha a melhor forma de pagamento.', 'status' => status_label($invoice->status), 'details' => [['label' => 'Fatura', 'value' => '#'.$invoice->id], ['label' => 'Total', 'value' => brl($invoice->total_minor)], ['label' => 'Vencimento', 'value' => $invoice->due_date->format('d/m/Y')]], 'action_url' => url('/painel/faturas/'.$invoice->id), 'action_label' => $this->paid ? 'Ver pagamento' : 'Ver e pagar fatura', 'note' => $this->paid ? 'A ativação do serviço pode depender da equipe ou da integração configurada.' : 'Se você já pagou, aguarde a confirmação do gateway.']);
    }
}
