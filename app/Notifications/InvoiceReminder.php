<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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

        return (new MailMessage)->subject('Lembrete de fatura #'.$i->id)->greeting('Olá, '.$notifiable->name)->line('A fatura #'.$i->id.' de '.brl($i->total_minor).' vence em '.$i->due_date->format('d/m/Y').'.')->line('Se já efetuou o pagamento e ele ainda não foi identificado, entre em contato com o financeiro.')->action('Ver fatura', url('/painel/faturas/'.$i->id));
    }
}
