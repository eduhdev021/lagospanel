<?php

namespace Tests\Feature;

use App\Jobs\RunOperation;
use App\Models\AccountContact;
use App\Models\Affiliate;
use App\Models\Connector;
use App\Models\DomainRegistration;
use App\Models\DomainTld;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\ServiceUpgrade;
use App\Models\User;
use App\Services\Billing;
use App\Services\Checkout;
use App\Services\Provisioning;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class WhmcsPaymenterParityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function client(): User
    {
        return User::factory()->create();
    }

    public function test_service_upgrade_prorrata_generates_invoice_and_applies_on_payment(): void
    {
        $client = $this->client();
        $basic = Product::create(['name' => 'VPS Basic', 'slug' => 'vps-basic', 'category' => 'VPS', 'price_minor' => 3000, 'setup_minor' => 0, 'cycle' => 'monthly', 'active' => true, 'allow_upgrade' => true]);
        $pro = Product::create(['name' => 'VPS Pro', 'slug' => 'vps-pro', 'category' => 'VPS', 'price_minor' => 9000, 'setup_minor' => 0, 'cycle' => 'monthly', 'active' => true, 'allow_upgrade' => true]);

        $service = Service::create([
            'user_id' => $client->id,
            'product_id' => $basic->id,
            'name' => $basic->name,
            'cycle' => 'monthly',
            'price_minor' => 3000,
            'status' => 'active',
            'next_due' => CarbonImmutable::today()->addDays(15),
        ]);

        $key = (string) Str::uuid();
        $res = $this->actingAs($client)->post(route('services.upgrade', $service), [
            'target_product_id' => $pro->id,
            'request_key' => $key,
        ]);

        $upgrade = ServiceUpgrade::firstOrFail();
        $this->assertSame('pending', $upgrade->status);
        $this->assertSame(3000, $upgrade->delta_minor); // (9000 - 3000) * 15 / 30 = 3000
        $res->assertRedirect(route('invoices.show', $upgrade->invoice_id));

        // Idempotent replay with same request_key
        $this->actingAs($client)->post(route('services.upgrade', $service), [
            'target_product_id' => $pro->id,
            'request_key' => $key,
        ])->assertRedirect(route('invoices.show', $upgrade->invoice_id));
        $this->assertSame(1, ServiceUpgrade::count());

        // Settle the upgrade invoice
        app(Billing::class)->settle($upgrade->invoice_id, 'manual', 'upgrade-1', 3000, 'BRL', $this->admin()->id);

        $service->refresh();
        $upgrade->refresh();
        $this->assertSame('completed', $upgrade->status);
        $this->assertSame($pro->id, $service->product_id);
        $this->assertSame('VPS Pro', $service->name);
        $this->assertSame(9000, $service->price_minor);
    }

    public function test_service_downgrade_prorrata_credits_wallet_immediately(): void
    {
        $client = $this->client();
        $pro = Product::create(['name' => 'Cloud Pro', 'slug' => 'cloud-pro', 'price_minor' => 6000, 'setup_minor' => 0, 'cycle' => 'monthly', 'active' => true, 'allow_upgrade' => true]);
        $starter = Product::create(['name' => 'Cloud Starter', 'slug' => 'cloud-starter', 'price_minor' => 3000, 'setup_minor' => 0, 'cycle' => 'monthly', 'active' => true, 'allow_upgrade' => true]);

        $service = Service::create([
            'user_id' => $client->id,
            'product_id' => $pro->id,
            'name' => $pro->name,
            'cycle' => 'monthly',
            'price_minor' => 6000,
            'status' => 'active',
            'next_due' => CarbonImmutable::today()->addDays(10),
        ]);

        $this->actingAs($client)->post(route('services.upgrade', $service), [
            'target_product_id' => $starter->id,
            'request_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $service->refresh();
        $client->refresh();
        $this->assertSame($starter->id, $service->product_id);
        $this->assertSame(3000, $service->price_minor);
        $this->assertSame(1000, $client->balance_minor); // (6000 - 3000) * 10 / 30 = 1000
    }

    public function test_product_addons_and_categories_workflow(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        $this->actingAs($admin)->post(route('admin.products.create'), [
            'name' => 'Hospedagem NVMe',
            'slug' => 'hosp-nvme',
            'category' => 'Hospedagem',
            'price' => '29,90',
            'setup' => '0,00',
            'cycle' => 'monthly',
            'active' => 1,
            'allow_quantity' => 1,
            'allow_upgrade' => 1,
        ])->assertRedirect(route('admin.products'));

        $this->get(route('store', ['category' => 'Hospedagem']))->assertOk()->assertSee('Hospedagem NVMe');
        $this->get(route('store', ['category' => 'Inexistente']))->assertOk()->assertDontSee('Hospedagem NVMe');

        $this->actingAs($admin)->post(route('admin.addons.save'), [
            'name' => 'IP Dedicado Extra',
            'description' => 'IPv4 exclusivo',
            'price' => '15,00',
            'setup' => '5,00',
            'cycle' => 'monthly',
            'active' => 1,
        ])->assertSessionHasNoErrors();

        $addon = ProductAddon::firstOrFail();
        $product = Product::firstOrFail();
        $service = Service::create([
            'user_id' => $client->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'cycle' => 'monthly',
            'price_minor' => $product->price_minor,
            'status' => 'active',
        ]);

        $res = $this->actingAs($client)->post(route('services.addons.order', $service), [
            'addon_id' => $addon->id,
        ]);
        $sa = ServiceAddon::firstOrFail();
        $this->assertSame('pending', $sa->status);
        $res->assertRedirect(route('invoices.show', $sa->invoice_id));

        app(Billing::class)->settle($sa->invoice_id, 'manual', 'addon-1', 2000, 'BRL', $admin->id);
        $this->assertSame('active', $sa->fresh()->status);
    }

    public function test_domain_tld_lookup_registration_nameservers_epp_lock_and_renewal(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        $this->actingAs($admin)->post(route('admin.domains.tlds.save'), [
            'tld' => '.com.br',
            'register_price' => '44,90',
            'transfer_price' => '44,90',
            'renew_price' => '49,90',
            'registrar' => 'registrobr',
            'active' => 1,
        ])->assertSessionHasNoErrors();

        $this->actingAs($client)->get(route('domains.index', ['q' => 'minhaempresa.com.br']))
            ->assertOk()
            ->assertSee('Disponível');

        $res = $this->actingAs($client)->post(route('domains.order'), [
            'domain' => 'minhaempresa.com.br',
            'operation_type' => 'register',
            'years' => 2,
            'ns1' => 'ns1.lagos.com.br',
            'ns2' => 'ns2.lagos.com.br',
        ]);

        $reg = DomainRegistration::firstOrFail();
        $this->assertSame('pending', $reg->status);
        // 1st year 4490 + 2nd year 4990 = 9480
        $this->assertSame(9480, $reg->invoice->total_minor);
        $res->assertRedirect(route('invoices.show', $reg->invoice_id));

        app(Billing::class)->settle($reg->invoice_id, 'manual', 'dom-1', 9480, 'BRL', $admin->id);
        $reg->refresh();
        $this->assertSame('active', $reg->status);
        $this->assertSame(CarbonImmutable::today()->addYears(2)->toDateString(), $reg->expires_at->toDateString());

        // Update nameservers
        $this->actingAs($client)->post(route('domains.nameservers', $reg), [
            'ns1' => 'ns1.cloudflare.com',
            'ns2' => 'ns2.cloudflare.com',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['ns1.cloudflare.com', 'ns2.cloudflare.com'], $reg->fresh()->nameservers);

        // Toggle transfer lock
        $this->actingAs($client)->post(route('domains.lock', $reg))->assertSessionHasNoErrors();
        $this->assertFalse($reg->fresh()->transfer_lock);

        // Reveal EPP requires password
        $this->actingAs($client)->post(route('domains.epp', $reg), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->actingAs($client)->post(route('domains.epp', $reg), ['password' => 'password'])->assertSessionHas('revealed_epp');

        // Renew domain for +1 year
        $renewRes = $this->actingAs($client)->post(route('domains.renew', $reg), ['years' => 1]);
        $renewInvoiceId = (int) basename($renewRes->headers->get('Location'));
        app(Billing::class)->settle($renewInvoiceId, 'manual', 'dom-renew-1', 4990, 'BRL', $admin->id);
        $this->assertSame(CarbonImmutable::today()->addYears(3)->toDateString(), $reg->fresh()->expires_at->toDateString());
    }

    public function test_affiliate_referral_commission_and_wallet_withdrawal(): void
    {
        $referrer = $this->client();
        $this->actingAs($referrer)->post(route('affiliates.enroll'))->assertSessionHasNoErrors();
        $affiliate = Affiliate::where('user_id', $referrer->id)->firstOrFail();

        // Track click on store
        $this->get(route('store', ['ref' => $affiliate->code]))->assertOk();
        $this->assertSame(1, $affiliate->fresh()->clicks);

        // Register referred user
        $buyer = User::factory()->create(['referred_by_id' => $referrer->id]);
        $product = Product::create(['name' => 'Dedicated Server', 'slug' => 'dedi-1', 'price_minor' => 25000, 'setup_minor' => 0, 'cycle' => 'monthly', 'active' => true]);

        $invoice = app(Checkout::class)->create($buyer, $product->id, 1, (string) Str::uuid());
        app(Billing::class)->settle($invoice->id, 'manual', 'aff-inv-1', 25000, 'BRL', $this->admin()->id);

        $affiliate->refresh();
        $this->assertSame(2500, $affiliate->available_minor); // 10% of 25000
        $this->assertSame(2500, $affiliate->total_earned_minor);

        // Withdraw to wallet
        $this->actingAs($referrer)->post(route('affiliates.withdraw'))->assertSessionHasNoErrors();
        $this->assertSame(0, $affiliate->fresh()->available_minor);
        $this->assertSame(2500, $affiliate->fresh()->total_withdrawn_minor);
        $this->assertSame(2500, $referrer->fresh()->balance_minor);
    }

    public function test_profile_fiscal_fields_and_subaccounts(): void
    {
        $owner = $this->client();
        $delegate = $this->client();

        $this->actingAs($owner)->post(route('profile.update'), [
            'name' => 'Empresa Teste Ltda',
            'tax_id' => '12.345.678/0001-90',
            'company_name' => 'Empresa Teste Tecnologia Ltda',
            'phone' => '+55 11 99999-0000',
            'billing_address' => 'Av. Paulista, 1000 — São Paulo/SP',
        ])->assertSessionHasNoErrors();

        $owner->refresh();
        $this->assertSame('12.345.678/0001-90', $owner->tax_id);
        $this->assertSame('Empresa Teste Tecnologia Ltda', $owner->company_name);

        $this->actingAs($owner)->post(route('subaccounts.store'), [
            'name' => 'Financeiro Delegado',
            'email' => $delegate->email,
            'permissions' => ['invoices.view', 'invoices.pay'],
            'password' => 'password',
        ])->assertSessionHasNoErrors();

        $contact = AccountContact::where('owner_id', $owner->id)->firstOrFail();
        $this->assertSame($delegate->id, $contact->contact_user_id);
        $this->assertSame(['invoices.view', 'invoices.pay'], $contact->permissions);

        $this->actingAs($delegate)->get(route('subaccounts.index'))->assertOk()->assertSee('Empresa Teste Ltda');

        $this->actingAs($owner)->post(route('subaccounts.destroy', $contact))->assertSessionHasNoErrors();
        $this->assertSame(0, AccountContact::count());
    }

    public function test_proxmox_and_virtualizor_vps_drivers_lifecycle(): void
    {
        config(['lagos.native_provisioning' => true]);
        Http::preventStrayRequests();
        Http::fake([
            'https://pve.example.test:8006/api2/json/nodes/pve1/qemu' => Http::response(['data' => 'UPID:pve1:create'], 200),
            'https://pve.example.test:8006/api2/json/nodes/pve1/qemu/*/status/suspend' => Http::response(['data' => 'UPID:pve1:suspend'], 200),
            'https://pve.example.test:8006/api2/json/nodes/pve1/qemu/*/status/resume' => Http::response(['data' => 'UPID:pve1:resume'], 200),
            'https://pve.example.test:8006/api2/json/nodes/pve1/qemu/*' => Http::response(['data' => 'UPID:pve1:delete'], 200),
        ]);

        $admin = $this->admin();
        $client = $this->client();

        $this->actingAs($admin)->post(route('admin.connectors.create'), [
            'name' => 'Proxmox Cluster',
            'driver' => 'proxmox',
            'endpoint' => 'https://pve.example.test:8006',
            'token' => 'pve-api-token-secret-123456',
            'active' => 1,
            'ack_native' => 1,
        ])->assertSessionHasNoErrors();

        $connector = Connector::where('driver', 'proxmox')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.products.create'), [
            'name' => 'VPS Proxmox 4G',
            'slug' => 'vps-proxmox-4g',
            'price' => '59,90',
            'cycle' => 'monthly',
            'connector_id' => $connector->id,
            'vps_config' => json_encode(['node' => 'pve1', 'template' => 'ubuntu-24.04', 'cores' => 2, 'memory_mb' => 4096, 'disk_gb' => 40]),
            'active' => 1,
        ])->assertRedirect(route('admin.products'));

        $product = Product::where('slug', 'vps-proxmox-4g')->firstOrFail();
        $invoice = app(Checkout::class)->create($client, $product->id, 1, (string) Str::uuid());
        app(Billing::class)->settle($invoice->id, 'manual', 'pve-inv-1', 5990, 'BRL', $admin->id);

        $service = $invoice->services()->firstOrFail();
        $op = $service->operations()->firstOrFail();
        (new RunOperation($op->id))->handle();

        $service->refresh();
        $this->assertSame('active', $service->status);
        $this->assertNotNull($service->remote_id);

        foreach (['suspend' => 'suspended', 'unsuspend' => 'active', 'terminate' => 'cancelled'] as $action => $expectedStatus) {
            $nextOp = app(Provisioning::class)->enqueue($service, $action, 'pve-'.$action);
            (new RunOperation($nextOp->id))->handle();
            $this->assertSame($expectedStatus, $service->fresh()->status);
        }
    }

    public function test_whmcs_and_paymenter_importer_is_idempotent_and_guards_passwords(): void
    {
        $admin = $this->admin();
        $payload = [
            'clients' => [
                ['id' => '501', 'name' => 'Cliente WHMCS', 'email' => 'whmcs@example.test', 'tax_id' => '111.222.333-44', 'company_name' => 'WHMCS Corp'],
            ],
            'products' => [
                ['id' => '10', 'name' => 'Plano Revenda WHMCS', 'price_minor' => 4990, 'cycle' => 'monthly', 'category' => 'Revenda'],
            ],
            'services' => [
                ['client_id' => '501', 'product_id' => '10', 'status' => 'active', 'price_minor' => 4990],
            ],
            'invoices' => [
                ['client_id' => '501', 'total_minor' => 4990, 'status' => 'paid', 'description' => 'Fatura WHMCS #900'],
            ],
        ];

        $this->actingAs($admin)->post(route('admin.import.run'), [
            'source' => 'whmcs',
            'payload_json' => json_encode($payload),
            'password' => 'password',
            'ack' => 1,
        ])->assertSessionHasNoErrors();

        $importedUser = User::where('email', 'whmcs@example.test')->firstOrFail();
        $this->assertTrue($importedUser->password_reset_required);
        $this->assertSame('whmcs', $importedUser->external_source);
        $this->assertSame('501', $importedUser->external_id);
        $this->assertSame('111.222.333-44', $importedUser->tax_id);
        $this->assertTrue(Product::where('name', 'Plano Revenda WHMCS')->exists());

        // Migration rollback must refuse when parity records exist
        $migration = require base_path('database/migrations/2026_10_04_000021_whmcs_paymenter_parity.php');
        $this->expectException(RuntimeException::class);
        $migration->down();
    }
}
