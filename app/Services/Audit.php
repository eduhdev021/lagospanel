<?php

namespace App\Services;

use App\Models\AuditEvent;

final class Audit
{
    public static function record(string $event, string $subject, array $context = [], ?int $actor = null): void
    {
        AuditEvent::create(['user_id' => $actor, 'event' => $event, 'subject' => $subject, 'context' => $context]);
    }
}
