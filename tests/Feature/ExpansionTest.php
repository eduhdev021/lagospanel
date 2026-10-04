<?php

namespace Tests\Feature;

use App\Models\AdminPreference;
use App\Models\ApiToken;
use App\Models\Article;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Invoice;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\PaymentReview;
use App\Models\Product;
use App\Models\StaffRole;
use App\Models\StockReservation;
use App\Models\User;
use App\Notifications\InvoiceReminder;
use App\Services\Billing;
use App\Services\Checkout;
use App\Services\OrderLifecycle;
use App\Services\Pricing;
use App\Services\Reminders;
use App\Support\Cycle;
use App\Support\Totp;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExpansionTest extends TestCase
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
        return Product::create(array_replace(['name' => 'Plano', 'slug' => (string) Str::uuid(), 'price_minor' => 1000, 'setup_minor' => 100, 'cycle' => 'monthly', 'stock' => 10, 'active' => true], $extra))->fresh();
    }

    private function invoice(?User $u = null, ?Product $p = null): Invoice
    {
        return app(Checkout::class)->create($u ?? $this->user(), ($p ?? $this->product())->id, 1, (string) Str::uuid());
    }

    private function option(Product $p): OptionValue
    {
        $o = $p->options()->create(['name' => 'Memória', 'required' => true]);

        return $o->values()->create(['label' => '8 GB', 'recurring_minor' => 500, 'setup_minor' => 200]);
    }

    private function reject(callable $f): void
    {
        try {
            $f();
            $this->fail('A operação deveria ser rejeitada.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    private function staff(array $permissions): User
    {
        $u = $this->user();
        $role = StaffRole::create(['name' => (string) Str::uuid(), 'permissions' => $permissions]);
        $u->forceFill(['staff_role_id' => $role->id])->save();

        return $u->fresh();
    }

    private function token(User $u, array $scopes): string
    {
        $raw = 'lp_'.bin2hex(random_bytes(32));
        ApiToken::create(['user_id' => $u->id, 'name' => 'Test', 'token_hash' => hash('sha256', $raw), 'password_fingerprint' => $u->apiCredentialFingerprint(), 'scopes' => $scopes, 'expires_at' => now()->addDay()]);

        return $raw;
    }

    public function test_new_customer_and_admin_screens_render(): void
    {
        $u = $this->user(true);
        $p = $this->product();
        $this->actingAs($u);
        foreach (['/painel/carrinho', '/painel/api', '/admin/equipe', '/admin/conhecimento', '/admin/relatorios', '/admin/conciliacao', '/admin/produtos/'.$p->id.'/opcoes', '/conhecimento'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_multi_product_checkout_is_atomic_and_uses_one_invoice(): void
    {
        $u = $this->user();
        $a = $this->product();
        $b = $this->product(['price_minor' => 2000]);
        $i = app(Checkout::class)->cart($u, [['product_id' => $a->id, 'quantity' => 2], ['product_id' => $b->id, 'quantity' => 1]], (string) Str::uuid());
        $this->assertSame(4300, $i->total_minor);
        $this->assertCount(3, $i->services);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertSame(8, $a->fresh()->stock);
        $this->assertSame(9, $b->fresh()->stock);
        $this->assertDatabaseCount('stock_reservations', 2);
    }

    public function test_cart_replay_is_independent_of_line_order(): void
    {
        $u = $this->user();
        $a = $this->product();
        $b = $this->product();
        $lines = [['product_id' => $a->id, 'quantity' => 1], ['product_id' => $b->id, 'quantity' => 2]];
        $key = (string) Str::uuid();
        $first = app(Checkout::class)->cart($u, $lines, $key);
        $second = app(Checkout::class)->cart($u, array_reverse($lines), $key);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_invalid_second_product_rolls_back_everything(): void
    {
        $a = $this->product();
        $b = $this->product(['stock' => 0]);
        $this->reject(fn () => app(Checkout::class)->cart($this->user(), [['product_id' => $a->id, 'quantity' => 1], ['product_id' => $b->id, 'quantity' => 1]], (string) Str::uuid()));
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('services', 0);
        $this->assertSame(10, $a->fresh()->stock);
    }

    public function test_configurable_price_is_saved_as_immutable_service_snapshot(): void
    {
        $p = $this->product();
        $v = $this->option($p);
        $i = app(Checkout::class)->create($this->user(), $p->id, 2, (string) Str::uuid(), null, [$v->id]);
        $this->assertSame(3600, $i->total_minor);
        $s = $i->services->first();
        $this->assertSame(1500, $s->price_minor);
        $this->assertSame('8 GB', $s->configuration[0]['label']);
        $v->update(['label' => 'Modified', 'recurring_minor' => 9000]);
        $this->assertSame('8 GB', $s->fresh()->configuration[0]['label']);
        $this->assertSame(1500, $s->fresh()->price_minor);
    }

    public function test_required_foreign_duplicate_and_disabled_options_are_rejected(): void
    {
        $p = $this->product();
        $v = $this->option($p);
        $foreign = $this->option($this->product());
        $q = app(Pricing::class);
        foreach ([[], [$foreign->id], [$v->id, $v->id]] as $ids) {
            $this->reject(fn () => $q->quote($p, $ids));
        }$v->update(['active' => false]);
        $this->reject(fn () => $q->quote($p, [$v->id]));
    }

    public function test_option_ids_are_canonical_for_idempotency(): void
    {
        $p = $this->product();
        $v = $this->option($p);
        $u = $this->user();
        $k = (string) Str::uuid();
        $a = app(Checkout::class)->create($u, $p->id, 1, $k, null, [(string) $v->id]);
        $b = app(Checkout::class)->create($u, $p->id, 1, $k, null, [$v->id]);
        $this->assertSame($a->id, $b->id);
    }

    public function test_optional_option_can_be_omitted(): void
    {
        $p = $this->product();
        $p->options()->create(['name' => 'Backup', 'required' => false]);
        $this->assertSame(1000, app(Pricing::class)->quote($p, [])['recurring_minor']);
    }

    public function test_per_customer_limit_counts_pending_and_active_services(): void
    {
        $p = $this->product(['max_per_user' => 1]);
        $u = $this->user();
        $this->invoice($u, $p);
        $this->reject(fn () => $this->invoice($u, $p));
        $this->assertSame(9, $p->fresh()->stock);
        $this->invoice($this->user(), $p);
        $this->assertDatabaseCount('services', 2);
    }

    public function test_allow_quantity_cannot_be_bypassed_by_multiple_configurations(): void
    {
        $p = $this->product(['allow_quantity' => false]);
        $this->reject(fn () => app(Checkout::class)->cart($this->user(), [['product_id' => $p->id, 'quantity' => 1], ['product_id' => $p->id, 'quantity' => 1]], (string) Str::uuid()));
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_fixed_coupon_is_capped_at_invoice_total(): void
    {
        $p = $this->product();
        Coupon::create(['code' => 'FIXED', 'kind' => 'fixed', 'fixed_minor' => 99999, 'percent' => 0]);
        $i = app(Checkout::class)->create($this->user(), $p->id, 1, (string) Str::uuid(), 'FIXED');
        $this->assertSame(0, $i->total_minor);
        $this->assertSame('paid', $i->status);
        $this->assertSame('consumed', CouponRedemption::sole()->status);
    }

    public function test_coupon_per_customer_limit_is_enforced(): void
    {
        $p = $this->product();
        $u = $this->user();
        Coupon::create(['code' => 'ONCE', 'percent' => 10, 'per_user_limit' => 1]);
        app(Checkout::class)->create($u, $p->id, 1, (string) Str::uuid(), 'ONCE');
        $this->reject(fn () => app(Checkout::class)->create($u, $p->id, 1, (string) Str::uuid(), 'ONCE'));
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_expiration_releases_stock_and_coupon_exactly_once(): void
    {
        $p = $this->product();
        $c = Coupon::create(['code' => 'HOLD', 'percent' => 10, 'max_uses' => 1]);
        $i = app(Checkout::class)->create($this->user(), $p->id, 2, (string) Str::uuid(), 'HOLD');
        $i->update(['expires_at' => now()->subMinute()]);
        $this->assertSame(1, app(OrderLifecycle::class)->expire());
        $this->assertSame(0, app(OrderLifecycle::class)->expire());
        $this->assertSame(10, $p->fresh()->stock);
        $this->assertSame(0, $c->fresh()->uses);
        $this->assertSame('cancelled', $i->fresh()->status);
        $this->assertSame('released', StockReservation::sole()->status);
        $this->assertSame('released', CouponRedemption::sole()->status);
    }

    public function test_paid_stock_is_consumed_and_never_released_by_expiry(): void
    {
        $p = $this->product();
        $i = $this->invoice(null, $p);
        app(Billing::class)->settle($i->id, 'test', 'paid', $i->total_minor, 'BRL');
        $i->update(['expires_at' => now()->subMinute()]);
        $this->assertSame(0, app(OrderLifecycle::class)->expire());
        $this->assertSame(9, $p->fresh()->stock);
        $this->assertSame('consumed', StockReservation::sole()->status);
        $this->reject(fn () => app(OrderLifecycle::class)->cancel($i->id, null, 'bad'));
    }

    public function test_expired_invoice_rejects_payment_even_before_cron(): void
    {
        $i = $this->invoice();
        $i->update(['expires_at' => now()->subMinute()]);
        $this->reject(fn () => app(Billing::class)->settle($i->id, 'test', 'late', $i->total_minor, 'BRL'));
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_customer_can_cancel_only_own_unpaid_order(): void
    {
        $u = $this->user();
        $i = $this->invoice($u);
        $this->actingAs($this->user())->post('/painel/faturas/'.$i->id.'/cancelar')->assertNotFound();
        $this->actingAs($u)->post('/painel/faturas/'.$i->id.'/cancelar')->assertRedirect();
        $this->assertSame('cancelled', $i->fresh()->status);
        $this->assertSame('cancelled', $i->services->first()->fresh()->status);
    }

    public function test_cart_is_persistent_and_cannot_be_mutated_across_accounts(): void
    {
        $u = $this->user();
        $p = $this->product();
        $this->actingAs($u)->post('/painel/carrinho', ['product_id' => $p->id, 'quantity' => 1])->assertRedirect(route('cart.index'));
        $item = CartItem::sole();
        $this->actingAs($this->user())->post('/painel/carrinho/'.$item->id.'/remover')->assertNotFound();
        $this->post('/painel/carrinho/'.$item->id.'/quantidade', ['quantity' => 10])->assertNotFound();
        $this->assertSame(1, $item->fresh()->quantity);
    }

    public function test_http_cart_checkout_replay_survives_session_marker_loss(): void
    {
        $u = $this->user();
        $p = $this->product();
        $this->actingAs($u)->post('/painel/carrinho', ['product_id' => $p->id, 'quantity' => 1]);
        $key = (string) Str::uuid();
        $first = $this->post('/painel/carrinho/finalizar', ['request_key' => $key]);
        $first->assertRedirect();
        $this->assertSame('cart', Order::sole()->source);
        $second = $this->post('/painel/carrinho/finalizar', ['request_key' => $key]);
        $second->assertRedirect($first->headers->get('Location'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('cart_items', 0);
        $this->post('/painel/carrinho/finalizar', ['request_key' => $key, 'coupon' => 'DIFFERENT'])->assertSessionHasErrors();
    }

    public function test_client_cannot_inject_staff_role_during_signup(): void
    {
        $role = StaffRole::create(['name' => 'Manager', 'permissions' => ['billing.manage']]);
        $this->post('/registrar', ['name' => 'Client', 'email' => 'escalate@example.test', 'password' => 'RegistrationPassword123!', 'password_confirmation' => 'RegistrationPassword123!', 'terms' => 1, 'staff_role_id' => $role->id])->assertRedirect();
        $this->assertNull(User::where('email', 'escalate@example.test')->sole()->staff_role_id);
    }

    public function test_read_only_billing_role_cannot_confirm_payments_or_view_other_modules(): void
    {
        $u = $this->staff(['billing.view']);
        $i = $this->invoice();
        $this->actingAs($u)->get('/admin/faturas')->assertOk()->assertDontSee('Confirmar recebimento');
        $this->post('/admin/faturas/'.$i->id.'/confirmar', ['note' => 'Unauthorized'])->assertForbidden();
        $this->get('/admin/integracoes')->assertForbidden();
        $this->get('/admin/conciliacao')->assertOk();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_support_operator_can_reply_but_not_manage_money(): void
    {
        $u = $this->staff(['support.view', 'support.manage']);
        $t = $this->user()->tickets()->create(['subject' => 'Help', 'body' => 'Message']);
        $this->actingAs($u)->get('/admin/suporte')->assertOk();
        $this->post('/admin/suporte/'.$t->id, ['body' => 'Resposta', 'status' => 'answered'])->assertRedirect();
        $this->assertSame('answered', $t->fresh()->status);
        $this->get('/admin/faturas')->assertForbidden();
    }

    public function test_delegated_manager_cannot_grant_permissions_they_do_not_have(): void
    {
        $u = $this->staff(['team.manage', 'support.view']);
        $this->actingAs($u)->post('/admin/equipe/funcoes', ['name' => 'Escalate', 'permissions' => ['billing.manage']])->assertForbidden();
        $strong = StaffRole::create(['name' => 'Finance', 'permissions' => ['billing.manage']]);
        $this->post('/admin/equipe/usuarios/'.$this->user()->id, ['staff_role_id' => $strong->id])->assertForbidden();
    }

    public function test_team_manager_cannot_remove_more_powerful_role_or_change_super_admin(): void
    {
        $manager = $this->staff(['team.manage']);
        $strong = $this->staff(['billing.manage']);
        $admin = $this->user(true);
        $this->actingAs($manager)->post('/admin/equipe/usuarios/'.$strong->id, ['staff_role_id' => null])->assertForbidden();
        $this->actingAs($admin)->post('/admin/equipe/usuarios/'.$admin->id, ['staff_role_id' => null])->assertForbidden();
        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_super_admin_can_assign_and_revoke_staff_role(): void
    {
        $admin = $this->user(true);
        $u = $this->user();
        $role = StaffRole::create(['name' => 'Support', 'permissions' => ['support.view']]);
        $this->actingAs($admin)->post('/admin/equipe/usuarios/'.$u->id, ['staff_role_id' => $role->id])->assertRedirect();
        $this->assertSame($role->id, $u->fresh()->staff_role_id);
        $this->post('/admin/equipe/usuarios/'.$u->id, ['staff_role_id' => null])->assertRedirect();
        $this->assertNull($u->fresh()->staff_role_id);
    }

    public function test_published_knowledge_is_searchable_but_drafts_are_private(): void
    {
        $u = $this->user(true);
        Article::create(['title' => 'Configurar DNS', 'slug' => 'dns', 'body' => 'Ajuda DNS', 'category' => 'Domínios', 'published' => true, 'author_id' => $u->id]);
        Article::create(['title' => 'Segredo draft', 'slug' => 'draft', 'body' => 'Private', 'category' => 'Interno', 'published' => false, 'author_id' => $u->id]);
        $this->get('/conhecimento?q=DNS')->assertOk()->assertSee('Configurar DNS')->assertDontSee('Segredo draft');
        $this->get('/conhecimento/dns')->assertOk();
        $this->get('/conhecimento/draft')->assertNotFound();
    }

    public function test_knowledge_html_is_escaped(): void
    {
        Article::create(['title' => 'Safe', 'slug' => 'safe', 'body' => '<script>window.bad=1</script>', 'category' => 'Geral', 'published' => true, 'author_id' => $this->user(true)->id]);
        $this->get('/conhecimento/safe')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>window.bad=1</script>', false);
    }

    public function test_knowledge_read_only_role_cannot_publish(): void
    {
        $u = $this->staff(['knowledge.view']);
        $this->actingAs($u)->get('/admin/conhecimento')->assertOk()->assertDontSee('Salvar artigo');
        $this->post('/admin/conhecimento', ['title' => 'Bad', 'slug' => 'bad', 'body' => 'bad', 'category' => 'Geral', 'published' => 1])->assertForbidden();
        $this->assertDatabaseCount('articles', 0);
    }

    public function test_api_token_is_hashed_scoped_and_displayed_once(): void
    {
        $u = $this->user();
        $this->actingAs($u)->from('/painel/api')->post('/painel/api', ['name' => 'Script', 'password' => 'password', 'days' => 30, 'scopes' => ['services:read']])->assertRedirect()->assertSessionHas('api_token');
        $raw = session('api_token');
        $token = ApiToken::sole();
        $this->assertNotSame($raw, $token->token_hash);
        $this->assertSame(hash('sha256', $raw), $token->token_hash);
        $this->assertArrayNotHasKey('token_hash', $token->toArray());
        $this->get('/painel/api')->assertSee($raw);
        $this->get('/painel/api')->assertDontSee($raw);
    }

    public function test_api_token_creation_requires_password_and_permitted_scope(): void
    {
        $u = $this->user();
        $this->actingAs($u)->post('/painel/api', ['name' => 'x', 'password' => 'bad', 'days' => 30, 'scopes' => ['services:read']])->assertSessionHasErrors('password');
        $this->post('/painel/api', ['name' => 'x', 'password' => 'password', 'days' => 30, 'scopes' => ['admin:all']])->assertSessionHasErrors();
        $this->assertDatabaseCount('api_tokens', 0);
    }

    public function test_api_lists_only_owners_data_and_enforces_scope(): void
    {
        $u = $this->user();
        $own = $this->invoice($u);
        $other = $this->invoice();
        $raw = $this->token($u, ['services:read']);
        $this->withToken($raw)->getJson('/api/v1/services')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->services->first()->id);
        $this->getJson('/api/v1/invoices')->assertForbidden();
        $this->assertNotSame($other->user_id, $u->id);
    }

    public function test_api_rejects_missing_wrong_case_expired_unverified_or_password_changed_token(): void
    {
        $u = $this->user();
        $raw = $this->token($u, ['services:read']);
        $this->getJson('/api/v1/services')->assertUnauthorized();
        $this->withToken(strtoupper($raw))->getJson('/api/v1/services')->assertUnauthorized();
        ApiToken::sole()->update(['expires_at' => now()->subMinute()]);
        $this->withToken($raw)->getJson('/api/v1/services')->assertUnauthorized();
        ApiToken::sole()->update(['expires_at' => now()->addDay()]);
        $u->forceFill(['email_verified_at' => null])->save();
        $this->getJson('/api/v1/services')->assertUnauthorized();
        $u->forceFill(['email_verified_at' => now(), 'password' => 'NewPassword123!'])->save();
        $this->getJson('/api/v1/services')->assertUnauthorized();
    }

    public function test_api_ticket_ignores_forged_owner(): void
    {
        $u = $this->user();
        $other = $this->user();
        $raw = $this->token($u, ['tickets:write']);
        $this->withToken($raw)->postJson('/api/v1/tickets', ['subject' => 'Via API', 'body' => 'Message', 'department' => 'support', 'user_id' => $other->id])->assertCreated();
        $this->assertDatabaseHas('tickets', ['user_id' => $u->id, 'subject' => 'Via API']);
    }

    public function test_token_revocation_is_owner_only_and_immediate(): void
    {
        $u = $this->user();
        $raw = $this->token($u, ['services:read']);
        $token = ApiToken::sole();
        $this->actingAs($this->user())->post('/painel/api/'.$token->id.'/revogar')->assertNotFound();
        $this->actingAs($u)->post('/painel/api/'.$token->id.'/revogar')->assertRedirect();
        $this->withToken($raw)->getJson('/api/v1/services')->assertUnauthorized();
    }

    public function test_reminders_are_deduplicated_and_do_not_send_after_payment(): void
    {
        $u = $this->user();
        $i = $u->invoices()->create(['type' => 'renewal', 'total_minor' => 500, 'snapshot' => [], 'due_date' => today()->subDay(), 'created_at' => now()->subDays(2)]);
        $this->assertSame(1, app(Reminders::class)->run());
        $this->assertSame(0, app(Reminders::class)->run());
        Notification::assertSentTo($u, InvoiceReminder::class);
        $notice = new InvoiceReminder($i->id, 'overdue_1');
        $this->assertTrue($notice->shouldSend($u, 'mail'));
        app(Billing::class)->settle($i->id, 'test', 'reminder-paid', 500, 'BRL');
        $this->assertFalse($notice->shouldSend($u, 'mail'));
        $this->assertDatabaseCount('invoice_reminders', 1);
    }

    public function test_reminders_do_not_flood_a_recent_invoice_or_catch_up_all_stages(): void
    {
        $i = $this->invoice();
        $this->assertSame(0, app(Reminders::class)->run());
        $i->update(['created_at' => now()->subDays(10), 'due_date' => today()->subDays(8), 'expires_at' => null]);
        $this->assertSame(1, app(Reminders::class)->run());
        $this->assertDatabaseHas('invoice_reminders', ['stage' => 'overdue_7']);
        $this->assertDatabaseCount('invoice_reminders', 1);
    }

    public function test_financial_reports_do_not_double_count_wallet_movements(): void
    {
        $u = $this->user();
        $deposit = $u->invoices()->create(['type' => 'deposit', 'total_minor' => 5000, 'snapshot' => [], 'due_date' => today()]);
        app(Billing::class)->settle($deposit->id, 'manual', 'cash', 5000, 'BRL');
        $i = $this->invoice($u);
        app(Billing::class)->payWithWallet($u, $i->id);
        $this->actingAs($this->staff(['reports.view']))->get('/admin/relatorios')->assertOk()->assertViewHas('external', 5000)->assertViewHas('wallet', 1100)->assertViewHas('balances', 3900);
    }

    public function test_csv_export_is_guarded_and_uses_integer_minor_units(): void
    {
        $i = $this->invoice();
        $this->actingAs($this->user())->get('/admin/relatorios/faturas.csv')->assertForbidden();
        $r = $this->actingAs($this->staff(['reports.view']))->get('/admin/relatorios/faturas.csv');
        $r->assertOk()->assertDownload();
        $csv = $r->streamedContent();
        $this->assertStringContainsString('total_centavos', $csv);
        $this->assertStringContainsString('1100', $csv);
        $this->assertDatabaseHas('audit_events', ['event' => 'report.exported']);
    }

    public function test_authenticated_mismatched_payment_is_preserved_for_review_once(): void
    {
        $i = $this->invoice();
        config(['lagos.payments.stripe.enabled' => true, 'lagos.payments.stripe.webhook_secret' => 'secret', 'lagos.payments.live' => false]);
        $data = ['type' => 'checkout.session.completed', 'livemode' => false, 'data' => ['object' => ['id' => 'cs_late', 'payment_intent' => 'pi_late', 'payment_status' => 'paid', 'metadata' => ['invoice_id' => $i->id], 'amount_total' => 1, 'currency' => 'brl']]];
        $body = json_encode($data);
        $sig = 't='.time().',v1='.hash_hmac('sha256', time().'.'.$body, 'secret');
        for ($n = 0; $n < 2; $n++) {
            $this->call('POST', '/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $sig], $body)->assertStatus(422);
        }$this->assertDatabaseCount('payment_reviews', 1);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('unpaid', $i->fresh()->status);
    }

    public function test_unsigned_events_cannot_spam_payment_review_records(): void
    {
        config(['lagos.payments.stripe.enabled' => true, 'lagos.payments.stripe.webhook_secret' => 'secret']);
        $this->postJson('/webhooks/stripe', ['type' => 'checkout.session.completed'])->assertForbidden();
        $this->assertDatabaseCount('payment_reviews', 0);
    }

    public function test_closing_review_does_not_forge_payment_or_refund(): void
    {
        $i = $this->invoice();
        $review = PaymentReview::create(['fingerprint' => hash('sha256', 'x'), 'invoice_id' => $i->id, 'provider_invoice_ref' => (string) $i->id, 'gateway' => 'stripe', 'reference' => 'pi_x', 'amount_minor' => 1, 'currency' => 'BRL', 'reason' => 'Mismatch']);
        $this->actingAs($this->staff(['billing.view']))->post('/admin/conciliacao/'.$review->id, ['resolution' => 'Conferido manualmente'])->assertForbidden();
        $this->actingAs($this->staff(['billing.manage']))->post('/admin/conciliacao/'.$review->id, ['resolution' => 'Conferido no provedor; devolução externa registrada.'])->assertRedirect();
        $this->assertSame('resolved', $review->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('unpaid', $i->fresh()->status);
    }

    public function test_old_checkout_replay_does_not_delete_a_new_cart(): void
    {
        $u = $this->user();
        $p = $this->product();
        $this->actingAs($u)->post('/painel/carrinho', ['product_id' => $p->id, 'quantity' => 1]);
        $key = (string) Str::uuid();
        $this->post('/painel/carrinho/finalizar', ['request_key' => $key])->assertRedirect();
        $this->post('/painel/carrinho', ['product_id' => $p->id, 'quantity' => 2]);
        $this->post('/painel/carrinho/finalizar', ['request_key' => $key])->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(2, CartItem::sole()->quantity);
    }

    public function test_production_staff_must_enroll_two_factor_before_admin_access(): void
    {
        $u = $this->staff(['support.view']);
        AdminPreference::ensure();
        AdminPreference::find(1)->update(['require_two_factor' => true]);
        $this->app['env'] = 'production';
        $this->actingAs($u)->get('/admin/suporte')->assertRedirect(route('profile'));
        $this->app['env'] = 'testing';
    }

    public function test_enabling_two_factor_invalidates_old_api_tokens(): void
    {
        $u = $this->user();
        $raw = $this->token($u, ['services:read']);
        $u->forceFill(['totp_secret' => Totp::secret()])->save();
        $this->withToken($raw)->getJson('/api/v1/services')->assertUnauthorized();
    }

    public function test_two_factor_account_cannot_create_api_token_without_a_fresh_code(): void
    {
        $u = $this->user();
        $u->forceFill(['totp_secret' => Totp::secret()])->save();
        $this->actingAs($u)->post('/painel/api', ['name' => 'blocked', 'password' => 'password', 'days' => 30, 'scopes' => ['services:read']])->assertSessionHasErrors('code');
        $this->assertDatabaseCount('api_tokens', 0);
    }

    public function test_additional_cycles_have_explicit_calendar_rules(): void
    {
        $d = CarbonImmutable::parse('2028-02-29');
        $this->assertSame('2028-03-01', Cycle::next($d, 'daily')->toDateString());
        $this->assertSame('2028-03-07', Cycle::next($d, 'weekly')->toDateString());
        $this->assertSame('2030-02-28', Cycle::next($d, 'biennial')->toDateString());
        $this->assertSame('2031-02-28', Cycle::next($d, 'triennial')->toDateString());
    }

    public function test_expired_order_cannot_open_a_gateway_checkout(): void
    {
        $u = $this->user();
        $i = $this->invoice($u);
        $i->update(['expires_at' => now()->subMinute()]);
        config(['lagos.payments.stripe.enabled' => true, 'lagos.payments.stripe.secret' => 'sk_test', 'lagos.payments.stripe.webhook_secret' => 'whsec_test']);
        Http::fake();
        $this->actingAs($u)->post('/painel/faturas/'.$i->id.'/pagar', ['gateway' => 'stripe'])->assertStatus(422);
        Http::assertNothingSent();
        $this->get('/painel/faturas/'.$i->id)->assertOk()->assertSee('Reserva expirada')->assertDontSee('Usar saldo da conta');
    }

    public function test_rate_limit_buckets_are_separate_between_unrelated_web_actions(): void
    {
        $u = $this->user();
        $this->actingAs($u);
        for ($n = 0; $n < 5; $n++) {
            $this->post('/painel/suporte', ['subject' => 'Ticket '.$n, 'body' => 'Mensagem de teste', 'department' => 'support'])->assertRedirect();
        }
        for ($n = 0; $n < 5; $n++) {
            $this->post('/painel/api', ['name' => 'Permitted '.$n, 'password' => 'password', 'days' => 1, 'scopes' => ['services:read']])->assertRedirect()->assertSessionHas('api_token');
        }
        $this->post('/painel/api', ['name' => 'Limited', 'password' => 'password', 'days' => 1, 'scopes' => ['services:read']])->assertStatus(429);
        $this->assertDatabaseCount('api_tokens', 5);
    }
}
