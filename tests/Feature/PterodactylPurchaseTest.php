<?php

namespace Tests\Feature;

use App\Jobs\PreparePterodactylAccount;
use App\Jobs\RunOperation;
use App\Models\Connector;
use App\Models\Operation;
use App\Models\Product;
use App\Models\PterodactylAccount;
use App\Models\PterodactylAccountRequest;
use App\Models\User;
use App\Provisioning\PterodactylConfig;
use App\Services\Billing;
use App\Services\Checkout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\FakePterodactyl;
use Tests\TestCase;

class PterodactylPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    private ?array $remote = null;

    private int $posts = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Queue::fake();
        Http::preventStrayRequests();
        config(['lagos.native_provisioning' => true]);
        $this->user = User::factory()->create(['name' => 'Cliente Exemplo']);
        $c = Connector::create(['name' => 'Games', 'driver' => 'pterodactyl', 'endpoint' => 'https://ptero-fixture.invalid', 'token' => 'application-test-token', 'active' => true]);
        $this->product = Product::create(['name' => 'Games', 'slug' => 'game-auto', 'price_minor' => 100, 'setup_minor' => 0, 'cycle' => 'monthly', 'active' => true, 'allow_quantity' => true, 'connector_id' => $c->id, 'provisioning' => PterodactylConfig::product(['ptero_config' => json_encode(['auto_account' => true] + PterodactylTest::plan())])]);
    }

    private function invoice(int $qty = 1)
    {
        return app(Checkout::class)->create($this->user, $this->product->id, $qty, (string) Str::uuid());
    }

    private function paid(int $qty = 1)
    {
        $i = $this->invoice($qty);
        app(Billing::class)->settle($i->id, 'test', 'payment-'.$i->id, $i->total_minor, 'BRL');

        return $i;
    }

    private function start(Operation $o): PreparePterodactylAccount
    {
        (new RunOperation($o->id))->handle();
        $o->refresh();
        $this->assertSame('processing', $o->status);
        Queue::assertPushed(PreparePterodactylAccount::class, fn ($j) => $j->operationId === $o->id && $j->executionToken === $o->execution_token);

        return new PreparePterodactylAccount($o->id, $o->execution_token);
    }

    private function fake(bool $timeout = false, ?callable $hook = null): void
    {
        Http::fake(function ($r) use ($timeout, $hook) {
            $this->assertStringContainsString('/api/application/users', $r->url());
            if ($r->method() === 'POST') {
                $this->posts++;
                $this->assertArrayNotHasKey('password', $r->data());
                $this->assertFalse($r['root_admin']);
                $this->remote = ['object' => 'user', 'attributes' => ['id' => 41] + $r->data()];
                if ($hook) {
                    $hook();
                }

                return $timeout ? Http::failedConnection() : Http::response($this->remote, 201);
            }

            return $this->remote ? Http::response($this->remote) : Http::response(['errors' => [['code' => 'NotFoundHttpException']]], 404);
        });
    }

    public function test_unpaid_checkout_has_frozen_identity_but_no_http_or_account_job(): void
    {
        $i = $this->invoice();
        $s = $i->services->sole();
        $this->assertNull($s->provisioning['remote_user_id']);
        $this->assertTrue($s->provisioning['auto_account']);
        $this->assertSame(['first_name' => 'Cliente', 'last_name' => 'Exemplo'], $s->provisioning['account_names']);
        $this->assertSame(0, Operation::count());
        $this->assertSame(0, PterodactylAccountRequest::count());
        Http::assertNothingSent();
        Queue::assertNotPushed(PreparePterodactylAccount::class);
    }

    public function test_paid_preparation_is_separate_from_server_creation_and_duplicate_delivery_is_harmless(): void
    {
        $i = $this->paid();
        $o = Operation::sole();
        $job = $this->start($o);
        Http::assertNothingSent();
        $this->fake();
        $job->handle();
        $job->handle();
        $this->assertSame(1, $this->posts);
        $this->assertSame('pending', $o->fresh()->status);
        $this->assertNull($o->fresh()->sent_at);
        $this->assertSame(41, $i->services->sole()->fresh()->provisioning['remote_user_id']);
        $file = base_path('.cache/purchase-'.Str::uuid().'.json');
        try {
            Http::swap(new Factory);
            FakePterodactyl::install($file, 41, $this->user->email);
            (new RunOperation($o->id))->handle();
            $this->assertSame('done', $o->fresh()->status);
            $this->assertSame('active', $i->services->sole()->fresh()->status);
            $job->failed(new \RuntimeException('late callback'));
            $this->assertSame('done', $o->fresh()->status);
        } finally {
            @unlink($file);
        }
    }

    public function test_two_services_share_one_remote_account(): void
    {
        $this->paid(2);
        $ops = Operation::orderBy('id')->get();
        $a = $this->start($ops[0]);
        $b = $this->start($ops[1]);
        $this->fake();
        $a->handle();
        $b->handle();
        $this->assertSame(1, $this->posts);
        $this->assertSame(1, PterodactylAccount::count());
        foreach ($ops as $o) {
            $this->assertSame('pending', $o->fresh()->status);
            $this->assertSame(41, $o->service->provisioning['remote_user_id']);
        }
    }

    public function test_concurrent_sibling_waits_in_queue_instead_of_marking_review(): void
    {
        $this->paid(2);
        $ops = Operation::orderBy('id')->get();
        $a = $this->start($ops[0]);
        $b = $this->start($ops[1])->withFakeQueueInteractions();
        $this->fake(false, function () use ($b, $ops) {
            $b->handle();
            $b->assertReleased(10);
            $this->assertSame('processing', $ops[1]->fresh()->status);
        });
        $a->handle();
        $b->handle();
        $this->assertSame(1, $this->posts);
        $this->assertSame('pending', $ops[1]->fresh()->status);
    }

    public function test_uncertain_post_can_resume_through_admin_without_second_post(): void
    {
        $this->paid();
        $o = Operation::sole();
        $job = $this->start($o);
        $this->fake(true);
        $job->handle();
        $this->assertSame('review', $o->fresh()->status);
        $this->assertNotNull(PterodactylAccountRequest::sole()->sent_at);
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get('/admin/operacoes')->assertOk()->assertSee('Retomar preparação de conta');
        $this->post('/admin/operacoes/'.$o->id.'/preparar-conta', ['note' => 'Conferido no provedor', 'ack' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $this->start($o)->handle();
        $this->assertSame('pending', $o->fresh()->status);
        $this->assertSame(1, $this->posts);
    }

    public function test_unverified_email_cannot_start_automatic_purchase(): void
    {
        $this->user->forceFill(['email_verified_at' => null])->save();
        try {
            $this->invoice();
            $this->fail('Expected validation');
        } catch (ValidationException) {
        }
        Http::assertNothingSent();
        $this->assertSame(0, PterodactylAccountRequest::count());
    }

    public function test_changed_email_after_payment_stops_before_http(): void
    {
        $this->paid();
        $o = Operation::sole();
        $j = $this->start($o);
        $this->user->update(['email' => 'changed@example.test']);
        $j->handle();
        $this->assertSame('review', $o->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_unpaid_operation_cannot_create_remote_user(): void
    {
        $i = $this->invoice();
        $o = Operation::create(['service_id' => $i->services->sole()->id, 'action' => 'create', 'status' => 'pending', 'reference' => 'unpaid-manual']);
        $this->start($o)->handle();
        $this->assertSame('review', $o->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_old_job_and_failure_cannot_overwrite_new_execution(): void
    {
        $this->paid();
        $o = Operation::sole();
        $j = $this->start($o);
        $o->update(['execution_token' => (string) Str::uuid()]);
        $j->handle();
        $j->failed(new \RuntimeException);
        Http::assertNothingSent();
        $this->assertSame('processing', $o->fresh()->status);
    }

    public function test_flag_disabled_after_payment_stops_before_http(): void
    {
        $this->paid();
        $o = Operation::sole();
        $j = $this->start($o);
        config(['lagos.native_provisioning' => false]);
        $j->handle();
        $this->assertSame('review', $o->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_resume_route_rejects_other_customer_and_server_already_sent(): void
    {
        $this->paid();
        $o = Operation::sole();
        $o->update(['status' => 'review']);
        $url = '/admin/operacoes/'.$o->id.'/preparar-conta';
        $data = ['note' => 'Conferido no provedor', 'ack' => 1];
        $this->actingAs($this->user)->post($url, $data)->assertForbidden();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $o->update(['sent_at' => now()]);
        $this->actingAs($admin)->post($url, $data)->assertStatus(409);
        Http::assertNothingSent();
    }

    public function test_legacy_plan_still_requires_explicit_link(): void
    {
        $p = $this->product->provisioning;
        unset($p['auto_account']);
        $this->product->update(['provisioning' => $p]);
        $this->expectException(ValidationException::class);
        $this->invoice();
    }
}
