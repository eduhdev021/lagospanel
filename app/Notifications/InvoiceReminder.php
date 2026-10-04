<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Support\EmailTemplate;

class InvoiceReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public function __construct(public int $invoiceId, public string $stage)
    {
        $this->onConnection('database')->beforeCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return Invoice::whereKey($this->invoiceId)->where('user_id', $notifiable->id)->whereIn('status', ['unpaid', 'overdue'])->exists();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $i = Invoice::findOrFail($this->invoiceId);

        $overdue = $i->due_date->isPast();
        return EmailTemplate::message(($overdue ? 'Fatura vencida' : 'Lembrete de pagamento').' · #'.$i->id, ['eyebrow' => $overdue ? 'ATENÇÃO AO VENCIMENTO' : 'LEMBRETE DE PAGAMENTO', 'title' => $overdue ? 'Sua fatura está vencida' : 'Sua fatura vence em breve', 'greeting' => 'Olá, '.$notifiable->name.'!', 'intro' => $overdue ? 'Identificamos uma fatura em aberto após a data de vencimento. Regularize para evitar interrupções.' : 'Este é um lembrete amigável de que existe uma fatura aguardando pagamento.', 'status' => status_label($i->status), 'details' => [['label' => 'Fatura', 'value' => '#'.$i->id], ['label' => 'Valor', 'value' => brl($i->total_minor)], ['label' => 'Vencimento', 'value' => $i->due_date->format('d/m/Y')]], 'action_url' => url('/painel/faturas/'.$i->id), 'action_label' => 'Regularizar fatura', 'note' => 'Se você já efetuou o pagamento, aguarde a confirmação ou fale com o financeiro.']);
    }
}
