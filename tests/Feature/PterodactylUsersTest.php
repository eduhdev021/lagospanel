<?php

namespace Tests\Feature;

use App\Models\Connector;
use App\Models\Product;
use App\Models\PterodactylAccount;
use App\Models\PterodactylAccountRequest;
use App\Models\StaffRole;
use App\Models\User;
use App\Provisioning\PterodactylConfig;
use App\Services\Checkout;
use App\Services\PterodactylUsers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PterodactylUsersTest extends TestCase
{
    use RefreshDatabase;

    private Connector $connector;

    private User $client;

    private User $admin;

    private ?array $remote = null;

    private array $calls = [];

    private array $names = ['first_name' => 'Cliente', 'last_name' => 'Exemplo'];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['lagos.native_provisioning' => true]);
        $this->connector = Connector::create(['name' => 'Ptero users', 'driver' => 'pterodactyl', 'endpoint' => 'https://ptero-users.invalid', 'token' => 'private-application-token', 'active' => true]);
        $this->client = User::factory()->create();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();
    }

    private function fake(?callable $post = null, ?callable $get = null): void
    {
        Http::fake(function ($r) use ($post, $get) {
            $this->assertStringStartsWith('https://ptero-users.invalid/api/application/users', $r->url());
            $this->assertSame(['Bearer private-application-token'], $r->header('Authorization'));
            $this->calls[] = ['method' => $r->method(), 'url' => $r->url(), 'data' => $r->data()];
            if ($r->method() === 'POST') {
                $this->remote = ['object' => 'user', 'attributes' => ['id' => 41] + $r->data()];

                return $post ? $post($r) : Http::response($this->remote, 201);
            }
            if ($get) {
                return $get($r);
            }

            return $this->remote ? Http::response($this->remote) : Http::response(['errors' => [['code' => 'NotFoundHttpException']]], 404);
        });
    }

    private function create(): PterodactylAccountRequest
    {
        return app(PterodactylUsers::class)->create($this->connector, $this->client, $this->names, $this->admin->id);
    }

    private function posts(): int
    {
        return count(array_filter($this->calls, fn ($c) => $c['method'] === 'POST'));
    }

    private function path(): string
    {
        return '/admin/integracoes/'.$this->connector->id.'/contas';
    }

    public function test_admin_creates_non_admin_without_local_password_and_links_after_two_reads(): void
    {
        $this->fake();
        $this->actingAs($this->admin)->post($this->path().'/criar-remota', $this->names + ['user_id' => $this->client->id, 'ack' => 1, 'root_admin' => true, 'password' => 'MustNotBeForwarded123'])->assertSessionHasNoErrors();
        $r = PterodactylAccountRequest::sole();
        $this->assertSame('done', $r->status);
        $this->assertSame(41, PterodactylAccount::sole()->remote_user_id);
        $this->assertCount(4, $this->calls);
        $body = $this->calls[1]['data'];
        $this->assertFalse($body['root_admin']);
        $this->assertArrayNotHasKey('password', $body);
        $this->assertSame(strtolower($this->client->email), $body['email']);
        $this->assertSame($r->external_id, $body['external_id']);
        $this->assertMatchesRegularExpression('/^lg[a-f0-9]{28}$/', $body['username']);
        $this->get($this->path())->assertOk()->assertSee('Vinculado')->assertDontSee('private-application-token');
    }

    public function test_automatic_link_unblocks_existing_pterodactyl_checkout(): void
    {
        $this->fake();
        $this->create();
        $product = Product::create(['name' => 'Game', 'slug' => 'auto-account-game', 'price_minor' => 100, 'setup_minor' => 0, 'cycle' => 'monthly', 'active' => true, 'connector_id' => $this->connector->id, 'provisioning' => PterodactylConfig::product(['ptero_config' => json_encode(PterodactylTest::plan())])]);
        $invoice = app(Checkout::class)->create($this->client, $product->id, 1, (string) Str::uuid());
        $this->assertSame(41, $invoice->services->sole()->provisioning['remote_user_id']);
        $this->assertSame($this->client->email, $invoice->services->sole()->provisioning['email']);
        $this->assertSame(1, $this->posts());
    }

    public function test_integration_manager_without_customer_permission_cannot_create(): void
    {
        $role = StaffRole::create(['name' => 'Integrations only', 'permissions' => ['integrations.view', 'integrations.manage']]);
        $staff = User::factory()->create();
        $staff->forceFill(['staff_role_id' => $role->id])->save();
        $this->actingAs($staff)->post($this->path().'/criar-remota', $this->names + ['user_id' => $this->client->id, 'ack' => 1])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_repeated_create_and_inspect_do_not_send_another_request(): void
    {
        $this->fake();
        $r = $this->create();
        $this->create();
        app(PterodactylUsers::class)->inspect($r, $this->admin->id);
        $this->assertSame(1, $this->posts());
        $this->assertCount(4, $this->calls);
        $this->assertSame(1, PterodactylAccount::count());
    }

    public function test_timeout_after_provider_commit_can_be_reconciled_without_recreation(): void
    {
        $this->fake(fn () => Http::failedConnection());
        $r = $this->create();
        $this->assertSame('review', $r->status);
        $this->assertNotNull($r->sent_at);
        $this->assertSame(0, PterodactylAccount::count());
        $r = app(PterodactylUsers::class)->inspect($r, $this->admin->id);
        $this->assertSame('done', $r->status);
        $this->assertSame(1, $this->posts());
        $this->assertSame(41, PterodactylAccount::sole()->remote_user_id);
    }

    public function test_sent_request_with_absent_result_is_never_reposted(): void
    {
        $this->fake(function () {
            $this->remote = null;

            return Http::failedConnection();
        });
        $r = $this->create();
        $this->assertSame('review', $r->status);
        $this->assertSame('review', app(PterodactylUsers::class)->inspect($r, $this->admin->id)->status);
        $this->assertSame('review', $this->create()->status);
        $this->assertSame(1, $this->posts());
        $this->assertSame(0, PterodactylAccount::count());
    }

    public function test_preflight_failure_does_not_mark_sent_and_can_retry_safely(): void
    {
        $healthy = false;
        $this->fake(null, function () use (&$healthy) {
            return ! $healthy ? Http::response('proxy not found', 404) : ($this->remote ? Http::response($this->remote) : Http::response(['errors' => [['code' => 'NotFoundHttpException']]], 404));
        });
        $r = $this->create();
        $this->assertSame('review', $r->status);
        $this->assertNull($r->sent_at);
        $this->assertSame(0, $this->posts());
        $healthy = true;
        $this->assertSame('done', $this->create()->status);
        $this->assertSame(1, $this->posts());
    }

    public function test_preexisting_external_identity_is_not_adopted_before_any_send(): void
    {
        $this->fake(null, function () {
            $r = PterodactylAccountRequest::sole();

            return Http::response(['object' => 'user', 'attributes' => ['id' => 41, 'external_id' => $r->external_id, 'username' => $r->username, 'email' => $r->email, 'root_admin' => false]]);
        });
        $r = $this->create();
        $this->assertSame('review', $r->status);
        $this->assertNull($r->sent_at);
        $this->assertSame(0, $this->posts());
        $this->assertSame(0, PterodactylAccount::count());
    }

    public function test_remote_admin_or_email_mismatch_prevents_linking(): void
    {
        $this->fake(function () {
            $this->remote['attributes']['root_admin'] = true;

            return Http::response($this->remote, 201);
        });
        $r = $this->create();
        $this->assertSame('review', $r->status);
        $this->assertSame(0, PterodactylAccount::count());
        $this->remote['attributes']['root_admin'] = false;
        $this->remote['attributes']['email'] = 'someone-else@example.test';
        $this->assertSame('review', app(PterodactylUsers::class)->inspect($r, $this->admin->id)->status);
        $this->assertSame(0, PterodactylAccount::count());
    }

    public function test_second_read_must_have_same_remote_id_after_timeout(): void
    {
        $this->fake(fn () => Http::failedConnection(), function ($r) {
            if (! $this->remote) {
                return Http::response(['errors' => [['code' => 'NotFoundHttpException']]], 404);
            }
            $body = $this->remote;
            if (str_ends_with($r->url(), '/users/41')) {
                $body['attributes']['id'] = 42;
            }

            return Http::response($body);
        });
        $r = $this->create();
        $this->assertSame('review', app(PterodactylUsers::class)->inspect($r, $this->admin->id)->status);
        $this->assertSame(0, PterodactylAccount::count());
    }

    public function test_pause_after_mutation_prevents_late_binding(): void
    {
        $this->fake(function () {
            $this->connector->update(['active' => false]);

            return Http::response($this->remote, 201);
        });
        $this->assertSame('review', $this->create()->status);
        $this->assertSame(0, PterodactylAccount::count());
        $this->assertCount(2, $this->calls);
    }

    public function test_superseded_execution_cannot_publish_or_reclaim_new_execution_token(): void
    {
        $replacement = (string) Str::uuid();
        $this->fake(function () use ($replacement) {
            PterodactylAccountRequest::query()->update(['execution_token' => $replacement, 'status' => 'review']);

            return Http::response($this->remote, 201);
        });
        $r = $this->create();
        $this->assertSame('review', $r->status);
        $this->assertSame($replacement, $r->execution_token);
        $this->assertSame(0, PterodactylAccount::count());
        $this->assertCount(2, $this->calls);
    }

    public function test_changed_local_email_after_post_blocks_binding(): void
    {
        $this->fake(function () {
            $this->client->update(['email' => 'changed@example.test']);

            return Http::response($this->remote, 201);
        });
        $this->assertSame('review', $this->create()->status);
        $this->assertSame(0, PterodactylAccount::count());
    }

    public function test_remote_conflict_and_secrets_in_provider_error_are_not_exposed(): void
    {
        $this->fake(fn () => Http::response(['errors' => [['detail' => 'private-application-token provider-private-trace']]], 422));
        $this->actingAs($this->admin)->from($this->path())->post($this->path().'/criar-remota', $this->names + ['user_id' => $this->client->id, 'ack' => 1])->assertSessionHasErrors('pterodactyl');
        $this->get($this->path())->assertOk()->assertDontSee('provider-private-trace')->assertDontSee('private-application-token');
        $this->assertSame(0, PterodactylAccount::count());
    }

    public function test_recent_processing_blocks_duplicates_but_stale_work_can_be_inspected(): void
    {
        $this->fake(fn () => Http::failedConnection());
        $r = $this->create();
        $r->update(['status' => 'processing', 'started_at' => now()]);
        $this->actingAs($this->admin)->post($this->path().'/solicitacoes/'.$r->id.'/conferir')->assertStatus(409);
        $this->post($this->path(), ['user_id' => $this->client->id, 'remote_user_id' => 41, 'ack' => 1])->assertStatus(409);
        $r->update(['started_at' => now()->subMinutes(3)]);
        $this->post($this->path().'/solicitacoes/'.$r->id.'/conferir')->assertSessionHasNoErrors();
        $this->assertSame('done', $r->fresh()->status);
        $this->assertSame(1, $this->posts());
    }

    public function test_existing_manual_mapping_is_not_overwritten(): void
    {
        $this->fake(function () {
            PterodactylAccount::create(['connector_id' => $this->connector->id, 'user_id' => $this->client->id, 'remote_user_id' => 99]);

            return Http::response($this->remote, 201);
        });
        $this->assertSame('review', $this->create()->status);
        $this->assertSame(99, PterodactylAccount::sole()->remote_user_id);
    }

    public function test_client_and_read_only_staff_cannot_create_or_inspect(): void
    {
        $this->fake(fn () => Http::failedConnection());
        $r = $this->create();
        $before = count($this->calls);
        $role = StaffRole::create(['name' => 'Reader', 'permissions' => ['integrations.view', 'customers.view']]);
        $staff = User::factory()->create();
        $staff->forceFill(['staff_role_id' => $role->id])->save();
        foreach ([$this->client, $staff] as $user) {
            $this->actingAs($user)->post($this->path().'/criar-remota', $this->names + ['user_id' => $this->client->id, 'ack' => 1])->assertForbidden();
            $this->post($this->path().'/solicitacoes/'.$r->id.'/conferir')->assertForbidden();
        }
        $this->actingAs($staff)->get($this->path())->assertOk()->assertDontSee('Criar e vincular conta remota');
        $this->assertCount($before, $this->calls);
    }

    public function test_global_switch_and_unverified_client_block_creation(): void
    {
        $this->fake();
        config(['lagos.native_provisioning' => false]);
        $this->actingAs($this->admin)->post($this->path().'/criar-remota', $this->names + ['user_id' => $this->client->id, 'ack' => 1])->assertSessionHasErrors('pterodactyl');
        config(['lagos.native_provisioning' => true]);
        $this->client->forceFill(['email_verified_at' => null])->save();
        $this->post($this->path().'/criar-remota', $this->names + ['user_id' => $this->client->id, 'ack' => 1])->assertStatus(422);
        Http::assertNothingSent();
        $this->assertSame(0, PterodactylAccountRequest::count());
    }

    public function test_request_cannot_be_inspected_under_another_connector(): void
    {
        $this->fake(fn () => Http::failedConnection());
        $r = $this->create();
        $other = $this->connector->replicate();
        $other->name = 'Other';
        $other->save();
        $before = count($this->calls);
        $this->actingAs($this->admin)->post('/admin/integracoes/'.$other->id.'/contas/solicitacoes/'.$r->id.'/conferir')->assertNotFound();
        $this->assertCount($before, $this->calls);
    }
}
