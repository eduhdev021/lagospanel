<?php

namespace Tests\Feature;

use App\Models\GatewayCharge;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class EfiGatewayTest extends TestCase
{
    use RefreshDatabase;

    private string $certificate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->certificate = tempnam(sys_get_temp_dir(), 'efi-cert-');
        file_put_contents($this->certificate, 'fake certificate for Http::fake');
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
        @unlink($this->certificate);
        parent::tearDown();
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

    public function test_efi_checkout_creates_one_reusable_pix_charge(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoice($user);
        $createdTxid = null;
        Http::fake(function ($request) use (&$createdTxid) {
            if (str_ends_with($request->url(), '/oauth/token')) {
                return Http::response(['access_token' => 'efi-access-token']);
            }
            $createdTxid = basename(parse_url($request->url(), PHP_URL_PATH));

            return Http::response(['txid' => $createdTxid, 'status' => 'ATIVA', 'location' => 'qrcode.loc/abc', 'pixCopiaECola' => '000201pix-code-test']);
        });

        $this->gatewayPost($user, $invoice)->assertRedirect(route('invoices.efi', $invoice));
        $this->assertNotNull($createdTxid);
        $this->assertDatabaseHas('gateway_charges', ['invoice_id' => $invoice->id, 'gateway' => 'efi', 'reference' => $createdTxid, 'status' => 'active']);
        $this->assertSame('unpaid', $invoice->fresh()->status);
        Http::assertSentCount(2);

        $this->gatewayPost($user, $invoice)->assertRedirect(route('invoices.efi', $invoice));
        Http::assertSentCount(2);
    }

    public function test_efi_webhook_consults_authoritative_charge_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoice($user);
        $txid = 'LAGOS00000001'.strtoupper(substr(hash('sha256', (string) Str::uuid()), 0, 20));
        $endToEnd = 'E1234567890123456789012345678901';
        GatewayCharge::create(['invoice_id' => $invoice->id, 'gateway' => 'efi', 'reference' => $txid, 'status' => 'active', 'amount_minor' => 2599, 'currency' => 'BRL', 'pix_copia_e_cola' => 'pix-code-test', 'expires_at' => now()->addHour()]);
        Http::fake(function ($request) use ($txid, $endToEnd) {
            if (str_ends_with($request->url(), '/oauth/token')) {
                return Http::response(['access_token' => 'efi-access-token']);
            }

            return Http::response(['txid' => $txid, 'status' => 'CONCLUIDA', 'valor' => ['original' => '25.99'], 'pix' => [['txid' => $txid, 'endToEndId' => $endToEnd, 'valor' => '25.99']]]);
        });
        $payload = ['pix' => [['txid' => $txid, 'endToEndId' => $endToEnd, 'valor' => '25.99']]];

        $this->postJson('/webhooks/efi?hmac=callback-hmac-test', $payload)->assertOk();
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('gateway_charges', ['reference' => $txid, 'status' => 'paid', 'provider_reference' => $endToEnd]);

        $this->postJson('/webhooks/efi?hmac=callback-hmac-test', $payload)->assertOk();
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_efi_webhook_requires_private_hmac(): void
    {
        Http::fake();
        $this->postJson('/webhooks/efi', ['pix' => []])->assertForbidden();
        Http::assertNothingSent();
    }
}
