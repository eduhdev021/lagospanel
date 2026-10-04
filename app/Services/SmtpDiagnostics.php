<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class SmtpDiagnostics
{
    public function send(string $recipient): array
    {
        $testId = 'lagos-mail-'.Str::lower(Str::random(24));
        $sent = Mail::mailer()->raw(
            'Teste SMTP do '.config('app.name')."\n\nIdentificador: {$testId}\n\nEste teste confirma apenas a aceitação pelo transportador e procura o identificador nos logs disponíveis.",
            function ($message) use ($recipient, $testId): void {
                $message->to($recipient)->subject('Teste SMTP do painel');
                $message->getHeaders()->addTextHeader('X-Lagos-Mail-Test-ID', $testId);
            }
        );

        if (! $sent) {
            return ['test_id' => $testId, 'transport' => config('mail.default'), 'accepted' => false, 'message_id' => null, 'log_status' => 'not_sent', 'log_files' => [], 'log_evidence' => []];
        }

        $messageId = $sent->getMessageId();
        $logs = $this->inspectLogs($testId, $messageId);

        return ['test_id' => $testId, 'transport' => config('mail.default'), 'accepted' => true, 'message_id' => $messageId, ...$logs];
    }

    private function inspectLogs(string $testId, string $messageId): array
    {
        $paths = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) config('mail.server_log_paths', ''))))));
        foreach (['/var/log/mail.log', '/var/log/maillog', '/var/log/mail.err', storage_path('logs/mail.log'), storage_path('logs/laravel.log')] as $path) {
            if (! in_array($path, $paths, true)) {
                $paths[] = $path;
            }
        }

        $files = [];
        $evidence = [];
        foreach ($paths as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                continue;
            }
            $files[] = $path;
            $contents = @file_get_contents($path);
            if (! is_string($contents)) {
                continue;
            }
            $lines = preg_split('/\R/', $contents) ?: [];
            foreach (array_slice($lines, -5000) as $line) {
                if (! str_contains($line, $testId) && ! str_contains($line, $messageId)) {
                    continue;
                }
                $safe = preg_replace('/(password|secret|token)=\S+/i', '$1=[redacted]', $line) ?? $line;
                $evidence[] = mb_substr($safe, 0, 500);
            }
        }

        if ($evidence === []) {
            return ['log_status' => $files === [] ? 'unavailable' : 'not_found', 'log_files' => $files, 'log_evidence' => []];
        }
        $joined = strtolower(implode("\n", $evidence));
        $confirmed = preg_match('/\b(status=sent|status=delivered|delivered|queued|250\s+2\.0\.0|accepted)\b/', $joined) === 1;

        return ['log_status' => $confirmed ? 'accepted_in_server_log' : 'correlation_found', 'log_files' => $files, 'log_evidence' => array_slice($evidence, -10)];
    }
}
