<?php

namespace Tests\Feature;

use App\Jobs\PreparePleskCustomer;
use App\Jobs\RunOperation;
use App\Models\Connector;
use App\Models\Operation;
use App\Models\PleskCustomerRequest;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Provisioning\HostingConfig;
use App\Services\Billing;
use App\Services\Checkout;
use App\Services\PleskCustomers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PleskCustomersTest extends TestCase
{
    use RefreshDatabase;

    private array $calls = [];

    private bool $exists = false;

    private bool $webspace = false;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Queue::fake();
        config(['lagos.native_provisioning' => true]);
        Http::preventStrayRequests();
    }

    private function service(bool $paid = true): Service
    {
        $u = User::factory()->create();
        $c = Connector::create(['name' => 'Plesk', 'driver' => 'plesk', 'endpoint' => 'https://plesk-fixture.invalid:8443', 'token' => 'private-plesk-test-key', 'active' => true, 'settings' => ['prefix' => 'lg']]);
        $p = Product::create(['name' => 'Plesk', 'slug' => 'plesk-'.Str::uuid(), 'price_minor' => 100, 'setup_minor' => 0, 'cycle' => 'monthly', 'active' => true, 'connector_id' => $c->id, 'provisioning' => HostingConfig::product('plesk', ['hosting_config' => json_encode(['auto_customer' => true, 'domain_suffix' => 'clients.example.test', 'ip' => '203.0.113.10', 'plan_guid' => '01234567-89ab-4cde-8123-456789abcdef'])])]);
        $i = app(Checkout::class)->create($u, $p->id, 1, (string) Str::uuid());
        if ($paid) {
            app(Billing::class)->settle($i->id, 'test', 'customer-'.$i->id, 100, 'BRL');
        }

return $i->services->sole();
    }

    private function fake(Service $s, array $changes = [], ?callable $hook = null): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($s, $changes, $hook) {
            $this->assertSame(1, DB::transactionLevel(), 'Remote I/O must be outside a transaction');
            $this->assertSame($s->connector->endpoint.'/enterprise/control/agent.php', $request->url());
            $xml = new \DOMDocument;
            $this->assertTrue($xml->loadXML($request->body()));
            $xp = new \DOMXPath($xml);
            $op = $xml->documentElement->firstElementChild->nodeName;
            $verb = $xml->documentElement->firstElementChild->firstElementChild->nodeName;
            $action = $op.'.'.$verb;
            $this->calls[] = $action;
            $r = PleskCustomerRequest::first();
            $p = $s->fresh()->provisioning;
            if ($op === 'customer') {
                if ($verb === 'add') {
                    $this->assertFalse($this->exists);
                    $this->assertNotNull($r->sent_at);
                    $this->assertSame($r->login, $xp->evaluate('string(/packet/customer/add/gen_info/login)'));
                    $this->assertSame($r->external_id, $xp->evaluate('string(/packet/customer/add/gen_info/external-id)'));
                    $this->assertSame($r->secret, $xp->evaluate('string(/packet/customer/add/gen_info/passwd)'));
                    $this->assertSame(14, strlen($r->secret));
                    $this->exists = true;
                    $result = '<status>ok</status><id>29</id>';
                } elseif (! $this->exists) {
                    $result = '<status>error</status><errcode>1013</errcode>';
                } else {
                    $result = '<status>ok</status><id>'.($changes['id'] ?? 29).'</id><data><gen_info>';
                    foreach (array_replace(['login' => $r->login, 'email' => $r->email, 'external-id' => $r->external_id, 'status' => '0'], $changes['customer'] ?? []) as $k => $v) {
                        $result .= '<'.$k.'>'.htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</'.$k.'>';
                    }$result .= '</gen_info></data>';
                }
            } elseif ($op === 'server') {
                $this->assertSame('create_session', $verb);
                $this->assertSame($r->login, $xp->evaluate('string(/packet/server/create_session/login)'));
                $this->assertSame('127.0.0.1', base64_decode($xp->evaluate('string(/packet/server/create_session/data/user_ip)')));
                $result = '<status>ok</status><id>'.($changes['token'] ?? str_repeat('a', 32)).'</id>';
            } else {
                if ($verb === 'add') {
                    $this->assertSame('29', $xp->evaluate('string(/packet/webspace/add/gen_setup/owner-id)'));
                    $this->webspace = true;
                    $result = '<status>ok</status><id>71</id>';
                } elseif (! $this->webspace) {
                    $result = '<status>error</status><errcode>1013</errcode>';
                } else {
                    $result = '<status>ok</status><id>71</id><data><gen_info><name>'.$p['domain'].'</name><owner-id>'.$p['owner_id'].'</owner-id><external-id>'.$p['external_id'].'</external-id><dns_ip_address>'.$p['ip'].'</dns_ip_address><htype>vrt_hst</htype><status>'.($changes['web_status'] ?? '0').'</status></gen_info><hosting><vrt_hst><property><name>ftp_login</name><value>'.$p['username'].'</value></property></vrt_hst></hosting><subscriptions><subscription><plan><plan-guid>'.$p['plan_guid'].'</plan-guid></plan></subscription></subscriptions></data>';
                }
            }
            if ($hook) {
                $override = $hook($action);
                if ($override !== null) {
                    return $override;
                }
            }

            return Http::response('<packet><'.$op.'><'.$verb.'><result>'.$result.'</result></'.$verb.'></'.$op.'></packet>', 200, ['Content-Type' => 'text/xml']);
        });
    }

    private function prepare(Service $s): Operation
    {
        $o = $s->operations()->latest('id')->firstOrFail();
        (new RunOperation($o->id))->handle();
        $o->refresh();
        (new PreparePleskCustomer($o->id, $o->execution_token))->handle();

        return $o->fresh();
    }

    private function active(): Service
    {
        $s = $this->service();
        $this->fake($s);
        $o = $this->prepare($s);
        $this->assertSame('pending', $o->status);
        (new RunOperation($o->id))->handle();
        $this->assertSame('done', $o->fresh()->status);

        return $s->fresh();
    }

    public function test_unpaid_checkout_creates_no_customer_or_remote_requests(): void
    {
        $s = $this->service(false);
        $this->assertNull($s->provisioning['owner_id']);
        $this->assertSame(0, PleskCustomerRequest::count());
        $this->assertSame(0, $s->operations()->count());
        Http::assertNothingSent();
    }

    public function test_paid_preparation_binds_verified_customer_before_subscription(): void
    {
        $s = $this->active();
        $r = PleskCustomerRequest::sole();
        $this->assertSame('done', $r->status);
        $this->assertNull($r->secret);
        $this->assertSame(29, $s->provisioning['owner_id']);
        $this->assertSame($r->id, $s->provisioning['customer_request_id']);
        $this->assertSame(1, count(array_filter($this->calls, fn ($a) => $a === 'customer.add')));
        $this->assertSame(1, count(array_filter($this->calls, fn ($a) => $a === 'webspace.add')));
        $this->assertArrayNotHasKey('secret', $r->toArray());
    }

    public function test_timeout_recovers_by_readback_never_resends(): void
    {
        $s = $this->service();
        $this->fake($s, [], fn ($a) => $a === 'customer.add' ? Http::failedConnection() : null);
        $o = $this->prepare($s);
        $this->assertSame('review', $o->status);
        $r = PleskCustomerRequest::sole();
        $this->assertNotNull($r->sent_at);
        $this->assertStringNotContainsString($r->secret, DB::table('plesk_customer_requests')->value('secret'));
        $this->assertNotContains('webspace.add', $this->calls);
        $this->fake($s);
        $r = app(PleskCustomers::class)->prepare($s->connector, $s->user);
        $this->assertSame('done', $r->status);
        $this->assertSame(1, count(array_filter($this->calls, fn ($a) => $a === 'customer.add')));
    }

    public function test_absence_after_uncertain_send_does_not_retry_creation(): void
    {
        $s = $this->service();
        $this->fake($s, [], fn ($a) => $a === 'customer.add' ? Http::failedConnection() : null);
        $this->prepare($s);
        $this->exists = false;
        $this->fake($s);
        $r = app(PleskCustomers::class)->prepare($s->connector, $s->user);
        $this->assertSame('review', $r->status);
        $this->assertSame(1, count(array_filter($this->calls, fn ($a) => $a === 'customer.add')));
    }

    public function test_existing_login_is_not_adopted(): void
    {
        $s = $this->service();
        $this->exists = true;
        $this->fake($s);
        $o = $this->prepare($s);
        $this->assertSame('review', $o->status);
        $this->assertNull(PleskCustomerRequest::sole()->sent_at);
        $this->assertSame(['customer.get'], $this->calls);
    }

    public static function drift(): array
    {
        return [[['login' => 'admin']], [['email' => 'other@example.test']], [['external-id' => 'foreign']], [['status' => '16']]];
    }

    #[DataProvider('drift')]
    public function test_customer_identity_drift_blocks_binding(array $fields): void
    {
        $s = $this->service();
        $this->fake($s, ['customer' => $fields]);
        $o = $this->prepare($s);
        $this->assertSame('review', $o->status);
        $this->assertNull($s->fresh()->provisioning['owner_id']);
        $this->assertNotContains('webspace.add', $this->calls);
    }

    public function test_mutation_id_must_match_readback(): void
    {
        $s = $this->service();
        $this->fake($s, ['id' => 30]);
        $this->assertSame('review', $this->prepare($s)->status);
        $this->assertSame(29, PleskCustomerRequest::sole()->remote_id);
    }

    public function test_disabled_connector_before_send_blocks_creation(): void
    {
        $s = $this->service();
        $this->fake($s, [], function ($a) use ($s) {
            if ($a === 'customer.get') {
                $s->connector->update(['active' => false]);
            }

return null;
        });
        $this->assertSame('review', $this->prepare($s)->status);
        $this->assertSame(['customer.get'], $this->calls);
    }

    public function test_stale_customer_execution_cannot_send(): void
    {
        $s = $this->service();
        $this->fake($s, [], function ($a) {
            if ($a === 'customer.get') {
                PleskCustomerRequest::query()->update(['execution_token' => (string) Str::uuid()]);
            }

return null;
        });
        $this->assertSame('review', $this->prepare($s)->status);
        $this->assertSame(['customer.get'], $this->calls);
        $this->assertSame('processing', PleskCustomerRequest::sole()->status);
    }

    public function test_customer_is_reused_only_after_identity_recheck(): void
    {
        $s = $this->active();
        $this->fake($s);
        $r = app(PleskCustomers::class)->prepare($s->connector, $s->user);
        $this->assertSame('done', $r->status);
        $this->assertSame('customer.get', end($this->calls));
        $this->assertSame(1, PleskCustomerRequest::count());
    }

    public function test_sso_is_owner_only_private_and_reauthenticated(): void
    {
        $s = $this->active();
        $url = '/painel/servicos/'.$s->id.'/acesso-plesk';
        $this->actingAs($s->user)->get('/painel/servicos')->assertOk()->assertSee('Entrar no Plesk');
        $this->post($url, ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertNotContains('server.create_session', $this->calls);
        $this->post($url, ['password' => 'password'])->assertStatus(303)->assertRedirect($s->connector->endpoint.'/enterprise/rsession_init.php?PLESKSESSID='.str_repeat('a', 32))->assertHeader('Referrer-Policy', 'no-referrer');
        $this->actingAs(User::factory()->create())->post($url, ['password' => 'password'])->assertNotFound();
    }

    public function test_shared_owner_can_never_receive_sso(): void
    {
        $s = $this->active();
        $p = $s->provisioning;
        $p['auto_customer'] = false;
        $s->update(['provisioning' => $p]);
        $this->actingAs($s->user)->get('/painel/servicos')->assertDontSee('Entrar no Plesk');
        $this->post('/painel/servicos/'.$s->id.'/acesso-plesk', ['password' => 'password'])->assertSessionHasErrors('service');
        $this->assertNotContains('server.create_session', $this->calls);
    }

    public function test_pending_operation_blocks_sso(): void
    {
        $s = $this->active();
        $s->operations()->first()->update(['status' => 'review']);
        $this->actingAs($s->user)->post('/painel/servicos/'.$s->id.'/acesso-plesk', ['password' => 'password'])->assertSessionHasErrors('service');
        $this->assertNotContains('server.create_session', $this->calls);
    }

    public function test_foreign_mapping_blocks_sso(): void
    {
        $s = $this->active();
        PleskCustomerRequest::sole()->update(['user_id' => User::factory()->create()->id]);
        $this->actingAs($s->user)->post('/painel/servicos/'.$s->id.'/acesso-plesk', ['password' => 'password'])->assertSessionHasErrors('service');
        $this->assertNotContains('server.create_session', $this->calls);
    }

    public function test_customer_drift_blocks_sso(): void
    {
        $s = $this->active();
        $this->fake($s, ['customer' => ['external-id' => 'foreign']]);
        $this->actingAs($s->user)->post('/painel/servicos/'.$s->id.'/acesso-plesk', ['password' => 'password'])->assertSessionHasErrors('service');
        $this->assertNotContains('server.create_session', $this->calls);
    }

    public function test_pause_after_session_creation_prevents_token_delivery(): void
    {
        $s = $this->active();
        $this->fake($s, [], function ($a) use ($s) {
            if ($a === 'server.create_session') {
                $s->connector->update(['active' => false]);
            }

return null;
        });
        $this->actingAs($s->user)->post('/painel/servicos/'.$s->id.'/acesso-plesk', ['password' => 'password'])->assertSessionHasErrors('service')->assertDontSee(str_repeat('a', 32));
    }

    public function test_invalid_session_token_is_not_delivered(): void
    {
        $s = $this->active();
        $this->fake($s, ['token' => 'https://evil.invalid']);
        $this->actingAs($s->user)->post('/painel/servicos/'.$s->id.'/acesso-plesk', ['password' => 'password'])->assertSessionHasErrors('service');
    }

    public function test_auto_mode_and_shared_owner_are_mutually_exclusive(): void
    {
        $this->expectException(ValidationException::class);
        HostingConfig::product('plesk', ['hosting_config' => json_encode(['auto_customer' => true, 'owner_id' => 1, 'domain_suffix' => 'example.test', 'ip' => '203.0.113.10', 'plan_guid' => '01234567-89ab-4cde-8123-456789abcdef'])]);
    }

    public function test_duplicate_preparation_jobs_do_not_create_twice(): void
    {
        $s = $this->service();
        $this->fake($s);
        $o = $s->operations()->first();
        (new RunOperation($o->id))->handle();
        $job = new PreparePleskCustomer($o->id, $o->fresh()->execution_token);
        $job->handle();
        $job->handle();
        $this->assertSame('pending', $o->fresh()->status);
        $this->assertSame(1, count(array_filter($this->calls, fn ($a) => $a === 'customer.add')));
    }

    public function test_synthetic_unpaid_operation_cannot_prepare_customer(): void
    {
        $s = $this->service(false);
        $o = Operation::create(['service_id' => $s->id, 'action' => 'create', 'reference' => 'unpaid', 'status' => 'processing', 'execution_token' => (string) Str::uuid()]);
        (new PreparePleskCustomer($o->id, $o->execution_token))->handle();
        $this->assertSame('review', $o->fresh()->status);
        $this->assertSame(0, PleskCustomerRequest::count());
        Http::assertNothingSent();
    }

    public function test_admin_can_resume_uncertain_customer_and_view_status_without_secrets(): void
    {
        $s = $this->service();
        $this->fake($s, [], fn ($a) => $a === 'customer.add' ? Http::failedConnection() : null);
        $o = $this->prepare($s);
        $r = PleskCustomerRequest::sole();
        $u = User::factory()->create(['is_admin' => true]);
        $this->actingAs($u)->get('/admin/integracoes/'.$s->connector_id.'/clientes-plesk')->assertOk()->assertSee($r->login)->assertDontSee($r->secret)->assertDontSee($s->connector->token);
        $this->post('/admin/operacoes/'.$o->id.'/preparar-conta', ['note' => 'Conferido no provedor remoto', 'ack' => 1])->assertSessionHasNoErrors();
        $this->fake($s);
        $this->assertSame('pending', $this->prepare($s)->status);
        $this->assertSame(1, count(array_filter($this->calls, fn ($a) => $a === 'customer.add')));
        $this->actingAs($s->user)->get('/admin/integracoes/'.$s->connector_id.'/clientes-plesk')->assertForbidden();
    }

    public function test_fresh_customer_lease_blocks_parallel_preparation(): void
    {
        $s = $this->service();
        $this->fake($s, [], function ($a) use ($s) {
            if ($a === 'customer.add') {
                try {
                    app(PleskCustomers::class)->prepare($s->connector,$s->user);
                    $this->fail('Concurrent preparation accepted');
                } catch (HttpException $e) {
                    $this->assertSame(409,$e->getStatusCode());
                }
            }

return null;
        });
        $this->assertSame('pending',$this->prepare($s)->status);
        $this->assertSame(1,count(array_filter($this->calls,fn ($a) => $a === 'customer.add')));
    }
}
