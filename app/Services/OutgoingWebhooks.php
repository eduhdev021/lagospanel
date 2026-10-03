<?php

namespace App\Services;

use App\Jobs\DeliverWebhook;
use App\Models\AuditEvent;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Str;

final class OutgoingWebhooks
{
    public const EVENTS = ['invoice.paid' => 'invoice', 'order.created' => 'order', 'service.create' => 'service', 'service.suspend' => 'service', 'service.unsuspend' => 'service', 'service.terminate' => 'service', 'service.cancellation_requested' => 'service', 'ticket.created' => 'ticket', 'ticket.state' => 'ticket', 'quote.accept' => 'quote', 'quote.decline' => 'quote', 'bulletin.created' => 'bulletin', 'bulletin.updated' => 'bulletin'];

    public function capture(AuditEvent $event): void
    {
        $prefix = self::EVENTS[$event->event] ?? null;
        if (! $prefix || ! preg_match('/^'.preg_quote($prefix, '/').':([1-9][0-9]*)$/D', $event->subject ?? '', $m)) {
            return;
        }
        $uuid = (string) Str::uuid();
        $payload = json_encode(['schema_version' => 1, 'id' => $uuid, 'type' => $event->event, 'occurred_at' => now()->toIso8601String(), 'data' => ['resource' => $prefix, 'id' => (int) $m[1]]], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        foreach (WebhookEndpoint::where('active', true)->cursor() as $endpoint) {
            if (! in_array($event->event, $endpoint->events, true)) {
                continue;
            }
            WebhookDelivery::firstOrCreate(['webhook_endpoint_id' => $endpoint->id, 'audit_event_id' => $event->id], ['event_id' => $uuid, 'event_type' => $event->event, 'payload' => $payload, 'status' => 'pending', 'next_attempt_at' => now()]);
        }
    }

    public function dispatchDue(): int
    {
        if (! config('lagos.outgoing_webhooks')) {
            return 0;
        }
        $ids = WebhookDelivery::where(function ($q) {
            $q->where(fn ($q) => $q->whereIn('status', ['pending', 'retry'])->where('next_attempt_at', '<=', now()))->orWhere(fn ($q) => $q->where('status', 'processing')->where('started_at', '<=', now()->subMinutes(2)));
        })->orderBy('id')->limit(200)->pluck('id');
        foreach ($ids as $id) {
            DeliverWebhook::dispatch($id)->onConnection('database');
        }

return $ids->count();
    }
}
