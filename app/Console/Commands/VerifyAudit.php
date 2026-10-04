<?php

namespace App\Console\Commands;

use App\Models\AuditEvent;
use Illuminate\Console\Command;

class VerifyAudit extends Command
{
    protected $signature = 'lagos:audit:verify';

    protected $description = 'Verifica a integridade encadeada dos eventos de auditoria.';

    public function handle(): int
    {
        $checked = 0;
        $legacy = 0;
        $previous = null;
        $invalid = null;

        AuditEvent::query()->orderBy('id')->chunkById(500, function ($events) use (&$checked, &$legacy, &$previous, &$invalid) {
            foreach ($events as $event) {
                if (! $event->hash) {
                    $legacy++;
                    continue;
                }

                $payload = [
                    'user_id' => $event->user_id,
                    'event' => $event->event,
                    'subject' => $event->subject,
                    'context' => $event->context,
                    'request_id' => $event->request_id,
                    'ip_address' => $event->ip_address,
                    'user_agent' => $event->user_agent,
                    'occurred_at' => $event->occurred_at,
                    'previous_hash' => $event->previous_hash,
                ];
                $expected = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                if ($event->previous_hash !== $previous || ! hash_equals($event->hash, $expected)) {
                    $invalid = $event->id;

                    return false;
                }
                $previous = $event->hash;
                $checked++;
            }

            return $invalid === null;
        });

        if ($invalid !== null) {
            $this->error("Auditoria inválida no evento #{$invalid}.");

            return self::FAILURE;
        }

        $this->info("Auditoria íntegra: {$checked} eventos verificados; {$legacy} eventos legados sem hash.");

        return self::SUCCESS;
    }
}
