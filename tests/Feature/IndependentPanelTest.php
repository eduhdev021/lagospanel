<?php

namespace Tests\Feature;

use App\Jobs\RunOperation;
use App\Models\Connector;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Operation;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\VerifyEmailQueued;
use App\Services\Billing;
use App\Services\Checkout;
use App\Services\Maintenance;
use App\Services\Provisioning;
use App\Support\Cycle;
use App\Support\Money;
use App\Support\Totp;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IndependentPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function user(bool $admin = false): User
    {
        $u = User::factory()->create();
        if ($admin) {
            $u->forceFill(['is_admin' => true])->save();
        }

        return $u;
    }

    private function product(array $extra = []): Product
    {
        return Product::create(array_replace(['name' => 'Cloud teste', 'slug' => 'cloud-'.Str::uuid(), 'price_minor' => 2599, 'setup_minor' => 100, 'cycle' => 'monthly', 'active' => true, 'stock' => 10], $extra));
    }

    private function invoice(?User $u = null, ?Product $p = null): Invoice
    {
        return app(Checkout::class)->create($u ?? $this->user(), ($p ?? $this->product())->id, 1, (string) Str::uuid());
    }

    private function rejects(callable $call): void
    {
        try {
            $call();
            $this->fail('Operação inválida foi aceita.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public function test_public_pages_render_without_legacy_runtime(): void
    {
        foreach (['/', '/loja', '/entrar', '/registrar', '/esqueci-senha', '/termos'] as $path) {
            $r = $this->get($path);
            $this->assertLessThan(400, $r->status(), $path);
        }$this->assertFileDoesNotExist(base_path('engine/lagospanel-engine.zip'));
        $this->assertFileDoesNotExist(public_path('wp-config.php'));
    }

    public function test_protected_pages_require_authentication(): void
    {
        foreach (['/painel', '/painel/faturas', '/painel/servicos', '/admin', '/admin/clientes'] as $p) {
            $this->get($p)->assertRedirect('/entrar');
        }
    }

    public function test_unverified_account_cannot_create_order(): void
    {
        $u = User::factory()->unverified()->create();
        $p = $this->product();
        $this->actingAs($u)->post('/pedidos', ['product_id' => $p->id, 'quantity' => 1, 'request_key' => (string) Str::uuid()])->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_registration_ignores_privileged_and_balance_fields(): void
    {
        $this->post('/registrar', ['name' => 'Maria', 'email' => 'MARIA@example.test', 'password' => 'SafePassword123!', 'password_confirmation' => 'SafePassword123!', 'terms' => 1, 'is_admin' => 1, 'balance_minor' => 999999, 'email_verified_at' => now()->toIso8601String()])->assertRedirect(route('verification.notice'));
        $u = User::where('email', 'maria@example.test')->firstOrFail();
        $this->assertFalse($u->is_admin);
        $this->assertSame(0, $u->balance_minor);
        $this->assertNull($u->email_verified_at);
        Notification::assertSentTo($u, VerifyEmailQueued::class);
    }

    public function test_signed_email_confirmation_and_tamper_rejection(): void
    {
        $u = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), ['id' => $u->id, 'hash' => sha1($u->email)]);
        $this->actingAs($u)->get($url.'&tamper=1')->assertForbidden();
        $this->get($url)->assertRedirect(route('dashboard'));
        $this->assertNotNull($u->fresh()->email_verified_at);
    }

    public function test_all_client_and_admin_screens_render(): void
    {
        $u = $this->user(true);
        $this->invoice($u);
        $this->actingAs($u);
        foreach (['/painel', '/painel/faturas', '/painel/servicos', '/painel/suporte', '/painel/perfil', '/admin', '/admin/produtos', '/admin/faturas', '/admin/servicos', '/admin/clientes', '/admin/suporte', '/admin/cupons', '/admin/integracoes', '/admin/operacoes', '/admin/auditoria'] as $p) {
            $this->get($p)->assertOk();
        }
    }

    public function test_clients_cannot_access_any_admin_screen_or_payment_action(): void
    {
        $u = $this->user();
        $i = $this->invoice($u);
        $this->actingAs($u);
        foreach (['/admin', '/admin/produtos', '/admin/faturas', '/admin/servicos', '/admin/clientes', '/admin/suporte', '/admin/cupons', '/admin/integracoes', '/admin/operacoes', '/admin/auditoria'] as $p) {
            $this->get($p)->assertForbidden();
        }$this->post('/admin/faturas/'.$i->id.'/confirmar', ['note' => 'Fake payment'])->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_cross_account_invoice_and_wallet_access_is_denied(): void
    {
        $i = $this->invoice();
        $other = $this->user();
        $this->actingAs($other)->get('/painel/faturas/'.$i->id)->assertNotFound();
        $this->post('/painel/faturas/'.$i->id.'/saldo')->assertNotFound();
        $this->post('/painel/faturas/'.$i->id.'/pagar', ['gateway' => 'stripe'])->assertNotFound();
    }

    public function test_cross_account_service_and_ticket_mutations_are_denied(): void
    {
        $i = $this->invoice();
        $t = Ticket::create(['user_id' => $i->user_id, 'subject' => 'Private', 'body' => 'Secret']);
        $this->actingAs($this->user())->post('/painel/servicos/'.$i->services->first()->id.'/cancelamento', ['reason' => 'cancel please'])->assertNotFound();
        $this->post('/painel/suporte/'.$t->id.'/responder', ['body' => 'attack'])->assertNotFound();
    }

    public function test_checkout_calculates_from_catalogue_not_client_input(): void
    {
        $u = $this->user();
        $p = $this->product();
        $this->actingAs($u)->post('/pedidos', ['product_id' => $p->id, 'quantity' => 2, 'request_key' => (string) Str::uuid(), 'total_minor' => 1, 'user_id' => 999])->assertRedirect();
        $this->assertSame(5398, Invoice::first()->total_minor);
        $this->assertSame($u->id, Invoice::first()->user_id);
        $this->assertSame(8, $p->fresh()->stock);
        $this->assertDatabaseCount('services', 2);
    }

    public function test_duplicate_order_is_idempotent_and_conflicting_replay_rejected(): void
    {
        $u = $this->user();
        $p = $this->product();
        $key = (string) Str::uuid();
        $a = app(Checkout::class)->create($u, $p->id, 2, $key);
        $b = app(Checkout::class)->create($u, $p->id, 2, $key);
        $this->assertSame($a->id, $b->id);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(8, $p->fresh()->stock);
        $this->rejects(fn () => app(Checkout::class)->create($u, $p->id, 1, $key));
    }

    public function test_stock_and_inactive_products_block_checkout(): void
    {
        $u = $this->user();
        foreach ([['stock' => 0], ['active' => false]] as $attrs) {
            $p = $this->product($attrs);
            $this->rejects(fn () => app(Checkout::class)->create($u, $p->id, 1, (string) Str::uuid()));
        }$this->assertDatabaseCount('invoices', 0);
    }

    public function test_coupon_is_locked_limited_and_initial_only(): void
    {
        $p = $this->product();
        $u = $this->user();
        Coupon::create(['code' => 'SAVE', 'percent' => 10, 'max_uses' => 1]);
        $i = app(Checkout::class)->create($u, $p->id, 1, (string) Str::uuid(), 'save');
        $this->assertSame(2429, $i->total_minor);
        $this->assertSame(2599, $i->services->first()->price_minor);
        $this->rejects(fn () => app(Checkout::class)->create($u, $p->id, 1, (string) Str::uuid(), 'SAVE'));
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_expired_coupon_rejected_without_stock_change(): void
    {
        $p = $this->product();
        Coupon::create(['code' => 'EXPIRED', 'percent' => 10, 'expires_at' => now()->subDay()]);
        $this->rejects(fn () => app(Checkout::class)->create($this->user(), $p->id, 1, (string) Str::uuid(), 'EXPIRED'));
        $this->assertSame(10, $p->fresh()->stock);
    }

    public function test_free_order_is_paid_without_crediting_money(): void
    {
        $i = $this->invoice(null, $this->product(['price_minor' => 0, 'setup_minor' => 0]));
        $this->assertSame('paid', $i->status);
        $this->assertSame('pending', $i->services->first()->status);
        $this->assertSame(0, Payment::sum('amount_minor'));
    }

    public function test_payment_replay_does_not_duplicate_payment_or_extend_term(): void
    {
        $i = $this->invoice();
        $billing = app(Billing::class);
        $billing->settle($i->id, 'manual', 'reference-1', $i->total_minor, 'BRL');
        $due = $i->services->first()->fresh()->next_due->toDateString();
        $billing->settle($i->id, 'manual', 'reference-1', $i->total_minor, 'BRL');
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame($due, $i->services->first()->fresh()->next_due->toDateString());
        $this->assertSame('pending', $i->services->first()->fresh()->status);
    }

    public function test_payment_mismatch_and_duplicate_settlement_fail_closed(): void
    {
        $i = $this->invoice();
        $b = app(Billing::class);
        $this->rejects(fn () => $b->settle($i->id, 'test', 'bad', 1, 'BRL'));
        $this->rejects(fn () => $b->settle($i->id, 'test', 'bad', $i->total_minor, 'USD'));
        $b->settle($i->id, 'test', 'good', $i->total_minor, 'BRL');
        $this->rejects(fn () => $b->settle($i->id, 'test', 'second', $i->total_minor, 'BRL'));
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_gateway_reference_cannot_be_reused_on_another_invoice(): void
    {
        $a = $this->invoice();
        $b = $this->invoice();
        app(Billing::class)->settle($a->id, 'test', 'shared', $a->total_minor, 'BRL');
        $this->rejects(fn () => app(Billing::class)->settle($b->id, 'test', 'shared', $b->total_minor, 'BRL'));
        $this->assertSame('unpaid', $b->fresh()->status);
    }

    public function test_cancelled_service_blocks_automatic_settlement(): void
    {
        $i = $this->invoice();
        $i->services->first()->update(['status' => 'cancelled']);
        $this->rejects(fn () => app(Billing::class)->settle($i->id, 'test', 'ref', $i->total_minor, 'BRL'));
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_wallet_payment_atomic_and_idempotent(): void
    {
        $u = $this->user();
        $i = $this->invoice($u);
        $b = app(Billing::class);
        $b->wallet($u->id, 10000, 'opening:test', 'Opening');
        $b->payWithWallet($u, $i->id);
        $b->payWithWallet($u, $i->id);
        $this->assertSame(7301, $u->fresh()->balance_minor);
        $this->assertDatabaseCount('wallet_entries', 2);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_insufficient_wallet_and_failed_settlement_roll_back(): void
    {
        $u = $this->user();
        $i = $this->invoice($u);
        $b = app(Billing::class);
        $this->rejects(fn () => $b->payWithWallet($u, $i->id));
        $this->assertDatabaseCount('wallet_entries', 0);
        $b->wallet($u->id, 5000, 'opening:test', 'Opening');
        $i->services->first()->update(['status' => 'cancelled']);
        $this->rejects(fn () => $b->payWithWallet($u, $i->id));
        $this->assertSame(5000, $u->fresh()->balance_minor);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_deposit_credit_is_once_and_cannot_be_paid_using_wallet(): void
    {
        $u = $this->user();
        $i = $u->invoices()->create(['type' => 'deposit', 'total_minor' => 5000, 'snapshot' => [], 'due_date' => today()]);
        $b = app(Billing::class);
        $this->rejects(fn () => $b->payWithWallet($u, $i->id));
        $b->settle($i->id, 'test', 'deposit', 5000, 'BRL');
        $b->settle($i->id, 'test', 'deposit', 5000, 'BRL');
        $this->assertSame(5000, $u->fresh()->balance_minor);
        $this->assertDatabaseCount('wallet_entries', 1);
    }

    public function test_cancellation_stops_new_renewals_without_deleting_service(): void
    {
        $u = $this->user();
        $i = $this->invoice($u);
        $s = $i->services->first();
        $this->actingAs($u)->post('/painel/servicos/'.$s->id.'/cancelamento', ['reason' => 'Não preciso mais'])->assertRedirect();
        $this->assertFalse($s->fresh()->auto_renew);
        $this->assertSame('pending', $s->fresh()->status);
    }

    public function test_manual_activation_requires_payment_and_audit(): void
    {
        $admin = $this->user(true);
        $i = $this->invoice();
        $s = $i->services->first();
        $p = app(Provisioning::class);
        $this->rejects(fn () => $p->manuallySet($s, 'active', $admin->id, 'Provisionado'));
        app(Billing::class)->settle($i->id, 'manual', 'ok', $i->total_minor, 'BRL');
        $p->manuallySet($s, 'active', $admin->id, 'Provisionado');
        $this->assertSame('active', $s->fresh()->status);
        $this->assertDatabaseHas('audit_events', ['event' => 'service.manual', 'user_id' => $admin->id]);
    }

    public function test_renewal_generation_and_payment_are_idempotent(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-01-31 12:00'));
        $i = $this->invoice();
        app(Billing::class)->settle($i->id, 'test', 'initial', $i->total_minor, 'BRL');
        $s = $i->services->first()->fresh();
        $s->update(['status' => 'active']);
        $this->assertSame('2026-02-28', $s->next_due->toDateString());
        $this->travelTo(CarbonImmutable::parse('2026-02-25 12:00'));
        app(Maintenance::class)->run();
        app(Maintenance::class)->run();
        $r = Invoice::where('type', 'renewal')->sole();
        $this->assertSame(2599, $r->total_minor);
        app(Billing::class)->settle($r->id, 'test', 'renewal', $r->total_minor, 'BRL');
        $this->assertSame('2026-03-31', $s->fresh()->next_due->toDateString());
        $this->travelBack();
    }

    public function test_cron_marks_overdue_and_does_not_create_duplicate_jobs(): void
    {
        $c = Connector::create(['name' => 'test', 'endpoint' => 'https://example.com', 'token' => 'very-secret-token', 'active' => true]);
        $i = $this->invoice(null, $this->product(['connector_id' => $c->id]));
        $s = $i->services->first();
        $s->update(['status' => 'active', 'remote_id' => 'remote-1']);
        $i->update(['due_date' => today()->subDays(4)]);
        app(Maintenance::class)->run();
        app(Maintenance::class)->run();
        $this->assertSame('overdue', $i->fresh()->status);
        $this->assertDatabaseCount('operations', 1);
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_remote_creation_contract_and_duplicate_job_delivery(): void
    {
        $c = Connector::create(['name' => 'API', 'endpoint' => 'https://example.com/lagos', 'token' => 'very-secret-token', 'active' => true]);
        $i = $this->invoice(null, $this->product(['connector_id' => $c->id]));
        Http::fake(['*' => Http::response(['success' => true, 'remote_id' => 'remote-42'])]);
        app(Billing::class)->settle($i->id, 'test', 'payment', $i->total_minor, 'BRL');
        $op = Operation::sole();
        (new RunOperation($op->id))->handle();
        (new RunOperation($op->id))->handle();
        $this->assertSame('active', $i->services->first()->fresh()->status);
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r->hasHeader('Idempotency-Key', $op->reference) && $r->hasHeader('Authorization', 'Bearer very-secret-token'));
    }

    public function test_http_200_error_does_not_activate_or_blindly_retry(): void
    {
        $c = Connector::create(['name' => 'API', 'endpoint' => 'https://example.com', 'token' => 'very-secret-token', 'active' => true]);
        $i = $this->invoice(null, $this->product(['connector_id' => $c->id]));
        Http::fake(['*' => Http::response(['success' => false, 'error' => 'denied'], 200)]);
        app(Billing::class)->settle($i->id, 'test', 'ok', $i->total_minor, 'BRL');
        $op = Operation::sole();
        (new RunOperation($op->id))->handle();
        $this->assertSame('review', $op->fresh()->status);
        $this->assertSame('pending', $i->services->first()->fresh()->status);
        $again = app(Provisioning::class)->enqueue($i->services->first(), 'create', 'another-reference');
        $this->assertSame($op->id, $again->id);
        (new RunOperation($op->id))->handle();
        Http::assertSentCount(1);
    }

    public function test_connector_secrets_are_encrypted_and_hidden(): void
    {
        $c = Connector::create(['name' => 'API', 'endpoint' => 'https://example.com', 'token' => 'very-secret-token', 'active' => true]);
        $this->assertNotSame('very-secret-token', DB::table('connectors')->value('token'));
        $this->assertArrayNotHasKey('token', $c->toArray());
        $this->assertSame('very-secret-token', $c->fresh()->token);
    }

    public function test_stripe_webhook_signature_amount_mode_and_replay(): void
    {
        $i = $this->invoice();
        config(['lagos.payments.stripe.enabled' => true, 'lagos.payments.stripe.webhook_secret' => 'webhook-secret', 'lagos.payments.live' => false]);
        $payload = ['type' => 'checkout.session.completed', 'livemode' => false, 'data' => ['object' => ['id' => 'cs_123', 'payment_intent' => 'pi_123', 'payment_status' => 'paid', 'metadata' => ['invoice_id' => (string) $i->id], 'amount_total' => $i->total_minor, 'currency' => 'brl']]];
        $body = json_encode($payload);
        $sig = 't='.time().',v1='.hash_hmac('sha256', time().'.'.$body, 'webhook-secret');
        $this->call('POST', '/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=bad'], $body)->assertForbidden();
        $this->call('POST', '/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $sig], $body)->assertOk();
        $this->call('POST', '/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $sig], $body)->assertOk();
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_mercado_pago_fetches_authoritative_payment_not_posted_amount(): void
    {
        $i = $this->invoice();
        config(['lagos.payments.mercadopago.enabled' => true, 'lagos.payments.mercadopago.token' => 'secret', 'lagos.payments.live' => false]);
        Http::fake(['https://api.mercadopago.com/v1/payments/123' => Http::response(['id' => 123, 'status' => 'approved', 'live_mode' => false, 'external_reference' => 'LAGOS-'.$i->id, 'transaction_amount' => 26.99, 'currency_id' => 'BRL'])]);
        $this->postJson('/webhooks/mercadopago', ['data' => ['id' => 123], 'amount' => 0, 'status' => 'approved'])->assertOk();
        $this->assertSame('paid', $i->fresh()->status);
        $this->assertSame(2699, Payment::sole()->amount_minor);
    }

    public function test_disabled_gateways_do_not_accept_callbacks(): void
    {
        $this->postJson('/webhooks/mercadopago', ['id' => '123'])->assertStatus(503);
        $this->postJson('/webhooks/stripe', [])->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_support_escapes_untrusted_content(): void
    {
        $u = $this->user();
        $this->actingAs($u)->post('/painel/suporte', ['subject' => '<script>alert(1)</script>', 'body' => '<img src=x onerror=alert(1)>', 'department' => 'support'])->assertRedirect();
        $this->get('/painel/suporte')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_login_logout_and_required_password_reset(): void
    {
        $u = $this->user();
        $this->post('/entrar', ['email' => $u->email, 'password' => 'password'])->assertRedirect('/painel');
        $this->assertAuthenticatedAs($u);
        $this->post('/sair')->assertRedirect('/entrar');
        $this->assertGuest();
        $u->forceFill(['password_reset_required' => true])->save();
        $this->post('/entrar', ['email' => $u->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_reset_token_is_single_use_and_clears_import_reset_flag(): void
    {
        $u = $this->user();
        $u->forceFill(['password_reset_required' => true])->save();
        $token = Password::createToken($u);
        $data = ['token' => $token, 'email' => $u->email, 'password' => 'NewSafePassword123!', 'password_confirmation' => 'NewSafePassword123!'];
        $this->post('/redefinir-senha', $data)->assertRedirect('/entrar');
        $this->assertFalse($u->fresh()->password_reset_required);
        $this->assertTrue(Hash::check($data['password'], $u->fresh()->password));
        $this->post('/redefinir-senha', $data)->assertSessionHasErrors('email');
    }

    public function test_two_factor_blocks_password_only_login_and_totp_replay(): void
    {
        $u = $this->user();
        $secret = Totp::secret();
        $u->forceFill(['totp_secret' => $secret])->save();
        $this->post('/entrar', ['email' => $u->email, 'password' => 'password'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $code = Totp::code($secret, intdiv(now()->timestamp, 30));
        $this->post('/duas-etapas', ['code' => $code])->assertRedirect('/painel');
        $this->assertAuthenticatedAs($u);
        $this->post('/sair');
        $this->post('/entrar', ['email' => $u->email, 'password' => 'password']);
        $this->post('/duas-etapas', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_code_single_use_and_password_change_invalidates_challenge(): void
    {
        $u = $this->user();
        $u->forceFill(['totp_secret' => Totp::secret(), 'recovery_codes' => [hash('sha256', 'recovery123')]])->save();
        $this->post('/entrar', ['email' => $u->email, 'password' => 'password']);
        $u->forceFill(['password' => 'DifferentPassword123!'])->save();
        $this->post('/duas-etapas', ['code' => 'recovery123'])->assertSessionHasErrors('code');
        $this->post('/entrar', ['email' => $u->email, 'password' => 'DifferentPassword123!']);
        $this->post('/duas-etapas', ['code' => 'recovery123'])->assertRedirect('/painel');
        $this->assertSame([], $u->fresh()->recovery_codes);
        $this->post('/sair');
        $this->post('/entrar', ['email' => $u->email, 'password' => 'DifferentPassword123!']);
        $this->post('/duas-etapas', ['code' => 'recovery123'])->assertSessionHasErrors('code');
    }

    public function test_two_factor_challenge_expires_after_five_failed_codes(): void
    {
        $u = $this->user();
        $secret = Totp::secret();
        $u->forceFill(['totp_secret' => $secret])->save();
        $this->post('/entrar', ['email' => $u->email, 'password' => 'password']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/duas-etapas', ['code' => 'invalid'])->assertSessionHasErrors();
        }$this->post('/duas-etapas', ['code' => Totp::code($secret, intdiv(now()->timestamp, 30))])->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_money_uses_exact_minor_units_and_rejects_ambiguous_values(): void
    {
        $this->assertSame(123456, Money::parse('1234,56'));
        $this->assertSame(10, Money::parse('0.10'));
        foreach (['-1', '1e3', '1.234', 'NaN', '1,000.00'] as $v) {
            $this->rejects(fn () => Money::parse($v));
        }
    }

    public function test_cycle_preserves_month_end_and_leap_year_anchor(): void
    {
        $this->assertSame('2028-02-29', Cycle::next(CarbonImmutable::parse('2028-01-31'), 'monthly', 31)->toDateString());
        $this->assertSame('2028-03-31', Cycle::next(CarbonImmutable::parse('2028-02-29'), 'monthly', 31)->toDateString());
        $this->assertNull(Cycle::next(CarbonImmutable::today(), 'one_time'));
    }

    public function test_totp_matches_rfc_vector(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $this->assertSame('287082', Totp::code($secret, 1));
    }

    public function test_timeout_requires_remote_reconciliation(): void
    {
        $c = Connector::create(['name' => 'API', 'endpoint' => 'https://example.com', 'token' => 'very-secret-token', 'active' => true]);
        $i = $this->invoice(null, $this->product(['connector_id' => $c->id]));
        Http::fake(['*' => Http::failedConnection()]);
        app(Billing::class)->settle($i->id, 'test', 'timeout-payment', $i->total_minor, 'BRL');
        $op = Operation::sole();
        (new RunOperation($op->id))->handle();
        $this->assertSame('review', $op->fresh()->status);
        $this->assertSame('pending', $i->services->first()->fresh()->status);
    }

    public function test_queue_and_payment_roll_back_together(): void
    {
        $c = Connector::create(['name' => 'API', 'endpoint' => 'https://example.com', 'token' => 'very-secret-token', 'active' => true]);
        $i = $this->invoice(null, $this->product(['connector_id' => $c->id]));
        try {
            DB::transaction(function () use ($i) {
                app(Billing::class)->settle($i->id, 'test', 'rollback', $i->total_minor, 'BRL');
                $this->assertDatabaseCount('jobs', 1);
                throw new \RuntimeException('simulate failure');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('simulate failure', $e->getMessage());
        }$this->assertSame('unpaid', $i->fresh()->status);
        foreach (['jobs', 'operations', 'payments'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_stale_overdue_job_does_not_suspend_a_paid_service(): void
    {
        $c = Connector::create(['name' => 'API', 'endpoint' => 'https://example.com', 'token' => 'very-secret-token', 'active' => true]);
        $i = $this->invoice(null, $this->product(['connector_id' => $c->id]));
        $s = $i->services->first();
        $s->update(['status' => 'active', 'remote_id' => 'remote-1']);
        $i->update(['due_date' => today()->subDays(4)]);
        app(Maintenance::class)->run();
        app(Billing::class)->settle($i->id, 'test', 'late-payment', $i->total_minor, 'BRL');
        Http::fake();
        (new RunOperation(Operation::sole()->id))->handle();
        Http::assertNothingSent();
        $this->assertSame('active', $s->fresh()->status);
    }

    public function test_two_factor_setup_and_disable_require_password_and_fresh_code(): void
    {
        $u = $this->user();
        $this->actingAs($u)->post('/painel/2fa/iniciar', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->post('/painel/2fa/iniciar', ['password' => 'password'])->assertRedirect();
        $setup = session('totp_setup');
        $code = Totp::code($setup['secret'], intdiv(now()->timestamp, 30));
        $this->post('/painel/2fa/ativar', ['code' => $code])->assertRedirect()->assertSessionHas('recovery_codes');
        $this->assertCount(8, $u->fresh()->recovery_codes);
        $this->assertNotSame($setup['secret'], DB::table('users')->where('id', $u->id)->value('totp_secret'));
        $this->actingAs($u->fresh())->post('/painel/2fa/desativar', ['password' => 'password', 'code' => $code])->assertSessionHasErrors('code');
        $this->travel(31)->seconds();
        $this->post('/painel/2fa/desativar', ['password' => 'password', 'code' => Totp::code($setup['secret'], intdiv(now()->timestamp, 30))])->assertRedirect();
        $this->assertNull($u->fresh()->totp_secret);
        $this->travelBack();
    }

    public function test_stripe_rejects_wrong_amount_or_environment_even_when_signed(): void
    {
        $i = $this->invoice();
        config(['lagos.payments.stripe.enabled' => true, 'lagos.payments.stripe.webhook_secret' => 'secret', 'lagos.payments.live' => false]);
        foreach ([[false, 1], [true, $i->total_minor]] as [$live,$amount]) {
            $data = ['type' => 'checkout.session.completed', 'livemode' => $live, 'data' => ['object' => ['id' => 'cs_bad', 'payment_intent' => 'pi_bad', 'payment_status' => 'paid', 'metadata' => ['invoice_id' => $i->id], 'amount_total' => $amount, 'currency' => 'brl']]];
            $body = json_encode($data);
            $sig = 't='.time().',v1='.hash_hmac('sha256', time().'.'.$body, 'secret');
            $this->call('POST', '/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $sig], $body)->assertStatus(422);
        }$this->assertDatabaseCount('payments', 0);
    }

    public function test_checkout_gateway_uses_server_amount_and_return_does_not_settle(): void
    {
        $u = $this->user();
        $i = $this->invoice($u);
        config(['lagos.payments.stripe.enabled' => true, 'lagos.payments.stripe.secret' => 'sk_test', 'lagos.payments.stripe.webhook_secret' => 'whsec_test']);
        Http::fake(['https://api.stripe.com/v1/checkout/sessions' => Http::response(['url' => 'https://checkout.stripe.com/c/pay/test'])]);
        $this->actingAs($u)->post('/painel/faturas/'.$i->id.'/pagar', ['gateway' => 'stripe', 'amount_total' => 1])->assertRedirect('https://checkout.stripe.com/c/pay/test');
        Http::assertSent(fn ($r) => $r['line_items'][0]['price_data']['unit_amount'] === $i->total_minor);
        $this->get('/painel/faturas/'.$i->id.'?retorno=stripe&paid=1')->assertOk();
        $this->assertSame('unpaid', $i->fresh()->status);
    }

    public function test_security_headers_keep_authenticated_pages_out_of_shared_cache(): void
    {
        $this->actingAs($this->user())->get('/painel')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Security-Policy');
        $this->assertStringContainsString('no-store', $this->get('/painel')->headers->get('Cache-Control'));
    }

    public function test_payment_during_remote_suspend_enqueues_compensating_unsuspend(): void
    {
        $c = Connector::create(['name' => 'API', 'endpoint' => 'https://example.com', 'token' => 'very-secret-token', 'active' => true]);
        $i = $this->invoice(null, $this->product(['connector_id' => $c->id]));
        $s = $i->services->first();
        $s->update(['status' => 'active', 'remote_id' => 'remote-1']);
        $i->update(['due_date' => today()->subDays(4)]);
        app(Maintenance::class)->run();
        Http::fake(function () use ($i) {
            app(Billing::class)->settle($i->id, 'test', 'inflight-payment', $i->total_minor, 'BRL');

            return Http::response(['success' => true, 'remote_id' => 'remote-1']);
        });
        $op = Operation::sole();
        (new RunOperation($op->id))->handle();
        $next = Operation::where('action', 'unsuspend')->sole();
        (new RunOperation($next->id))->handle();
        $this->assertSame('active', $s->fresh()->status);
        $this->assertSame('done', $next->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
        Http::assertSentCount(2);
    }
}
