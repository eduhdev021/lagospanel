<?php

namespace App\Services;

use App\Models\AuditEvent;
use Illuminate\Support\Facades\DB;

final class Audit
{
    public static function record(string $event, string $subject, array $context = [], ?int $actor = null): void
    {
        DB::transaction(function () use ($event, $subject, $context, $actor) {
            $row = AuditEvent::create(['user_id' => $actor, 'event' => $event, 'subject' => $subject, 'context' => $context]);
            app(OutgoingWebhooks::class)->capture($row);
        }, 5);
    }
}
