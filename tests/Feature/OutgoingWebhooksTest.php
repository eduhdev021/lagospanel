<?php

namespace Tests\Feature;

use App\Jobs\DeliverWebhook;
use App\Models\AuditEvent;
use App\Models\StaffRole;
use App\Models\User;
use App\Models\WebhookAttempt;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Audit;
use App\Services\OutgoingWebhooks;
use App\Services\WebhookDestination;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OutgoingWebhooksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['lagos.outgoing_webhooks' => true]);
        Http::preventStrayRequests();
        $this->app->instance(WebhookDestination::class, new class extends WebhookDestination
        {
            public function resolve(string $host): array
            {
                return ['93.184.216.34'];
            }
        });
    }

    private function endpoint(array $extra = []): WebhookEndpoint
    {
        return WebhookEndpoint::create(array_replace(['name' => 'Receiver', 'url' => 'https://receiver.example.com/hooks', 'secret' => str_repeat('a', 64), 'events' => ['invoice.paid'], 'active' => true], $extra));
    }

    private function delivery(): WebhookDelivery
    {
        $this->endpoint();
        Audit::record('invoice.paid', 'invoice:7', ['email' => 'private@example.test', 'password' => 'never-forward-this']);

        return WebhookDelivery::sole();
    }

    private function execute(WebhookDelivery $d, int $code = 200): WebhookDelivery
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response('response text not retained', $code));
        (new DeliverWebhook($d->id))->handle();

        return $d->fresh();
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => true])->save();

        return $u;
    }

    public function test_outbox_is_atomic_with_domain_transaction(): void
    {
        $this->endpoint();
        try {
            DB::transaction(function () {
                Audit::record('invoice.paid', 'invoice:7');
                throw new \RuntimeException;
            });
        } catch (\RuntimeException) {
        }$this->assertSame(0, WebhookDelivery::count());
        $this->assertSame(0, AuditEvent::count());
        Http::assertNothingSent();
    }

    public function test_whitelist_and_minimal_encrypted_payload(): void
    {
        $d = $this->delivery();
        $p = json_decode($d->payload, true);
        $this->assertSame(['resource' => 'invoice', 'id' => 7], $p['data']);
        $this->assertStringNotContainsString('never-forward-this', $d->payload);
        $this->assertStringNotContainsString($d->event_id, DB::table('webhook_deliveries')->value('payload'));
        $this->assertArrayNotHasKey('payload', $d->toArray());
        $this->assertArrayNotHasKey('secret', WebhookEndpoint::sole()->toArray());
        Audit::record('auth.login', 'user:1', ['token' => 'hidden']);
        Audit::record('invoice.paid', 'invoice:bad');
        $this->assertSame(1, WebhookDelivery::count());
    }

    public function test_capture_is_idempotent_for_same_audit_record(): void
    {
        $d = $this->delivery();
        app(OutgoingWebhooks::class)->capture(AuditEvent::sole());
        $this->assertSame(1, WebhookDelivery::count());
        $this->assertSame($d->event_id, WebhookDelivery::sole()->event_id);
    }

    public function test_subscription_and_disabled_endpoint_filter(): void
    {
        $this->endpoint(['events' => ['order.created']]);
        $this->endpoint(['active' => false]);
        Audit::record('invoice.paid', 'invoice:7');
        $this->assertSame(0, WebhookDelivery::count());
    }

    public function test_signed_delivery_and_duplicate_job(): void
    {
        $d = $this->delivery();
        Http::fake(function ($r) use ($d) {
            $this->assertSame(1, DB::transactionLevel());
            $this->assertSame($d->payload, $r->body());
            $t = (string) now()->timestamp;
            $expected = 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$r->body(), str_repeat('a', 64));
            $this->assertSame([$expected], $r->header('X-Lagos-Signature'));
            $this->assertSame([$d->event_id], $r->header('X-Lagos-Event-Id'));

            return Http::response('', 204);
        });
        $this->travelTo(now()->startOfSecond());
        (new DeliverWebhook($d->id))->handle();
        (new DeliverWebhook($d->id))->handle();
        Http::assertSentCount(1);
        $this->assertSame('delivered', $d->fresh()->status);
        $this->assertSame(1, $d->fresh()->attempts);
        $this->assertSame('delivered', WebhookAttempt::sole()->outcome);
        $this->assertNotNull($d->fresh()->delivered_at);
    }

    public static function statuses(): array
    {
        return [[301, 'failed'], [400, 'failed'], [401, 'failed'], [403, 'failed'], [404, 'failed'], [408, 'retry'], [429, 'retry'], [500, 'retry'], [503, 'retry']];
    }

    #[DataProvider('statuses')]
    public function test_http_response_policy(int $code, string $state): void
    {
        $d = $this->execute($this->delivery(), $code);
        $this->assertSame($state, $d->status);
        $this->assertSame($code, WebhookAttempt::sole()->http_status);
        $this->assertStringNotContainsString('response text', json_encode(WebhookAttempt::sole()->toArray()));
    }

    public function test_network_failure_is_retryable_without_exception_leak(): void
    {
        $d = $this->delivery();
        Http::fake(fn () => Http::failedConnection('secret unsafe error'));
        (new DeliverWebhook($d->id))->handle();
        $this->assertSame('retry', $d->fresh()->status);
        $this->assertSame('network', WebhookAttempt::sole()->outcome);
        $this->assertStringNotContainsString('secret unsafe error', json_encode(WebhookAttempt::sole()->toArray()));
    }

    public function test_backoff_budget_and_event_identity_are_stable(): void
    {
        $d = $this->delivery();
        $body = $d->payload;
        $id = $d->event_id;
        foreach ([60, 300, 1800, 7200] as $delay) {
            $d = $this->execute($d, 503);
            $this->assertSame('retry', $d->status);
            $this->assertEqualsWithDelta($delay, now()->diffInSeconds($d->next_attempt_at), 1);
            $this->assertSame($body, $d->payload);
            $this->assertSame($id, $d->event_id);
            $this->travel($delay + 1)->seconds();
        }$d = $this->execute($d, 503);
        $this->assertSame('failed', $d->status);
        $this->assertSame(5, $d->attempts);
        $this->assertSame(5, WebhookAttempt::count());
    }

    public function test_future_retry_does_not_send_early(): void
    {
        $d = $this->execute($this->delivery(), 500);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        (new DeliverWebhook($d->id))->handle();
        Http::assertNothingSent();
        $this->assertSame(1, $d->fresh()->attempts);
    }

    public function test_global_pause_preserves_pending_outbox(): void
    {
        $d = $this->delivery();
        config(['lagos.outgoing_webhooks' => false]);
        (new DeliverWebhook($d->id))->handle();
        $this->assertSame('pending', $d->fresh()->status);
        $this->assertSame(0, app(OutgoingWebhooks::class)->dispatchDue());
        Http::assertNothingSent();
    }

    public function test_disabled_destination_cancels_delivery_without_http(): void
    {
        $d = $this->delivery();
        $d->endpoint->update(['active' => false]);
        (new DeliverWebhook($d->id))->handle();
        Http::assertNothingSent();
        $this->assertSame('cancelled', $d->fresh()->status);
    }

    public static function unsafeUrls(): array
    {
        return [['http://receiver.example.com/hook'], ['https://127.0.0.1/hook'], ['https://[::1]/hook'], ['https://localhost/hook'], ['https://host.internal/hook'], ['https://user:password@example.com/hook'], ['https://example.com:8443/hook'], ['https://example.com/?token=secret'], ['https://example.com/#fragment']];
    }

    #[DataProvider('unsafeUrls')]
    public function test_unsafe_destination_url_rejected(string $url): void
    {
        $this->expectException(ValidationException::class);
        WebhookDestination::validate($url);
    }

    public static function unsafeIps(): array
    {
        return [['127.0.0.1'], ['10.0.0.1'], ['172.16.0.1'], ['192.168.0.1'], ['169.254.169.254'], ['100.64.0.1'], ['0.0.0.0'], ['192.0.2.1']];
    }

    #[DataProvider('unsafeIps')]
    public function test_private_or_reserved_dns_blocked(string $ip): void
    {
        $d = $this->delivery();
        $this->app->instance(WebhookDestination::class, new class($ip)extends WebhookDestination
        {
            public function __construct(private string $ip) {}

            public function resolve(string $host): array
            {
                return ['93.184.216.34', $this->ip];
            }
        });
        (new DeliverWebhook($d->id))->handle();
        Http::assertNothingSent();
        $this->assertSame('failed', $d->fresh()->status);
        $this->assertSame('blocked_destination', WebhookAttempt::sole()->outcome);
    }

    public function test_disable_during_dns_resolution_blocks_send(): void
    {
        $d = $this->delivery();
        $this->app->instance(WebhookDestination::class, new class extends WebhookDestination
        {
            public function resolve(string $host): array
            {
                WebhookEndpoint::query()->update(['active' => false]);

                return ['93.184.216.34'];
            }
        });
        (new DeliverWebhook($d->id))->handle();
        Http::assertNothingSent();
        $this->assertSame('cancelled', $d->fresh()->status);
    }

    public function test_stale_lease_cannot_finalize_new_execution(): void
    {
        $d = $this->delivery();
        Http::fake(function () use ($d) {
            $d->update(['execution_token' => '00000000-0000-4000-8000-000000000000']);

            return Http::response('', 200);
        });
        (new DeliverWebhook($d->id))->handle();
        $this->assertSame('processing', $d->fresh()->status);
        $this->assertNull($d->fresh()->delivered_at);
    }

    public function test_active_lease_is_not_reclaimed(): void
    {
        $d = $this->delivery();
        $d->update(['status' => 'processing', 'started_at' => now(), 'execution_token' => '00000000-0000-4000-8000-000000000000']);
        (new DeliverWebhook($d->id))->handle();
        Http::assertNothingSent();
    }

    public function test_interrupted_attempt_is_reclaimed_and_audited(): void
    {
        $d = $this->delivery();
        $token = '00000000-0000-4000-8000-000000000000';
        $d->update(['status' => 'processing', 'started_at' => now()->subMinutes(3), 'execution_token' => $token, 'attempts' => 1]);
        WebhookAttempt::create(['webhook_delivery_id' => $d->id, 'execution_token' => $token, 'number' => 1, 'started_at' => now()->subMinutes(3)]);
        $d = $this->execute($d);
        $this->assertSame('delivered', $d->status);
        $this->assertSame(2, $d->attempts);
        $this->assertSame('interrupted', WebhookAttempt::oldest('id')->first()->outcome);
    }

    public function test_dispatcher_finds_pending_and_stale_work(): void
    {
        Queue::fake();
        $d = $this->delivery();
        $this->assertSame(1, app(OutgoingWebhooks::class)->dispatchDue());
        Queue::assertPushed(DeliverWebhook::class, fn ($job) => $job->deliveryId === $d->id);
        Http::assertNothingSent();
    }

    public function test_admin_creation_shows_secret_once_and_never_flashes_it(): void
    {
        $this->actingAs($this->admin());
        $input = ['name' => 'Receiver', 'url' => 'https://receiver.example.com/hooks', 'events' => ['invoice.paid'], 'ack' => 1, 'password' => 'password'];
        $this->post('/admin/webhooks', $input)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $e = WebhookEndpoint::sole();
        $this->assertSame(64, strlen($e->secret));
        $this->assertStringNotContainsString($e->secret, DB::table('webhook_endpoints')->value('secret'));
        $this->get('/admin/webhooks')->assertOk()->assertDontSee($e->secret);
        $this->assertNull(session()->getOldInput('password'));
    }

    public function test_wrong_password_blocks_configuration(): void
    {
        $this->actingAs($this->admin())->post('/admin/webhooks', ['name' => 'Receiver', 'url' => 'https://receiver.example.com/hooks', 'events' => ['invoice.paid'], 'ack' => 1, 'password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertSame(0, WebhookEndpoint::count());
    }

    public function test_read_only_operator_cannot_change_webhooks(): void
    {
        $u = User::factory()->create();
        $role = StaffRole::create(['name' => 'Reader', 'permissions' => ['integrations.view']]);
        $u->forceFill(['staff_role_id' => $role->id])->save();
        $this->actingAs($u)->get('/admin/webhooks')->assertOk()->assertDontSee('Novo destino');
        $this->post('/admin/webhooks', [])->assertForbidden();
    }

    public function test_clients_cannot_access_webhook_data(): void
    {
        $d = $this->delivery();
        $this->actingAs(User::factory()->create())->get('/admin/webhooks')->assertForbidden();
        $this->get('/admin/webhooks/entregas/'.$d->id)->assertForbidden();
        $this->post('/admin/webhooks', [])->assertForbidden();
    }

    public function test_manual_retry_preserves_event_and_history(): void
    {
        $d = $this->execute($this->delivery(), 400);
        $this->actingAs($this->admin())->get('/admin/webhooks/entregas/'.$d->id)->assertOk();
        $data = ['password' => 'password', 'ack' => 1, 'note' => 'Destinatário corrigido e deduplicação confirmada'];
        $this->post('/admin/webhooks/entregas/'.$d->id.'/repetir', $data)->assertSessionHasNoErrors();
        $this->post('/admin/webhooks/entregas/'.$d->id.'/repetir', $data)->assertConflict();
        $this->assertSame(6, $d->fresh()->max_attempts);
        $this->assertSame($d->event_id, $d->fresh()->event_id);
        $this->assertSame(1, WebhookAttempt::count());
        $this->assertSame('delivered', $this->execute($d)->status);
    }

    public function test_response_limit_cannot_mark_delivery_complete(): void
    {
        $d = $this->delivery();
        Http::fake(fn () => Http::response(str_repeat('x', 65537), 200));
        (new DeliverWebhook($d->id))->handle();
        $this->assertSame('retry', $d->fresh()->status);
    }

    public function test_rollback_preserves_outbox(): void
    {
        $this->delivery();
        $m = require database_path('migrations/2026_10_03_000015_outgoing_webhooks.php');
        $this->expectException(\RuntimeException::class);
        $m->down();
    }

    public function test_http_transport_pins_dns_and_disables_proxy_and_redirects(): void
    {
        $d = $this->delivery();
        $options = null;
        Http::fake(function ($r, $o) use (&$options) {
            $options = $o;

            return Http::response('', 204);
        });
        (new DeliverWebhook($d->id))->handle();
        $this->assertSame('delivered', $d->fresh()->status);
        $this->assertSame(['receiver.example.com:443:93.184.216.34'], $options['curl'][CURLOPT_RESOLVE]);
        $this->assertSame(CURL_IPRESOLVE_V4, $options['curl'][CURLOPT_IPRESOLVE]);
        $this->assertSame('', $options['proxy']);
        $this->assertFalse($options['allow_redirects']);
        $this->assertTrue($options['verify']);
    }

    public function test_destination_url_drift_during_dns_cannot_bypass_pinning(): void
    {
        $d = $this->delivery();
        $this->app->instance(WebhookDestination::class, new class extends WebhookDestination
        {
            public function resolve(string $host): array
            {
                WebhookEndpoint::query()->update(['url' => 'https://other.example.com/hook']);

                return ['93.184.216.34'];
            }
        });
        (new DeliverWebhook($d->id))->handle();
        Http::assertNothingSent();
        $this->assertSame('cancelled', $d->fresh()->status);
    }

    public function test_totp_required_and_replay_rejected(): void
    {
        $u = $this->admin();
        $secret = 'JBSWY3DPEHPK3PXP';
        $u->forceFill(['totp_secret' => $secret])->save();
        $this->actingAs($u);
        $data = ['name' => 'Receiver', 'url' => 'https://receiver.example.com/hooks', 'events' => ['invoice.paid'], 'ack' => 1, 'password' => 'password'];
        $this->post('/admin/webhooks',$data)->assertSessionHasErrors('code');
        $this->assertSame(0,WebhookEndpoint::count());
        $data['code'] = Totp::code($secret,intdiv(now()->timestamp,30));
        $this->post('/admin/webhooks',$data)->assertOk();
        $this->post('/admin/webhooks',$data)->assertSessionHasErrors('code');
        $this->assertSame(1,WebhookEndpoint::count());
    }
}
