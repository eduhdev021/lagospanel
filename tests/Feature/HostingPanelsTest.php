<?php

namespace Tests\Feature;

use App\Jobs\RunOperation;
use App\Models\Connector;
use App\Models\Operation;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Provisioning\HostingConfig;
use App\Services\Billing;
use App\Services\Checkout;
use App\Services\Provisioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\FakeHosting;
use Tests\TestCase;

class HostingPanelsTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Http::preventStrayRequests();
        config(['lagos.native_provisioning' => true]);
        $this->file = base_path('.cache/hosting-'.Str::uuid().'.json');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    public static function drivers(): array
    {
        return [['directadmin'], ['plesk']];
    }

    public static function plan(string $driver): array
    {
        return ['domain_suffix' => 'clients.example.test', 'ip' => '203.0.113.10'] + ($driver === 'directadmin' ? ['plan' => 'basic'] : ['owner_id' => 12, 'plan_guid' => '01234567-89ab-4cde-8123-456789abcdef']);
    }

    private function service(string $driver, bool $paid = true): Service
    {
        $u = User::factory()->create();
        $c = Connector::create(['name' => $driver, 'driver' => $driver, 'endpoint' => 'https://'.$driver.'-fixture.invalid:'.($driver === 'directadmin' ? '2222' : '8443'), 'token' => 'hosting-fixture-private-key', 'active' => true, 'settings' => ['username' => 'reseller', 'prefix' => 'lg']]);
        $p = Product::create(['name' => 'Hosting '.$driver, 'slug' => 'hosting-'.Str::uuid(), 'price_minor' => 100, 'setup_minor' => 0, 'cycle' => 'monthly', 'active' => true, 'connector_id' => $c->id, 'provisioning' => HostingConfig::product($driver, ['hosting_config' => json_encode(self::plan($driver))])]);
        $i = app(Checkout::class)->create($u, $p->id, 1, (string) Str::uuid());
        if ($paid) {
            app(Billing::class)->settle($i->id, 'test', 'hosting-'.$i->id, 100, 'BRL');
        }

        return $i->services->sole();
    }

    private function fake(Service $s, array $options = [], ?callable $hook = null): void
    {
        Http::swap(new Factory);
        FakeHosting::install($this->file, $s, $options, $hook);
    }

    private function execute(Service $s): Operation
    {
        $o = $s->operations()->latest('id')->firstOrFail();
        (new RunOperation($o->id))->handle();

        return $o->fresh();
    }

    private function calls(): array
    {
        return json_decode(file_get_contents($this->file), true)['calls'];
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => true])->save();

        return $u;
    }

    #[DataProvider('drivers')]
    public function test_paid_lifecycle_and_duplicate_jobs(string $driver): void
    {
        $s = $this->service($driver);
        Http::assertNothingSent();
        $this->fake($s);
        $o = $this->execute($s);
        $this->assertSame('done', $o->status, $o->error ?? '');
        (new RunOperation($o->id))->handle();
        $this->assertSame(1, count(array_filter($this->calls(), fn ($v) => $v === 'create')));
        $this->assertSame('active', $s->fresh()->status);
        $this->assertNotEmpty($s->fresh()->provisioning_secret);
        $this->assertStringNotContainsString($s->fresh()->provisioning_secret, json_encode($s->fresh()));
        foreach (['suspend' => 'suspended', 'unsuspend' => 'active', 'terminate' => 'cancelled'] as $action => $state) {
            app(Provisioning::class)->enqueue($s->fresh(), $action, (string) Str::uuid());
            $o = $this->execute($s->fresh());
            $this->assertSame('done', $o->status, $o->error ?? '');
            $this->assertSame($state, $s->fresh()->status);
        }
        $this->assertNull($s->fresh()->provisioning_secret);
    }

    #[DataProvider('drivers')]
    public function test_unpaid_and_disabled_provisioning_never_mutate(string $driver): void
    {
        $s = $this->service($driver, false);
        app(Provisioning::class)->enqueue($s, 'create', 'unpaid');
        $this->assertSame('review', $this->execute($s)->status);
        Http::assertNothingSent();
        Operation::query()->update(['status' => 'pending']);
        config(['lagos.native_provisioning' => false]);
        $this->assertSame('review', $this->execute($s)->status);
        Http::assertNothingSent();
    }

    #[DataProvider('drivers')]
    public function test_uncertain_creation_is_read_back_not_automatically_repeated(string $driver): void
    {
        $s = $this->service($driver);
        $this->fake($s, ['timeout' => true]);
        $o = $this->execute($s);
        $this->assertSame('review', $o->status);
        $this->assertNotNull($o->sent_at);
        $this->assertNull($s->fresh()->remote_id);
        (new RunOperation($o->id))->handle();
        $this->fake($s);
        $this->actingAs($this->admin())->post('/admin/operacoes/'.$o->id.'/conciliar', ['decision' => 'confirm', 'note' => 'Resultado conferido no provedor', 'ack' => 1])->assertSessionHasNoErrors();
        $this->assertSame('done', $o->fresh()->status);
        $this->assertSame('active', $s->fresh()->status);
        $this->assertSame(1, count(array_filter($this->calls(), fn ($v) => $v === 'create')));
    }

    #[DataProvider('drivers')]
    public function test_preexisting_account_is_not_adopted(string $driver): void
    {
        $s = $this->service($driver);
        file_put_contents($this->file, json_encode(['exists' => true, 'suspended' => false, 'calls' => []]));
        $this->fake($s);
        $this->assertSame('review', $this->execute($s)->status);
        $this->assertSame(['get'], $this->calls());
        $this->assertNull($s->fresh()->remote_id);
    }

    public static function drift(): array
    {
        return [['directadmin', ['creator' => 'someoneelse']], ['directadmin', ['userType' => 'admin']], ['directadmin', ['package' => 'other']], ['directadmin', ['email' => 'other@example.test']], ['directadmin', ['suspended' => 'no']], ['plesk', ['owner-id' => '999']], ['plesk', ['external-id' => 'other']], ['plesk', ['name' => 'other.example.test']], ['plesk', ['htype' => 'none']], ['plesk', ['status' => '64']]];
    }

    #[DataProvider('drift')]
    public function test_identity_or_nonmanaged_status_blocks_suspension(string $driver, array $fields): void
    {
        $s = $this->service($driver);
        $this->fake($s);
        $this->assertSame('done', $this->execute($s)->status);
        $s->refresh();
        $this->fake($s, ['fields' => $fields]);
        app(Provisioning::class)->enqueue($s, 'suspend', 'drift');
        $this->assertSame('review', $this->execute($s)->status);
        $this->assertSame(0, count(array_filter($this->calls(), fn ($v) => $v === 'suspend')));
    }

    #[DataProvider('drivers')]
    public function test_pause_and_execution_fence_before_mutation(string $driver): void
    {
        $s = $this->service($driver);
        $this->fake($s, [], fn ($a) => $a === 'get' ? $s->connector->update(['active' => false]) : null);
        $this->assertSame('review', $this->execute($s)->status);
        $this->assertSame(['get'], $this->calls());
    }

    #[DataProvider('drivers')]
    public function test_stale_operation_cannot_mutate(string $driver): void
    {
        $s = $this->service($driver);
        $this->fake($s, [], fn () => Operation::where('service_id', $s->id)->update(['execution_token' => (string) Str::uuid()]));
        $o = $this->execute($s);
        $this->assertSame('processing', $o->status);
        $this->assertSame(['get'], $this->calls());
    }

    public static function invalidResponses(): array
    {
        return [['directadmin', '<html>Not found</html>', 404], ['directadmin', '{"error":0}', 200], ['plesk', '<!DOCTYPE packet [<!ENTITY x SYSTEM "file:///etc/passwd">]><packet>&x;</packet>', 200], ['plesk', '<packet><webspace><get><result><status>ok</status></result><result/></get></webspace></packet>', 200], ['plesk', '<html/>', 302], ['plesk', '<packet><webspace><get><result><status>error</status><errcode>1006</errcode></result></get></webspace></packet>', 200]];
    }

    #[DataProvider('invalidResponses')]
    public function test_malformed_or_unauthorized_response_is_not_absence(string $driver, string $raw, int $status): void
    {
        $s = $this->service($driver);
        $this->fake($s, ['raw' => $raw, 'status' => $status]);
        $this->assertSame('review', $this->execute($s)->status);
        $this->assertSame(['get'], $this->calls());
    }

    public function test_directadmin_initial_access_is_owner_only_and_private(): void
    {
        $s = $this->service('directadmin');
        $this->fake($s);
        $this->execute($s);
        $this->actingAs($s->user)->get('/painel/servicos')->assertOk()->assertDontSee('Entrar no cPanel');
        $url = '/painel/servicos/'.$s->id.'/acesso-inicial';
        $this->post($url, ['password' => 'password'])->assertOk()->assertSee('Abrir DirectAdmin')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->actingAs(User::factory()->create())->post($url, ['password' => 'password'])->assertNotFound();
    }

    public function test_plesk_only_reveals_own_ftp_credentials_after_reauthentication(): void
    {
        $s = $this->service('plesk');
        $this->fake($s);
        $this->execute($s);
        $this->actingAs($s->user)->get('/painel/servicos')->assertOk()->assertSee('Assinatura Plesk gerenciada')->assertSee('Ver acesso de publicação');
        $this->post('/painel/servicos/'.$s->id.'/acesso-inicial', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->post('/painel/servicos/'.$s->id.'/acesso-inicial', ['password' => 'password'])->assertOk()->assertSee('Servidor FTP/FTPS')->assertDontSee('Abrir Plesk')->assertSee($s->fresh()->provisioning_secret);
    }

    #[DataProvider('drivers')]
    public function test_admin_can_configure_connector_and_product_with_no_key_echo(string $driver): void
    {
        $this->actingAs($this->admin());
        $port = $driver === 'directadmin' ? 2222 : 8443;
        $data = ['name' => 'Hosting', 'driver' => $driver, 'endpoint' => 'https://hosting.invalid:'.$port, 'token' => 'private-hosting-key-123456', 'account_prefix' => 'lg', 'whm_username' => 'reseller', 'active' => 1];
        $this->post('/admin/integracoes', $data)->assertSessionHasErrors('ack_native');
        $this->assertNull(session()->getOldInput('token'));
        $this->post('/admin/integracoes', $data + ['ack_native' => 1])->assertSessionHasNoErrors();
        $c = Connector::sole();
        $this->assertSame($driver, $c->driver);
        $this->post('/admin/produtos', ['name' => 'Hosting', 'slug' => 'hosting', 'price' => '10,00', 'cycle' => 'monthly', 'active' => 1, 'connector_id' => $c->id, 'hosting_config' => json_encode(self::plan($driver))])->assertSessionHasNoErrors();
        $this->assertSame($driver, Product::sole()->provisioning['driver']);
        $this->get('/admin/integracoes')->assertOk()->assertDontSee($data['token']);
        $this->get('/admin/produtos')->assertOk()->assertSee('DirectAdmin / Plesk');
    }
}
