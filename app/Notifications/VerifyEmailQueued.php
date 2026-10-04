<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use App\Support\EmailTemplate;

class VerifyEmailQueued extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public function __construct()
    {
        $this->onConnection('database')->beforeCommit();
    }

    public function toMail($notifiable): MailMessage
    {
        return EmailTemplate::message('Confirme seu e-mail · '.config('app.name'), ['eyebrow' => 'ATIVAÇÃO DA CONTA', 'title' => 'Confirme seu endereço de e-mail', 'greeting' => 'Olá, '.$notifiable->name.'!', 'intro' => 'Falta só uma etapa para liberar todos os recursos da sua conta: confirmar que este e-mail pertence a você.', 'action_url' => $this->verificationUrl($notifiable), 'action_label' => 'Confirmar meu e-mail', 'note' => 'Este link expira em '.config('auth.verification.expire', 60).' minutos. Se você não criou esta conta, ignore esta mensagem.']);
    }
}
