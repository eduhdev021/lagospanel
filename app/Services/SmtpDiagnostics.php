<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

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

    /**
     * Tests the server without sending a message. It verifies DNS, TCP, TLS,
     * SMTP capabilities and credentials, and returns only safe diagnostics.
     *
     * @param array{host:string,port:int,scheme:string,username:?string,password:?string,from:?string} $settings
     */
    public function connection(array $settings): array
    {
        $started = microtime(true);
        $steps = [];
        $host = trim($settings['host']);
        $port = (int) $settings['port'];
        $scheme = $settings['scheme'] === 'smtps' ? 'smtps' : 'smtp';
        $username = trim((string) ($settings['username'] ?? ''));
        $password = (string) ($settings['password'] ?? '');

        $resolved = gethostbyname($host);
        if ($resolved === $host || filter_var($resolved, FILTER_VALIDATE_IP) === false) {
            $steps[] = $this->step('DNS', false, 'Não foi possível resolver o host informado.', ['host' => $host]);

            return $this->connectionResult($host, $port, $scheme, $steps, $started);
        }
        $steps[] = $this->step('DNS', true, 'Host resolvido.', ['ip' => $resolved]);

        $socket = null;
        try {
            $errno = 0;
            $error = '';
            $target = $scheme === 'smtps' ? 'tls://'.$host : $host;
            $socket = @stream_socket_client($target.':'.$port, $errno, $error, 12, STREAM_CLIENT_CONNECT);
            if (! is_resource($socket)) {
                throw new RuntimeException('connection_failed');
            }
            stream_set_timeout($socket, 12);
            $steps[] = $this->step('TCP', true, 'Conexão TCP estabelecida.', ['host' => $host, 'port' => $port]);

            $greeting = $this->readResponse($socket);
            $this->assertCode($greeting, [220]);
            $steps[] = $this->step('SMTP greeting', true, 'Servidor respondeu corretamente.', ['code' => $greeting['code']]);

            $ehlo = $this->command($socket, 'EHLO lagospanel');
            $this->assertCode($ehlo, [250]);
            $steps[] = $this->step('EHLO', true, 'Servidor identificou o painel.', ['code' => $ehlo['code'], 'capabilities' => $this->capabilities($ehlo['lines'])]);

            if ($scheme === 'smtp') {
                $hasStartTls = collect($ehlo['lines'])->contains(fn (string $line) => str_contains(strtoupper($line), 'STARTTLS'));
                if (! $hasStartTls) {
                    throw new RuntimeException('starttls_not_advertised');
                }
                $tls = $this->command($socket, 'STARTTLS');
                $this->assertCode($tls, [220]);
                $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($crypto !== true) {
                    throw new RuntimeException('tls_handshake_failed');
                }
                $steps[] = $this->step('TLS', true, 'Handshake TLS concluído e certificado aceito.');
                $ehlo = $this->command($socket, 'EHLO lagospanel');
                $this->assertCode($ehlo, [250]);
            } else {
                $steps[] = $this->step('TLS', true, 'TLS implícito ativo desde a abertura da conexão.');
            }

            if ($username === '' || $password === '') {
                throw new RuntimeException('credentials_missing');
            }
            $auth = $this->authenticate($socket, $username, $password, $ehlo['lines']);
            $this->assertCode($auth, [235]);
            $steps[] = $this->step('Autenticação', true, 'Usuário e senha SMTP aceitos.', ['code' => $auth['code']]);
            $this->command($socket, 'QUIT', [221, 250]);
        } catch (RuntimeException $e) {
            $steps[] = $this->step($this->failureLabel($e->getMessage()), false, $this->failureMessage($e->getMessage()));
            if (is_resource($socket)) {
                @fwrite($socket, "QUIT\r\n");
            }
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }

        return $this->connectionResult($host, $port, $scheme, $steps, $started);
    }

    private function connectionResult(string $host, int $port, string $scheme, array $steps, float $started): array
    {
        $failed = collect($steps)->firstWhere('ok', false);

        return [
            'ok' => $failed === null,
            'host' => $host,
            'port' => $port,
            'scheme' => $scheme,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'steps' => $steps,
            'summary' => $failed ? 'Falhou na etapa '.$failed['label'].'.' : 'Conexão, TLS e autenticação SMTP confirmados.',
        ];
    }

    private function step(string $label, bool $ok, string $message, array $meta = []): array
    {
        return ['label' => $label, 'ok' => $ok, 'message' => $message, 'meta' => $meta];
    }

    private function readResponse($socket): array
    {
        $lines = [];
        while (($line = fgets($socket, 2048)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $lines[] = $line;
            if (preg_match('/^\d{3} /', $line)) {
                break;
            }
        }
        if ($lines === []) {
            throw new RuntimeException('empty_response');
        }
        $last = end($lines);
        $code = (int) substr($last, 0, 3);

        return ['code' => $code, 'lines' => $lines];
    }

    private function command($socket, string $command, array $accepted = []): array
    {
        if (@fwrite($socket, $command."\r\n") === false) {
            throw new RuntimeException('write_failed');
        }
        $response = $this->readResponse($socket);
        if ($accepted !== [] && ! in_array($response['code'], $accepted, true)) {
            throw new RuntimeException('smtp_code_'.$response['code']);
        }

        return $response;
    }

    private function authenticate($socket, string $username, string $password, array $capabilities): array
    {
        $plain = $this->command($socket, 'AUTH PLAIN '.base64_encode("\0{$username}\0{$password}"));
        if (in_array($plain['code'], [235], true)) {
            return $plain;
        }
        if (! in_array($plain['code'], [500, 501, 502, 504, 535], true)) {
            return $plain;
        }

        $login = $this->command($socket, 'AUTH LOGIN');
        $this->assertCode($login, [334]);
        $login = $this->command($socket, base64_encode($username));
        $this->assertCode($login, [334]);

        return $this->command($socket, base64_encode($password));
    }

    private function assertCode(array $response, array $accepted): void
    {
        if (! in_array($response['code'], $accepted, true)) {
            throw new RuntimeException('smtp_code_'.$response['code']);
        }
    }

    private function capabilities(array $lines): array
    {
        return array_values(array_filter(array_map(static function (string $line): ?string {
            $value = trim(substr($line, 4));
            return $value !== '' && preg_match('/^[A-Z0-9][A-Z0-9_-]*/i', $value, $m) ? strtoupper($m[0]) : null;
        }, $lines)));
    }

    private function failureLabel(string $code): string
    {
        return match (true) {
            $code === 'connection_failed' => 'TCP',
            $code === 'starttls_not_advertised' || $code === 'tls_handshake_failed' => 'TLS',
            $code === 'credentials_missing' || str_starts_with($code, 'smtp_code_535') => 'Autenticação',
            default => 'SMTP',
        };
    }

    private function failureMessage(string $code): string
    {
        return match (true) {
            $code === 'connection_failed' => 'Não foi possível abrir a conexão com o host e porta informados.',
            $code === 'starttls_not_advertised' => 'O servidor não anunciou STARTTLS nesta porta. Confira porta e modo de segurança.',
            $code === 'tls_handshake_failed' => 'A conexão abriu, mas o handshake TLS falhou ou o certificado não foi aceito.',
            $code === 'credentials_missing' => 'Informe usuário e senha SMTP, ou salve uma senha antes de testar.',
            str_starts_with($code, 'smtp_code_535') => 'O servidor recusou usuário ou senha SMTP.',
            str_starts_with($code, 'smtp_code_') => 'O servidor respondeu com um código SMTP inesperado.',
            default => 'O servidor encerrou ou não respondeu corretamente durante o diagnóstico.',
        };
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
