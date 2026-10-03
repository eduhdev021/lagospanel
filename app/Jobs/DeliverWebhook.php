<?php

namespace App\Jobs;

use App\Models\WebhookAttempt;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\WebhookDestination;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public $tries = 1;

    public $timeout = 30;

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        if (! config('lagos.outgoing_webhooks')) {
            return;
        }
        $d = DB::transaction(function () {
            $d = WebhookDelivery::whereKey($this->deliveryId)->lockForUpdate()->first();
            if (! $d || ! in_array($d->status, ['pending', 'retry', 'processing'], true)) {
                return null;
            }
            if ($d->status === 'processing' && $d->started_at?->gt(now()->subMinutes(2))) {
                return null;
            }
            if ($d->status !== 'processing' && $d->next_attempt_at?->isFuture()) {
                return null;
            }
            if ($d->status === 'processing') {
                WebhookAttempt::where('execution_token', $d->execution_token)->where('outcome', 'processing')->update(['outcome' => 'interrupted', 'finished_at' => now()]);
            }
            $e = WebhookEndpoint::whereKey($d->webhook_endpoint_id)->lockForUpdate()->firstOrFail();
            if (! $e->active) {
                $d->update(['status' => 'cancelled', 'execution_token' => null]);

                return null;
            }
            if ($d->attempts >= $d->max_attempts) {
                $d->update(['status' => 'failed', 'execution_token' => null]);

                return null;
            }
            $d->update(['status' => 'processing', 'attempts' => $d->attempts + 1, 'execution_token' => (string) Str::uuid(), 'started_at' => now()]);
            WebhookAttempt::create(['webhook_delivery_id' => $d->id, 'execution_token' => $d->execution_token, 'number' => $d->attempts, 'started_at' => now()]);

            return $d;
        }, 5);
        if (! $d) {
            return;
        }
        $status = null;
        $outcome = 'blocked_destination';
        $retry = false;
        try {
            $e = WebhookEndpoint::findOrFail($d->webhook_endpoint_id);
            $target = $e->url;
            [$host,$ip] = app(WebhookDestination::class)->pin($target);
            $current = $d->fresh();
            $e = $e->fresh();
            if (! config('lagos.outgoing_webhooks') || ! $e->active || $e->url !== $target || $current->status !== 'processing' || $current->execution_token !== $d->execution_token) {
                $this->finish($d, 'cancelled', 'cancelled', null);

                return;
            }
            $body = $d->payload;
            $timestamp = (string) now()->timestamp;
            $signature = hash_hmac('sha256', $timestamp.'.'.$body, $e->secret);
            $outcome = 'network';
            $retry = true;
            $r = Http::withoutRedirecting()->connectTimeout(3)->timeout(10)->withOptions(['verify' => true, 'proxy' => '', 'curl' => [CURLOPT_RESOLVE => [$host.':443:'.$ip], CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4], 'on_headers' => function ($r) {
                if ((int) $r->getHeaderLine('Content-Length') > 65536) {
                    throw new \RuntimeException('Response limit');
                }
            }, 'progress' => function ($total, $received) {
                if ($received > 65536) {
                    throw new \RuntimeException('Response limit');
                }
            }])->withHeaders(['X-Lagos-Event-Id' => $d->event_id, 'X-Lagos-Event' => $d->event_type, 'X-Lagos-Signature' => 't='.$timestamp.',v1='.$signature, 'User-Agent' => 'LagosPanel-Webhooks/1'])->withBody($body, 'application/json')->post($e->url);
            $status = $r->status();
            $outcome = 'http';
            if (strlen($r->body()) > 65536) {
                throw new \RuntimeException('Response limit');
            }
            if ($r->successful()) {
                $this->finish($d, 'delivered', 'delivered', $status);

                return;
            }
            $retry = in_array($status, [408, 429], true) || $status >= 500;
        } catch (\Throwable) {/* Never persist exceptions, signing keys, request bodies or remote response bodies. */
        }
        $this->finish($d, $retry && $d->attempts < $d->max_attempts ? 'retry' : 'failed', $outcome, $status);
    }

    private function finish(WebhookDelivery $d, string $state, string $outcome, ?int $status): void
    {
        DB::transaction(function () use ($d, $state, $outcome, $status) {
            $current = WebhookDelivery::whereKey($d->id)->lockForUpdate()->firstOrFail();
            if ($current->status !== 'processing' || $current->execution_token !== $d->execution_token) {
                return;
            }
            $delays = [60, 300, 1800, 7200];
            $delay = $delays[min(max(0, $d->attempts - $d->retry_base - 1), 3)];
            $current->update(['status' => $state, 'execution_token' => null, 'next_attempt_at' => $state === 'retry' ? now()->addSeconds($delay) : null, 'delivered_at' => $state === 'delivered' ? now() : null]);
            WebhookAttempt::where('execution_token', $d->execution_token)->update(['outcome' => $outcome, 'http_status' => $status, 'finished_at' => now()]);
        }, 5);
    }
}
