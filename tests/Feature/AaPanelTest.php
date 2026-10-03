<?php

namespace Tests\Feature;

use App\Jobs\RunOperation;
use App\Models\Connector;
use App\Models\Operation;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Provisioning\AaPanelDriver;
use App\Provisioning\ProtocolError;
use App\Services\Billing;
use App\Services\Checkout;
use App\Services\Provisioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class AaPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Http::preventStrayRequests();
        config(['lagos.native_provisioning' => true]);
    }

    private function service(bool $paid = true): Service
    {
        $u = User::factory()->create();
        $c = Connector::create(['name' => 'aaPanel', 'driver' => 'aapanel', 'endpoint' => 'https://aapanel-fixture.invalid:7800', 'token' => 'private-aapanel-key', 'active' => true, 'settings' => ['prefix' => 'aa']]);
        $p = Product::create(['name' => 'Managed site', 'slug' => 'aa-'.Str::uuid(), 'price_minor' => 100, 'setup_minor' => 0, 'cycle' => 'monthly', 'active' => true, 'connector_id' => $c->id, 'provisioning' => ['driver' => 'aapanel', 'domain_suffix' => 'sites.example.test', 'php_version' => '82']]);
        $i = app(Checkout::class)->create($u, $p->id, 1, (string) Str::uuid());
        if ($paid) {
            app(Billing::class)->settle($i->id, 'test', 'aa:'.$i->id, 100, 'BRL');
        }

        return $i->services->first();
    }

    private function account(Service $s, array $extra = []): array
    {
        return array_replace(['id' => 41, 'name' => $s->provisioning['domain'], 'path' => $s->provisioning['path'], 'ps' => $s->provisioning['marker'], 'status' => '1'], $extra);
    }

    private function runOp(Service $s): Operation
    {
        $op = $s->operations()->latest('id')->firstOrFail();
        (new RunOperation($op->id))->handle();

        return $op->fresh();
    }

    private function create(Service $s): void
    {
        Http::fakeSequence()->push(['data' => []])->push([['version' => '82']])->push(['siteStatus' => true])->push(['data' => [$this->account($s)]]);
        $this->assertSame('done', $this->runOp($s)->status);
    }

    private function resetHttp(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
    }

    private function root(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => true])->save();

        return $u;
    }

    public function test_create_signed_requests_and_bind_numeric_site_id(): void
    {
        $s = $this->service();
        $this->create($s);
        $this->assertSame('41', $s->fresh()->remote_id);
        $this->assertSame('active', $s->fresh()->status);
        $this->assertNull($s->fresh()->provisioning_secret);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'AddSite') && $r['request_token'] === md5((string) $r['request_time'].md5('private-aapanel-key')) && $r['ftp'] === 'false' && $r['sql'] === 'false' && $r['version'] === '82' && ! str_contains($r->url(), 'request_token'));
        (new RunOperation($s->operations()->sole()->id))->handle();
        Http::assertSentCount(4);
    }

    public function test_lifecycle_preserves_files_and_never_sends_database_or_ftp_delete_flags(): void
    {
        $s = $this->service();
        $this->create($s);
        foreach ([['suspend', '0', 'SiteStop', 'suspended'], ['unsuspend', '1', 'SiteStart', 'active'], ['terminate', null, 'DeleteSite', 'cancelled']] as [$action,$after,$api,$local]) {
            $s->refresh();
            $this->resetHttp();
            Http::fakeSequence()->push(['data' => [$this->account($s, ['status' => $s->status === 'suspended' ? '0' : '1'])]])->push(['status' => true])->push(['data' => $after === null ? [] : [$this->account($s, ['status' => $after])]]);
            app(Provisioning::class)->enqueue($s, $action, 'aa:'.$action);
            $this->assertSame('done', $this->runOp($s)->status);
            $this->assertSame($local, $s->fresh()->status);
            Http::assertSent(fn ($r) => str_contains($r->url(), $api) && (string) $r['id'] === '41' && ! isset($r['path']) && ! isset($r['ftp']) && ! isset($r['database']));
        }
    }

    public function test_existing_matching_site_is_not_adopted(): void
    {
        $s = $this->service();
        Http::fake(['*' => Http::response(['data' => [$this->account($s)]])]);
        $this->assertSame('review', $this->runOp($s)->status);
        Http::assertSentCount(1);
        $this->assertNull($s->fresh()->remote_id);
    }

    public function test_foreign_marker_or_path_blocks_remote_changes(): void
    {
        $s = $this->service();
        $this->create($s);
        $s->refresh();
        foreach ([['ps' => 'foreign'], ['path' => '/etc'], ['id' => 99]] as $bad) {
            $this->resetHttp();
            Http::fake(['*' => Http::response(['data' => [$this->account($s, $bad)]])]);
            try {
                app(AaPanelDriver::class)->observe($s);
                $this->fail('Identity accepted');
            } catch (ProtocolError $e) {
                $this->assertStringContainsString('diverge', $e->getMessage());
            }
        }
    }

    public function test_uninstalled_php_prevents_creation(): void
    {
        $s = $this->service();
        Http::fakeSequence()->push(['data' => []])->push([['version' => '81']]);
        $this->assertSame('review', $this->runOp($s)->status);
        Http::assertSentCount(2);
        $this->assertNull($s->operations()->sole()->sent_at);
    }

    public function test_api_error_is_not_absence_or_success(): void
    {
        $s = $this->service();
        Http::fake(['*' => Http::response(['status' => false, 'msg' => 'SECRET-RESPONSE', 'data' => []])]);
        $o = $this->runOp($s);
        $this->assertSame('review', $o->status);
        $this->assertStringNotContainsString('SECRET-RESPONSE', $o->error);
        Http::assertSentCount(1);
    }

    public function test_malformed_and_saturated_lists_cannot_prove_absence(): void
    {
        $s = $this->service();
        foreach ([[], ['data' => null], ['data' => array_fill(0, 100, $this->account($s))], ['data' => [$this->account($s, ['name' => 'different.example.test'])]]] as $d) {
            $this->resetHttp();
            Http::fake(['*' => Http::response($d)]);
            try {
                app(AaPanelDriver::class)->observe($s);
                $this->fail('Bad list accepted');
            } catch (ProtocolError $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function test_mutation_response_alone_does_not_activate(): void
    {
        $s = $this->service();
        Http::fakeSequence()->push(['data' => []])->push([['version' => '82']])->push(['siteStatus' => true])->push(['data' => []]);
        $this->assertSame('review', $this->runOp($s)->status);
        $this->assertSame('pending', $s->fresh()->status);
    }

    public function test_unrequested_resources_require_review(): void
    {
        $s = $this->service();
        Http::fakeSequence()->push(['data' => []])->push([['version' => '82']])->push(['siteStatus' => true, 'ftpStatus' => true]);
        $this->assertSame('review', $this->runOp($s)->status);
        Http::assertSentCount(3);
    }

    public function test_timeout_does_not_retry_or_expose_api_key(): void
    {
        $s = $this->service();
        $calls = 0;
        Http::fake(function ($r) use (&$calls) {
            $calls++;
            if ($calls === 1) {
                return Http::response(['data' => []]);
            }if ($calls === 2) {
                return Http::response([['version' => '82']]);
            }throw new ConnectionException('private-aapanel-key');
        });
        $o = $this->runOp($s);
        (new RunOperation($o->id))->handle();
        $this->assertSame(3, $calls);
        $this->assertSame('review', $o->status);
        $this->assertNotNull($o->sent_at);
        $this->assertStringNotContainsString('private-aapanel-key', $o->error);
    }

    public function test_confirmation_uses_remote_site_id_not_local_username(): void
    {
        $s = $this->service();
        $o = $s->operations()->sole();
        $o->update(['status' => 'review', 'sent_at' => now()]);
        Http::fake(['*' => Http::response(['data' => [$this->account($s)]])]);
        $this->actingAs($this->root())->post('/admin/operacoes/'.$o->id.'/conciliar', ['decision' => 'confirm', 'ack' => 1, 'note' => 'Conferido no aaPanel de teste.'])->assertSessionHasNoErrors();
        $this->assertSame('41', $s->fresh()->remote_id);
        $this->assertSame('done', $o->fresh()->status);
    }

    public function test_global_switch_and_payment_gate_prevent_outbound_calls(): void
    {
        $s = $this->service(false);
        app(Provisioning::class)->enqueue($s, 'create', 'unpaid');
        Http::fake();
        $this->runOp($s);
        Http::assertNothingSent();
        $s = $this->service();
        config(['lagos.native_provisioning' => false]);
        $this->runOp($s);
        Http::assertNothingSent();
    }

    public function test_pause_after_probe_prevents_mutation(): void
    {
        $s = $this->service();
        Http::fake(function ($r) use ($s) {
            $s->connector->update(['active' => false]);

            return Http::response(['data' => []]);
        });
        $this->assertSame('review', $this->runOp($s)->status);
        Http::assertSentCount(1);
    }

    public function test_aapanel_never_reveals_server_login_as_customer_credential(): void
    {
        $s = $this->service();
        $this->create($s);
        $this->actingAs($s->user)->post('/painel/servicos/'.$s->id.'/acesso-inicial', ['password' => 'password'])->assertNotFound();
        $this->get('/painel/servicos')->assertSee('sem acesso administrativo aaPanel')->assertDontSee('private-aapanel-key');
    }

    public function test_connector_and_product_validation(): void
    {
        $this->actingAs($this->root());
        $data = ['name' => 'aaPanel', 'driver' => 'aapanel', 'endpoint' => 'https://aa.example.test:7800', 'token' => 'private-aapanel-key', 'account_prefix' => 'aa', 'active' => 1];
        $this->post('/admin/integracoes', $data)->assertSessionHasErrors('ack_native');
        $this->post('/admin/integracoes', $data + ['ack_native' => 1])->assertSessionHasNoErrors();
        $this->assertSame('aapanel', Connector::sole()->driver);
        $this->post('/admin/produtos', ['name' => 'Site', 'slug' => 'site', 'price' => '10', 'cycle' => 'monthly', 'connector_id' => Connector::sole()->id, 'aapanel_domain_suffix' => '../../etc', 'aapanel_php_version' => '82'])->assertSessionHasErrors('aapanel_domain_suffix');
    }

    public function test_tls_redirect_policy_and_signature_are_enforced(): void
    {
        $s = $this->service();
        Http::fake(function ($r, $opts) {
            $this->assertTrue($opts['verify']);
            $this->assertFalse($opts['allow_redirects']);
            $this->assertSame(10, $opts['timeout']);
            $this->assertSame(md5((string) $r['request_time'].md5('private-aapanel-key')), $r['request_token']);

            return Http::response(['data' => []]);
        });
        app(AaPanelDriver::class)->observe($s);
    }

    public function test_manual_product_edit_and_nonce_directory_are_safe(): void
    {
        $s = $this->service();
        $this->assertSame('/www/wwwroot/'.$s->provisioning['domain'].'-'.substr($s->provisioning['marker'], 11), $s->provisioning['path']);
        $p = Product::create(['name' => 'Manual', 'slug' => 'manual', 'price_minor' => 100, 'setup_minor' => 0, 'cycle' => 'monthly', 'active' => true]);
        $this->actingAs($this->root())->get('/admin/produtos/'.$p->id.'/editar')->assertOk()->assertSee('aapanel_domain_suffix');
    }
}
