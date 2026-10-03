<?php

namespace Tests\Feature;

use App\Jobs\RunOperation;
use App\Models\Connector;
use App\Models\Invoice;
use App\Models\Operation;
use App\Models\Product;
use App\Models\Service;
use App\Models\StaffRole;
use App\Models\User;
use App\Provisioning\CpanelDriver;
use App\Services\Billing;
use App\Services\Checkout;
use App\Services\Provisioning;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NativeProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Http::preventStrayRequests();
        config(['lagos.native_provisioning' => true]);
    }

    private function user(bool $root = false): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => $root])->save();

        return $u;
    }

    private function invoice(bool $paid = true, ?User $user = null): Invoice
    {
        $c = Connector::create(['name' => 'WHM', 'driver' => 'cpanel', 'endpoint' => 'https://whm.example.test:2087', 'token' => 'native-secret-123456', 'active' => true, 'settings' => ['username' => 'root', 'prefix' => 'lp', 'client_url' => 'https://whm.example.test:2083']]);
        $p = Product::create(['name' => 'Hosting', 'slug' => 'hosting-'.Str::uuid(), 'price_minor' => 100, 'setup_minor' => 0, 'cycle' => 'monthly', 'active' => true, 'connector_id' => $c->id, 'provisioning' => ['driver' => 'cpanel', 'plan' => 'basic', 'domain_suffix' => 'clients.example.test']]);
        $i = app(Checkout::class)->create($user ?? $this->user(), $p->id, 1, (string) Str::uuid());
        if ($paid) {
            app(Billing::class)->settle($i->id, 'test', 'native:'.$i->id, 100, 'BRL');
        }

        return $i;
    }

    private function reply(string $command, array $data = []): array
    {
        return ['metadata' => ['command' => $command, 'result' => 1, 'version' => 1], 'data' => $data];
    }

    private function account(Service $s, int $suspended = 0): array
    {
        $p = $s->provisioning;

        return ['user' => $p['username'], 'domain' => $p['domain'], 'owner' => $p['whm_user'], 'plan' => $p['plan'], 'email' => $p['email'], 'suspended' => $suspended];
    }

    private function fakeCreate(Service $s): void
    {
        Http::fakeSequence()->push($this->reply('listaccts', ['acct' => []]))->push($this->reply('createacct') + ['ignored_secret' => 'do-not-persist'])->push($this->reply('listaccts', ['acct' => [$this->account($s)]]));
    }

    private function execute(Operation $op): void
    {
        (new RunOperation($op->id))->handle();
    }

    private function active(): Service
    {
        $s = $this->invoice()->services->first();
        $this->fakeCreate($s);
        $this->execute($s->operations()->sole());

        Http::swap(new Factory);
        Http::preventStrayRequests();

        return $s->fresh();
    }

    private function reconcile(Operation $op, string $decision = 'inspect', array $extra = []): TestResponse
    {
        return $this->post('/admin/operacoes/'.$op->id.'/conciliar', array_merge(['decision' => $decision, 'ack' => 1, 'note' => 'WHM conferido no ambiente isolado.'], $extra));
    }

    public function test_native_creation_checks_contract_and_keeps_credentials_out_of_url_and_logs(): void
    {
        $s = $this->invoice()->services->first();
        $op = $s->operations()->sole();
        $this->fakeCreate($s);
        $job = new RunOperation($op->id);
        $job->handle();
        $job->handle();
        $this->assertSame('done', $op->fresh()->status);
        $this->assertSame('active', $s->fresh()->status);
        $this->assertSame($s->native_username, $s->fresh()->remote_id);
        Http::assertSentCount(3);
        $password = $s->fresh()->provisioning_secret;
        $this->assertGreaterThanOrEqual(40, strlen($password));
        $this->assertNotSame($password, DB::table('services')->value('provisioning_secret'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/createacct') && $r->method() === 'POST' && $r['plan'] === 'basic' && $r['forcedns'] === 0 && $r['password'] === $password && $r->hasHeader('Authorization', 'whm root:native-secret-123456') && ! str_contains($r->url(), $password));
        $this->assertArrayNotHasKey('provisioning_secret', $s->fresh()->toArray());
        $this->assertArrayNotHasKey('provisioning', $s->fresh()->toArray());
        $this->assertStringNotContainsString($password, DB::table('jobs')->value('payload'));
        $this->assertNull($op->fresh()->error);
    }

    public function test_snapshot_is_not_rewritten_when_product_package_changes(): void
    {
        $s = $this->invoice()->services->first();
        $s->product->update(['provisioning' => ['driver' => 'cpanel', 'plan' => 'expensive', 'domain_suffix' => 'new.example.test']]);
        $this->fakeCreate($s);
        $this->execute($s->operations()->sole());
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/createacct') && $r['plan'] === 'basic' && str_ends_with($r['domain'], '.clients.example.test'));
    }

    public function test_existing_account_is_never_adopted_automatically(): void
    {
        $s = $this->invoice()->services->first();
        Http::fake(['*' => Http::response($this->reply('listaccts', ['acct' => [$this->account($s)]]))]);
        $op = $s->operations()->sole();
        $this->execute($op);
        $this->assertSame('review', $op->fresh()->status);
        $this->assertNull($op->fresh()->sent_at);
        $this->assertNull($s->fresh()->remote_id);
        Http::assertSentCount(1);
        $this->actingAs($this->user(true));
        $this->reconcile($op, 'confirm')->assertSessionHasErrors('operation');
        $this->assertNull($s->fresh()->remote_id);
    }

    public function test_identity_mismatch_blocks_mutation(): void
    {
        $s = $this->active();
        $a = $this->account($s);
        $a['domain'] = 'someone-else.example.test';
        Http::fake(['*' => Http::response($this->reply('listaccts', ['acct' => [$a]]))]);
        $op = app(Provisioning::class)->enqueue($s, 'terminate', 'mismatch');
        $this->execute($op);
        $this->assertSame('review', $op->fresh()->status);
        $this->assertSame('active', $s->fresh()->status);
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/removeacct'));
    }

    public function test_http_200_with_failed_metadata_is_not_success(): void
    {
        $s = $this->invoice()->services->first();
        Http::fakeSequence()->push($this->reply('listaccts', ['acct' => []]))->push(['metadata' => ['result' => 0, 'reason' => 'SECRET-PROVIDER-OUTPUT', 'command' => 'createacct', 'version' => 1]]);
        $op = $s->operations()->sole();
        $this->execute($op);
        $this->assertSame('review', $op->fresh()->status);
        $this->assertSame('pending', $s->fresh()->status);
        $this->assertStringNotContainsString('SECRET-PROVIDER-OUTPUT', $op->fresh()->error);
    }

    public function test_malformed_lookup_is_not_treated_as_absence(): void
    {
        $s = $this->invoice()->services->first();
        Http::fake(['*' => Http::response($this->reply('listaccts', []))]);
        $this->execute($s->operations()->sole());
        $this->assertSame('review', $s->operations()->sole()->status);
        Http::assertSentCount(1);
    }

    public function test_global_switch_blocks_all_native_requests(): void
    {
        $s = $this->invoice()->services->first();
        config(['lagos.native_provisioning' => false]);
        Http::fake();
        $this->execute($s->operations()->sole());
        Http::assertNothingSent();
        $this->assertSame('review', $s->operations()->sole()->status);
    }

    public function test_inactive_connector_blocks_native_requests(): void
    {
        $s = $this->invoice()->services->first();
        $s->connector->update(['active' => false]);
        Http::fake();
        $this->execute($s->operations()->sole());
        Http::assertNothingSent();
    }

    public function test_changed_endpoint_is_not_silently_used_for_existing_service(): void
    {
        $s = $this->invoice()->services->first();
        $s->connector->update(['endpoint' => 'https://different.example.test:2087']);
        Http::fake();
        $this->execute($s->operations()->sole());
        Http::assertNothingSent();
        $this->assertSame('review', $s->operations()->sole()->status);
    }

    public function test_unpaid_account_cannot_be_created_even_by_direct_enqueue(): void
    {
        $s = $this->invoice(false)->services->first();
        $op = app(Provisioning::class)->enqueue($s, 'create', 'unpaid');
        Http::fake();
        $this->execute($op);
        Http::assertNothingSent();
        $this->assertSame('review', $op->fresh()->status);
    }

    public function test_timeout_keeps_durable_secret_and_does_not_retry(): void
    {
        $s = $this->invoice()->services->first();
        Http::fakeSequence()->push($this->reply('listaccts', ['acct' => []]))->pushResponse(Http::failedConnection());
        $op = $s->operations()->sole();
        $job = new RunOperation($op->id);
        $job->handle();
        $job->handle();
        $this->assertSame('review', $op->fresh()->status);
        $this->assertNotNull($op->fresh()->sent_at);
        $this->assertNotNull($s->fresh()->provisioning_secret);
        $this->assertNull($s->fresh()->remote_id);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'password='));
    }

    public function test_post_mutation_state_must_match_before_local_activation(): void
    {
        $s = $this->invoice()->services->first();
        Http::fakeSequence()->push($this->reply('listaccts', ['acct' => []]))->push($this->reply('createacct'))->push($this->reply('listaccts', ['acct' => []]));
        $this->execute($s->operations()->sole());
        $this->assertSame('pending', $s->fresh()->status);
        $this->assertSame('review', $s->operations()->sole()->status);
    }

    public function test_suspend_unsuspend_and_terminate_use_correct_whm_fields(): void
    {
        $s = $this->active();
        foreach ([['suspend', 0, 1, 'suspendacct', 'user', 'suspended'], ['unsuspend', 1, 0, 'unsuspendacct', 'user', 'active'], ['terminate', 0, null, 'removeacct', 'username', 'cancelled']] as [$action,$before,$after,$method,$field,$status]) {
            Http::swap(new Factory);
            Http::preventStrayRequests();
            Http::fakeSequence()->push($this->reply('listaccts', ['acct' => [$this->account($s, $before)]]))->push($this->reply($method))->push($this->reply('listaccts', ['acct' => $after === null ? [] : [$this->account($s, $after)]]));
            $op = app(Provisioning::class)->enqueue($s->fresh(), $action, 'action:'.$action);
            $this->execute($op);
            $this->assertSame('done', $op->fresh()->status);
            $this->assertSame($status, $s->fresh()->status);
            Http::assertSent(fn ($r) => str_ends_with($r->url(), '/'.$method) && $r[$field] === $s->native_username);
        }
        $this->assertNull($s->fresh()->provisioning_secret);
    }

    public function test_already_suspended_account_does_not_receive_another_mutation(): void
    {
        $s = $this->active();
        Http::fake(['*' => Http::response($this->reply('listaccts', ['acct' => [$this->account($s, 1)]]))]);
        $op = app(Provisioning::class)->enqueue($s, 'suspend', 'already');
        $this->execute($op);
        $this->assertSame('suspended', $s->fresh()->status);
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/suspendacct'));
    }

    public function test_unknown_remote_username_is_not_used(): void
    {
        $s = $this->active();
        $s->update(['remote_id' => 'otheruser']);
        Http::fake();
        $op = app(Provisioning::class)->enqueue($s, 'terminate', 'wrong-user');
        $this->execute($op);
        Http::assertNothingSent();
        $this->assertSame('review', $op->fresh()->status);
    }

    public function test_reconciliation_inspects_without_changing_local_service(): void
    {
        $s = $this->invoice()->services->first();
        $op = $s->operations()->sole();
        $op->update(['status' => 'review', 'sent_at' => now()]);
        Http::fake(['*' => Http::response($this->reply('listaccts', ['acct' => [$this->account($s)]]))]);
        $this->actingAs($this->user(true));
        $this->reconcile($op)->assertSessionHasNoErrors();
        $this->assertSame('pending', $s->fresh()->status);
        $this->assertSame('active', $op->fresh()->inspection['status']);
        $this->assertSame('review', $op->fresh()->status);
    }

    public function test_manual_confirmation_after_uncertain_creation_requires_authoritative_identity(): void
    {
        $s = $this->invoice()->services->first();
        $op = $s->operations()->sole();
        $op->update(['status' => 'review', 'sent_at' => now()]);
        Http::fake(['*' => Http::response($this->reply('listaccts', ['acct' => [$this->account($s)]]))]);
        $this->actingAs($this->user(true));
        $this->reconcile($op, 'confirm')->assertSessionHasNoErrors();
        $this->assertSame('done', $op->fresh()->status);
        $this->assertSame('active', $s->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_manual_retry_only_queues_and_never_immediately_mutates_remote(): void
    {
        $s = $this->invoice()->services->first();
        $op = $s->operations()->sole();
        $op->update(['status' => 'review']);
        Http::fake(['*' => Http::response($this->reply('listaccts', ['acct' => []]))]);
        $this->actingAs($this->user(true));
        $this->reconcile($op, 'retry')->assertSessionHasNoErrors();
        $this->assertSame('pending', $op->fresh()->status);
        Http::assertSentCount(1);
        $this->assertDatabaseCount('jobs', 2);
    }

    public function test_existing_account_blocks_creation_retry(): void
    {
        $s = $this->invoice()->services->first();
        $op = $s->operations()->sole();
        $op->update(['status' => 'review']);
        Http::fake(['*' => Http::response($this->reply('listaccts', ['acct' => [$this->account($s)]]))]);
        $this->actingAs($this->user(true));
        $this->reconcile($op, 'retry')->assertSessionHasErrors('operation');
        $this->assertSame('review', $op->fresh()->status);
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_reconciliation_requires_both_permissions_and_explicit_acknowledgement(): void
    {
        $s = $this->invoice()->services->first();
        $op = $s->operations()->sole();
        $op->update(['status' => 'review']);
        $u = $this->user();
        $role = StaffRole::create(['name' => 'Operations', 'permissions' => ['operations.manage']]);
        $u->forceFill(['staff_role_id' => $role->id])->save();
        $this->actingAs($u);
        $this->reconcile($op)->assertForbidden();
        $this->actingAs($this->user(true));
        $this->reconcile($op, 'retry', ['ack' => 0])->assertSessionHasErrors('ack');
        Http::assertNothingSent();
    }

    public function test_credential_disclosure_requires_owner_password_and_is_not_flashed(): void
    {
        $s = $this->active();
        $secret = $s->provisioning_secret;
        $url = '/painel/servicos/'.$s->id.'/acesso-inicial';
        $this->actingAs($this->user())->post($url, ['password' => 'password'])->assertNotFound();
        $this->actingAs($s->user)->post($url, ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->post($url, ['password' => 'password'])->assertOk()->assertSee($secret)->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringNotContainsString($secret, json_encode(session()->all()));
        $this->get('/painel/servicos')->assertOk()->assertDontSee($secret);
        $this->assertStringNotContainsString($secret, DB::table('audit_events')->get()->toJson());
    }

    public function test_credentials_require_fresh_totp_and_cannot_be_read_when_suspended(): void
    {
        $s = $this->active();
        $u = $s->user;
        $secret = Totp::secret();
        $u->forceFill(['totp_secret' => $secret])->save();
        $url = '/painel/servicos/'.$s->id.'/acesso-inicial';
        $this->actingAs($u);
        $this->post($url, ['password' => 'password'])->assertSessionHasErrors('code');
        $code = Totp::code($secret, intdiv(now()->timestamp, 30));
        $this->post($url, ['password' => 'password', 'code' => $code])->assertOk();
        $this->post($url, ['password' => 'password', 'code' => $code])->assertSessionHasErrors('code');
        $s->update(['status' => 'suspended']);
        $u->forceFill(['totp_secret' => null])->save();
        $this->post($url, ['password' => 'password'])->assertNotFound();
    }

    public function test_connector_creation_validates_origin_and_never_flashes_token(): void
    {
        $this->actingAs($this->user(true));
        $input = ['name' => 'WHM', 'driver' => 'cpanel', 'endpoint' => 'https://whm.example.test:2087', 'token' => 'secret-credential-1234', 'whm_username' => 'root', 'account_prefix' => 'lp', 'client_url' => 'https://whm.example.test:2083'];
        $this->post('/admin/integracoes', $input + ['active' => 1])->assertSessionHasErrors('ack_native');
        $this->assertNull(session()->getOldInput('token'));
        $this->post('/admin/integracoes', array_replace($input, ['endpoint' => 'https://whm.example.test:2087/path']))->assertSessionHasErrors('endpoint');
        $this->post('/admin/integracoes', $input)->assertSessionHasNoErrors();
        $c = Connector::sole();
        $this->assertFalse($c->active);
        $this->assertSame('cpanel', $c->driver);
        $this->get('/admin/integracoes')->assertDontSee($input['token']);
    }

    public function test_rotation_does_not_change_driver_or_endpoint(): void
    {
        $s = $this->invoice()->services->first();
        $c = $s->connector;
        $this->actingAs($this->user(true))->post('/admin/integracoes/'.$c->id, ['active' => 1, 'ack_native' => 1, 'token' => 'replacement-secret-1234', 'endpoint' => 'https://evil.example.test', 'driver' => 'json'])->assertRedirect()->assertSessionHasNoErrors();
        $c->refresh();
        $this->assertSame('cpanel', $c->driver);
        $this->assertSame('https://whm.example.test:2087', $c->endpoint);
        $this->assertSame('replacement-secret-1234', $c->token);
    }

    public function test_remote_termination_needs_explicit_confirmation(): void
    {
        $s = $this->active();
        $this->actingAs($this->user(true))->post('/admin/servicos/'.$s->id, ['status' => 'cancelled', 'note' => 'Encerramento solicitado'])->assertSessionHasErrors('confirm_termination');
        $this->assertSame(1, $s->operations()->count());
    }

    public function test_stale_worker_cannot_overwrite_a_replacement_claim(): void
    {
        $s = $this->invoice()->services->first();
        $op = $s->operations()->sole();
        $n = 0;
        Http::fake(function ($r) use ($s, $op, &$n) {
            $n++;
            if ($n === 1) {
                return Http::response($this->reply('listaccts', ['acct' => []]));
            }if ($n === 2) {
                return Http::response($this->reply('createacct'));
            }$op->update(['status' => 'review', 'execution_token' => 'replacement']);

            return Http::response($this->reply('listaccts', ['acct' => [$this->account($s)]]));
        });
        $job = new RunOperation($op->id);
        $job->handle();
        $job->failed(new \RuntimeException('Old failure'));
        $this->assertSame('review', $op->fresh()->status);
        $this->assertSame('replacement', $op->fresh()->execution_token);
        $this->assertSame('pending', $s->fresh()->status);
    }

    public function test_interrupted_claim_only_moves_to_review_after_five_minutes(): void
    {
        $s = $this->invoice()->services->first();
        $op = $s->operations()->sole();
        $op->update(['status' => 'processing', 'execution_token' => 'old']);
        $this->actingAs($this->user(true));
        $url = '/admin/operacoes/'.$op->id.'/interrompida';
        $data = ['ack' => 1, 'note' => 'Worker finalizado e WHM conferido.'];
        $this->post($url, $data)->assertStatus(409);
        $this->travel(6)->minutes();
        $this->post($url, $data)->assertRedirect();
        $this->assertSame('review', $op->fresh()->status);
        $this->assertNull($op->fresh()->execution_token);
        Http::assertNothingSent();
    }

    public function test_conflicting_operation_is_rejected_instead_of_silently_ignored(): void
    {
        $s = $this->invoice()->services->first();
        $this->expectException(ValidationException::class);
        app(Provisioning::class)->enqueue($s, 'terminate', 'conflict');
    }

    public function test_empty_native_schema_roundtrip_and_downgrade_guard(): void
    {
        $m = require database_path('migrations/2026_10_03_000007_native_provisioning.php');
        $m->down();
        $this->assertFalse(Schema::hasColumn('connectors', 'driver'));
        $m->up();
        $this->assertTrue(Schema::hasColumn('operations', 'execution_token'));
        $this->invoice();
        try {
            $m->down();
            $this->fail('Unsafe rollback');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Rollback bloqueado', $e->getMessage());
        }$this->assertTrue(Schema::hasColumn('services', 'provisioning_secret'));
    }

    public function test_error_state_and_redirect_do_not_send_secrets_to_another_origin(): void
    {
        $s = $this->invoice()->services->first();
        Http::fake(['*' => Http::response('', 302, ['Location' => 'https://evil.example.test'])]);
        $this->execute($s->operations()->sole());
        Http::assertSentCount(1);
        $this->assertSame('review', $s->operations()->sole()->status);
    }

    public function test_pausing_connector_after_probe_prevents_mutation(): void
    {
        $s = $this->invoice()->services->first();
        $calls = 0;
        Http::fake(function ($r) use ($s, &$calls) {
            $calls++;
            $s->connector->update(['active' => false]);

            return Http::response($this->reply('listaccts', ['acct' => []]));
        });
        $this->execute($s->operations()->sole());
        $this->assertSame(1, $calls);
        $this->assertSame('review', $s->operations()->sole()->status);
        $this->assertSame('pending', $s->fresh()->status);
    }

    public function test_whm_requests_enforce_tls_and_disable_redirects(): void
    {
        $s = $this->invoice()->services->first();
        Http::fake(function ($r, $options) {
            $this->assertTrue($options['verify']);
            $this->assertFalse($options['allow_redirects']);
            $this->assertSame(15, $options['timeout']);

            return Http::response($this->reply('listaccts', ['acct' => []]));
        });
        app(CpanelDriver::class)->observe($s);
    }

    public function test_reference_cannot_be_reused_for_another_service(): void
    {
        $s = $this->invoice()->services->first();
        $other = $this->invoice()->services->first();
        $reference = $s->operations()->sole()->reference;
        $this->expectException(ValidationException::class);
        app(Provisioning::class)->enqueue($other, 'create', $reference);
    }

    public function test_unmapped_options_cannot_be_charged_for_a_native_product(): void
    {
        $i = $this->invoice(false);
        $p = $i->services->first()->product;
        $group = $p->options()->create(['name' => 'Memória']);
        $value = $group->values()->create(['label' => '8 GB', 'recurring_minor' => 100]);
        $before = Invoice::count();
        try {
            app(Checkout::class)->create($i->user, $p->id, 1, (string) Str::uuid(), null, [$value->id]);
            $this->fail('Unmapped resources must not be sold');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('product', $e->errors());
        }
        $this->assertSame($before, Invoice::count());
        $this->assertDatabaseCount('services', 1);
        Http::assertNothingSent();
    }
}
