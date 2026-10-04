<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\ShouldBeEncrypted;
use Illuminate\Contracts\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use App\Support\EmailTemplate;

class ResetPasswordQueued extends ResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public function __construct($token)
    {
        parent::__construct($token);
        $this->onConnection('database')->beforeCommit();
    }

    public function toMail($notifiable): MailMessage
    {
        return EmailTemplate::message('Redefinição de senha · '.config('app.name'), ['eyebrow' => 'SEGURANÇA DA CONTA', 'title' => 'Crie uma nova senha', 'greeting' => 'Olá, '.$notifiable->name.'!', 'intro' => 'Recebemos uma solicitação para redefinir a senha da sua conta. Se foi você, continue pelo botão abaixo.', 'action_url' => $this->resetUrl($notifiable), 'action_label' => 'Redefinir minha senha', 'note' => 'O link expira em '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutos. Se você não solicitou isso, sua senha permanece inalterada.']);
    }
}
