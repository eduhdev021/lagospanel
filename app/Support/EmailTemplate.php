<?php

namespace App\Support;

final class EmailTemplate
{
    public static function message(string $subject, array $data): object
    {
        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject($subject)
            ->view('emails.transactional', ['subject' => $subject] + $data)
            ->text('emails.transactional-text', ['subject' => $subject] + $data);
    }
}
