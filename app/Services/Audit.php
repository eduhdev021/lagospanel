<?php

namespace App\Services;

use App\Models\AuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class Audit
{
    public static function record(string $event, string $subject, array $context = [], ?int $actor = null): void
    {
        DB::transaction(function () use ($event, $subject, $context, $actor): void {
            $request = app()->bound('request') ? request() : null;
            $previous = AuditEvent::query()->latest('id')->lockForUpdate()->first();
            $requestId = $request?->headers->get('X-Request-ID') ?: (string) Str::uuid();
            $occurredAt = now()->toIso8601String();
            $payload = [
                'user_id' => $actor,
                'event' => $event,
                'subject' => $subject,
                'context' => $context,
                'request_id' => $requestId,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'occurred_at' => $occurredAt,
                'previous_hash' => $previous?->hash,
            ];
            $payload['hash'] = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $row = AuditEvent::create($payload + ['created_at' => $occurredAt]);
            app(OutgoingWebhooks::class)->capture($row);
        }, 5);
    }
}
