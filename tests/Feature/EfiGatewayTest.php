<?php

namespace Tests\Feature;

use App\Models\GatewayCharge;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Gateways;
use Efi\EfiPay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class EfiGatewayTest extends TestCase
{
    use RefreshDatabase;

    private string $certificate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->certificate = tempnam(sys_get_temp_dir(), 'efi-cert-');
        file_put_contents($this->certificate, 'fake certificate for SDK tests');
        config([
            'lagos.payments.live' => false,
            'lagos.payments.efi' => [
                'enabled' => true,
                'environment' => 'homologacao',
                'client_id' => 'client-id-test',
                'client_secret' => 'client-secret-test',
                'certificate' => $this->certificate,
                'certificate_password' => 'secret',
                'certificate_type' => 'PEM',
                'pix_key' => 'pix@example.test',
                'webhook_hmac' => 'callback-hmac-test',
                'charge_expiration' => 3600,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        @unlink($this->certificate);
        parent::tearDown();
    }

    private function sdk(): \Mockery\MockInterface
    {
        $sdk = Mockery::mock(EfiPay::class);
        $this->app->instance(EfiPay::class, $sdk);

        return $sdk;
    }

    private function invoice(User $user): Invoice
    {
        return Invoice::create([
            'user_id' => $user->id,
            'type' => 'order',
            'status' => 'unpaid',
            'currency' => 'BRL',
            'total_minor' => 2599,
            'snapshot' => [['name' => 'Plano de teste', 'quantity' => 1, 'unit_minor' => 2599]],
            'due_date' => today()->addDay(),
            'expires_at' => now()->addHour(),
        ]);
    }

    private function gatewayPost(User $user, Invoice $invoice)
    {
        $token = 'efi-test-csrf-token';

        return $this->actingAs($user)->withSession(['_token' => $token])->post(route('invoices.gateway', $invoice), ['gateway' => 'efi', '_token' => $token]);
    }

    public function test_efi_checkout_uses_sdk_and_creates_one_reusable_pix_charge(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoice($user);
        $sdk = $this->sdk();
        $sdk->shouldReceive('pixCreateCharge')->once()->andReturnUsing(fn (array $params, array $body) => ['txid' => $params['txid'], 'status' => 'ATIVA', 'loc' => ['id' => 321]]);
        $sdk->shouldReceive('pixGenerateQRCode')->once()->with(['id' => 321])->andReturn(['qrcode' => '000201pix-code-test', 'imagemQrcode' => 'data:image/png;base64,test']);

        $this->gatewayPost($user, $invoice)->assertRedirect(route('invoices.efi', $invoice));
        $charge = GatewayCharge::where('invoice_id', $invoice->id)->sole();
        $this->assertSame('active', $charge->status);
        $this->assertSame('000201pix-code-test', $charge->pix_copia_e_cola);
        $this->assertSame('unpaid', $invoice->fresh()->status);

        $this->gatewayPost($user, $invoice)->assertRedirect(route('invoices.efi', $invoice));
        $this->assertDatabaseCount('gateway_charges', 1);
    }

    public function test_efi_webhook_consults_authoritative_charge_through_sdk_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoice($user);
        $txid = 'LAGOS00000001'.strtoupper(substr(hash('sha256', (string) Str::uuid()), 0, 20));
        $endToEnd = 'E1234567890123456789012345678901';
        GatewayCharge::create(['invoice_id' => $invoice->id, 'gateway' => 'efi', 'reference' => $txid, 'status' => 'active', 'amount_minor' => 2599, 'currency' => 'BRL', 'pix_copia_e_cola' => 'pix-code-test', 'expires_at' => now()->addHour()]);
        $sdk = $this->sdk();
        $sdk->shouldReceive('pixDetailCharge')->once()->with(['txid' => $txid])->andReturn(['txid' => $txid, 'status' => 'CONCLUIDA', 'valor' => ['original' => '25.99'], 'pix' => [['txid' => $txid, 'endToEndId' => $endToEnd, 'valor' => '25.99']]]);
        $payload = ['pix' => [['txid' => $txid, 'endToEndId' => $endToEnd, 'valor' => '25.99']]];

        $this->postJson('/webhooks/efi?hmac=callback-hmac-test', $payload)->assertOk();
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('gateway_charges', ['reference' => $txid, 'status' => 'paid', 'provider_reference' => $endToEnd]);

        $this->postJson('/webhooks/efi?hmac=callback-hmac-test', $payload)->assertOk();
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_efi_webhook_can_be_configured_by_sdk_without_a_panel_url_field(): void
    {
        $sdk = $this->sdk();
        $sdk->shouldReceive('pixConfigWebhook')->once()->with(['chave' => 'pix@example.test'], ['webhookUrl' => 'https://panel.example.test/webhooks/efi?ignorar='])->andReturn(['webhookUrl' => 'https://panel.example.test/webhooks/efi?ignorar=']);

        $result = app(Gateways::class)->configureEfiWebhook('https://panel.example.test/webhooks/efi?ignorar=');

        $this->assertSame('https://panel.example.test/webhooks/efi?ignorar=', $result['webhookUrl']);
    }

    public function test_efi_webhook_requires_private_hmac_when_one_is_configured(): void
    {
        $this->postJson('/webhooks/efi', ['pix' => []])->assertForbidden();
    }

    public function test_efi_webhook_accepts_callback_without_hmac_when_hmac_is_not_configured(): void
    {
        config(['lagos.payments.efi.webhook_hmac' => null]);
        $this->postJson('/webhooks/efi', ['pix' => []])->assertOk();
    }
}
