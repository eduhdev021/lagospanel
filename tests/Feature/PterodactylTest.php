<?php

namespace Tests\Feature;

use App\Jobs\PollPterodactyl;
use App\Jobs\RunOperation;
use App\Models\Connector;
use App\Models\Operation;
use App\Models\Product;
use App\Models\PterodactylAccount;
use App\Models\Service;
use App\Models\User;
use App\Provisioning\PterodactylConfig;
use App\Services\Billing;
use App\Services\Checkout;
use App\Services\Provisioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\FakePterodactyl;
use Tests\TestCase;

class PterodactylTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Http::preventStrayRequests();
        config(['lagos.native_provisioning' => true]);
        $this->file = base_path('.cache/ptero-phpunit-'.Str::uuid().'.json');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    public static function plan(): array
    {
        return ['egg' => 1, 'location' => 1, 'docker_image' => 'ghcr.io/pterodactyl/yolks:java_21', 'startup' => 'java -jar server.jar', 'environment' => ['SERVER_JARFILE' => 'server.jar'], 'memory' => 1024, 'disk' => 10240, 'cpu' => 100, 'swap' => 0, 'io' => 500, 'databases' => 0, 'allocations' => 1, 'backups' => 1];
    }

    private function product(User $u, bool $linked = true): Product
    {
        $c = Connector::create(['name' => 'Ptero', 'driver' => 'pterodactyl', 'endpoint' => 'https://ptero-fixture.invalid', 'token' => 'ptero-private-application-key', 'active' => true]);
        if ($linked) {
            PterodactylAccount::create(['connector_id' => $c->id, 'user_id' => $u->id, 'remote_user_id' => 41]);
        }

        return Product::create(['name' => 'Game', 'slug' => 'game-'.Str::uuid(), 'price_minor' => 100, 'setup_minor' => 0, 'cycle' => 'monthly', 'active' => true, 'connector_id' => $c->id, 'provisioning' => PterodactylConfig::product(['ptero_config' => json_encode(self::plan())])]);
    }

    private function service(bool $paid = true): Service
    {
        $u = User::factory()->create();
        $p = $this->product($u);
        $i = app(Checkout::class)->create($u, $p->id, 1, (string) Str::uuid());
        if ($paid) {
            app(Billing::class)->settle($i->id, 'test', 'ptero:'.$i->id, 100, 'BRL');
        }

        return $i->services->first();
    }

    private function execute(Service $s): Operation
    {
        $o = $s->operations()->latest('id')->firstOrFail();
        (new RunOperation($o->id))->handle();

        return $o->fresh();
    }

    private function fake(Service $s, bool $installing = false): void
    {
        FakePterodactyl::install($this->file, 41, $s->user->email, $installing);
    }

    private function root(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => true])->save();

        return $u;
    }

    public function test_full_lifecycle_uses_exact_external_identity_and_nonforce_delete(): void
    {
        $s = $this->service();
        $this->fake($s);
        $this->assertSame('done', $this->execute($s)->status);
        $this->assertSame('71', $s->fresh()->remote_id);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/servers') && $r['deploy']['locations'] === [1] && $r['user'] === 41 && $r['limits']['memory'] === 1024 && $r['skip_scripts'] === false);
        foreach (['suspend' => 'suspended', 'unsuspend' => 'active', 'terminate' => 'cancelled'] as $action => $state) {
            app(Provisioning::class)->enqueue($s->fresh(), $action, Str::uuid());
            $this->assertSame('done', $this->execute($s)->status);
            $this->assertSame($state, $s->fresh()->status);
        }
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/servers/71'));
    }

    public function test_duplicate_delivery_does_not_create_twice(): void
    {
        $s = $this->service();
        $this->fake($s);
        $o = $this->execute($s);
        (new RunOperation($o->id))->handle();
        $calls = json_decode(file_get_contents($this->file), true)['calls'];
        $this->assertSame(1, count(array_filter($calls, fn ($c) => $c === 'POST /api/application/servers')));
    }

    public function test_installation_is_not_prematurely_active_and_poll_only_reads(): void
    {
        Queue::fake();
        $s = $this->service();
        $this->fake($s, true);
        $o = $this->execute($s);
        $this->assertSame('review', $o->status);
        $this->assertSame('pending', $s->fresh()->status);
        Queue::assertPushed(PollPterodactyl::class);
        $state = json_decode(file_get_contents($this->file), true);
        $state['server']['status'] = null;
        file_put_contents($this->file, json_encode($state));
        (new PollPterodactyl($o->id, $o->execution_token))->handle();
        $this->assertSame('active', $s->fresh()->status);
        $this->assertSame('done', $o->fresh()->status);
        $calls = json_decode(file_get_contents($this->file), true)['calls'];
        $this->assertSame(1, count(array_filter($calls, fn ($c) => $c === 'POST /api/application/servers')));
    }

    public function test_stale_poll_cannot_finalize_newer_execution(): void
    {
        $s = $this->service();
        $this->fake($s, true);
        $o = $this->execute($s);
        $o->update(['execution_token' => 'replacement']);
        (new PollPterodactyl($o->id, 'old-token'))->handle();
        $this->assertSame('pending', $s->fresh()->status);
        $this->assertSame('replacement', $o->fresh()->execution_token);
    }

    public function test_checkout_without_explicit_user_mapping_rolls_back(): void
    {
        $u = User::factory()->create();
        $p = $this->product($u, false);
        $this->actingAs($u)->post('/pedidos', ['product_id' => $p->id, 'quantity' => 1, 'request_key' => (string) Str::uuid()])->assertSessionHasErrors();
        $this->assertDatabaseCount('invoices', 0);
        Http::assertNothingSent();
    }

    public function test_invalid_and_unlimited_plan_resources_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        PterodactylConfig::product(['ptero_config' => json_encode(array_replace(self::plan(), ['memory' => 0]))]);
    }

    public function test_owner_email_mismatch_blocks_all_mutations(): void
    {
        $s = $this->service();
        FakePterodactyl::install($this->file, 41, 'other@example.test');
        $this->assertSame('review', $this->execute($s)->status);
        Http::assertSentCount(1);
    }

    public function test_admin_remote_owner_is_rejected(): void
    {
        $s = $this->service();
        Http::fake(['*' => Http::response(['object' => 'user', 'attributes' => ['id' => 41, 'email' => $s->user->email, 'root_admin' => true]])]);
        $this->assertSame('review', $this->execute($s)->status);
        Http::assertSentCount(1);
    }

    public function test_global_pause_blocks_network(): void
    {
        $s = $this->service();
        config(['lagos.native_provisioning' => false]);
        $this->assertSame('review', $this->execute($s)->status);
        Http::assertNothingSent();
    }

    public function test_endpoint_change_blocks_network(): void
    {
        $s = $this->service();
        $s->connector->update(['endpoint' => 'https://other.invalid']);
        $this->assertSame('review', $this->execute($s)->status);
        Http::assertNothingSent();
    }

    public function test_wrong_remote_identity_or_resources_prevents_suspend(): void
    {
        $s = $this->service();
        $this->fake($s);
        $this->execute($s);
        $state = json_decode(file_get_contents($this->file), true);
        $state['server']['limits']['memory'] = 2048;
        file_put_contents($this->file, json_encode($state));
        app(Provisioning::class)->enqueue($s->fresh(), 'suspend', Str::uuid());
        $this->assertSame('review', $this->execute($s)->status);
        $this->assertSame('active', $s->fresh()->status);
    }

    public function test_uncertain_network_failure_is_sanitized_without_auto_retry(): void
    {
        $s = $this->service();
        Http::fake(fn () => throw new \RuntimeException('secret-key-provider-response'));
        $o = $this->execute($s);
        $this->assertSame('review', $o->status);
        $this->assertStringNotContainsString('secret-key', $o->error);
    }

    public function test_delete_requires_explicit_confirmation_and_credentials_are_not_exposed(): void
    {
        $s = $this->service();
        $this->fake($s);
        $this->execute($s);
        $this->actingAs($this->root())->post('/admin/servicos/'.$s->id, ['status' => 'cancelled', 'note' => 'Remover teste'])->assertSessionHasErrors('confirm_termination');
        $this->actingAs($s->user)->get('/painel/servicos')->assertSee('Abrir Pterodactyl')->assertDontSee('ptero-private-application-key');
    }

    public function test_account_mapping_is_unique_and_authorized(): void
    {
        $u = User::factory()->create();
        $p = $this->product($u, false);
        $this->actingAs($this->root());
        $url = '/admin/integracoes/'.$p->connector_id.'/contas';
        $this->post($url, ['user_id' => $u->id, 'remote_user_id' => 41, 'ack' => 1])->assertSessionHasNoErrors();
        $this->post($url, ['user_id' => $u->id, 'remote_user_id' => 42, 'ack' => 1])->assertStatus(409);
        $this->actingAs($u)->get($url)->assertForbidden();
    }

    public function test_manual_reconciliation_can_confirm_installed_server(): void
    {
        $s = $this->service();
        $this->fake($s, true);
        $o = $this->execute($s);
        $state = json_decode(file_get_contents($this->file), true);
        $state['server']['status'] = null;
        file_put_contents($this->file, json_encode($state));
        $this->actingAs($this->root())->post('/admin/operacoes/'.$o->id.'/conciliar', ['decision' => 'confirm', 'note' => 'Servidor instalado conferido', 'ack' => 1])->assertSessionHasNoErrors();
        $this->assertSame('active', $s->fresh()->status);
        $this->assertSame('71', $s->fresh()->remote_id);
    }

    public function test_tls_policy_and_malformed_404_cannot_prove_absence(): void
    {
        $s = $this->service();
        Http::fake(function ($r, $opts) use ($s) {
            $this->assertTrue($opts['verify']);
            $this->assertFalse($opts['allow_redirects']);
            $this->assertTrue($r->hasHeader('Authorization', 'Bearer ptero-private-application-key'));
            if (str_contains($r->url(), '/users/')) {
                return Http::response(['object' => 'user', 'attributes' => ['id' => 41, 'email' => $s->user->email, 'root_admin' => false]]);
            }

            return Http::response('<html>proxy missing</html>', 404);
        });
        $this->assertSame('review', $this->execute($s)->status);
        Http::assertSentCount(2);
    }

    public function test_failed_installation_is_not_activated_or_recreated_by_poll(): void
    {
        $s = $this->service();
        $this->fake($s, true);
        $o = $this->execute($s);
        $state = json_decode(file_get_contents($this->file), true);
        $state['server']['status'] = 'install_failed';
        file_put_contents($this->file, json_encode($state));
        (new PollPterodactyl($o->id, $o->execution_token))->handle();
        $this->assertSame('pending', $s->fresh()->status);
        $this->assertSame('review', $o->fresh()->status);
        $calls = json_decode(file_get_contents($this->file), true)['calls'];
        $this->assertSame(1, count(array_filter($calls, fn ($c) => $c === 'POST /api/application/servers')));
    }

    public function test_creation_response_and_readback_ids_must_match(): void
    {
        $s = $this->service();
        $p = $s->provisioning;
        $owner = ['object' => 'user', 'attributes' => ['id' => 41, 'email' => $s->user->email, 'root_admin' => false]];
        $attrs = ['id' => 71, 'external_id' => $p['external_id'], 'user' => 41, 'egg' => 1, 'limits' => array_intersect_key($p, array_flip(['memory', 'disk', 'cpu', 'swap', 'io'])), 'feature_limits' => array_intersect_key($p, array_flip(['databases', 'allocations', 'backups'])), 'container' => ['image' => $p['docker_image']], 'status' => null, 'suspended' => false];
        Http::fake(['*' => Http::sequence()->push($owner)->push(['errors' => [['code' => 'NotFoundHttpException']]], 404)->push(['object' => 'server', 'attributes' => array_replace($attrs, ['id' => 99])], 201)->push($owner)->push(['object' => 'server', 'attributes' => $attrs])]);
        $this->assertSame('review', $this->execute($s)->status);
        $this->assertSame('pending', $s->fresh()->status);
        $this->assertNull($s->fresh()->remote_id);
    }
}
