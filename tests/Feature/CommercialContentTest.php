<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Bulletin;
use App\Models\DownloadAsset;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Service;
use App\Models\StaffRole;
use App\Models\User;
use App\Services\Billing;
use App\Services\Downloads;
use App\Services\Quotes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CommercialContentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();
        $this->client = User::factory()->create();
    }

    private function operator(array $permissions): User
    {
        $u = User::factory()->create();
        $role = StaffRole::create(['name' => 'Role '.$u->id, 'permissions' => $permissions]);
        $u->forceFill(['staff_role_id' => $role->id])->save();

        return $u;
    }

    private function quoteData(): array
    {
        return ['user_id' => $this->client->id, 'title' => 'Proposta privada', 'terms' => 'Entrega manual em três dias após pagamento.', 'items' => [['name' => 'Configuração avulsa', 'quantity' => 2, 'unit_minor' => 999]], 'valid_until' => today()->addDays(10)->format('Y-m-d'), 'payment_days' => 7];
    }

    private function quote(bool $sent = true): Quote
    {
        $q = app(Quotes::class)->save($this->admin, $this->quoteData());

        return $sent ? app(Quotes::class)->transition($this->admin, $q, 'send', $q->version) : $q;
    }

    private function decision(Quote $q, string $decision = 'accept'): array
    {
        return ['decision' => $decision, 'version' => $q->version, 'ack' => 1];
    }

    private function bulletin(array $extra = []): Bulletin
    {
        return Bulletin::create(array_replace(['author_id' => $this->admin->id, 'title' => 'Incidente público', 'body' => 'Texto seguro', 'kind' => 'incident', 'severity' => 'outage', 'state' => 'open', 'published' => true, 'published_at' => now()], $extra));
    }

    private function product(): Product
    {
        return Product::create(['name' => 'Plano com arquivos', 'slug' => 'download-product', 'price_minor' => 100, 'cycle' => 'monthly']);
    }

    private function asset(?Product $p = null, array $extra = []): DownloadAsset
    {
        return app(Downloads::class)->create($this->admin, array_replace(['title' => 'Arquivo reservado', 'description' => 'Material de apoio', 'product_id' => $p?->id, 'active' => true], $extra), UploadedFile::fake()->createWithContent('guia.txt', 'conteudo-confidencial-123'));
    }

    private function service(Product $p, User $u, string $state = 'active'): Service
    {
        return Service::create(['user_id' => $u->id, 'product_id' => $p->id, 'name' => 'Serviço', 'cycle' => 'monthly', 'price_minor' => 100, 'status' => $state]);
    }

    public function test_quote_acceptance_generates_one_invoice_with_immutable_values(): void
    {
        $q = $this->quote();
        $data = $this->decision($q) + ['total_minor' => 1, 'items' => []];
        $this->actingAs($this->client)->post('/painel/orcamentos/'.$q->id.'/decisao', $data)->assertRedirect();
        $this->post('/painel/orcamentos/'.$q->id.'/decisao', $data)->assertRedirect();
        $i = Invoice::sole();
        $this->assertSame(1998, $i->total_minor);
        $this->assertSame('quote', $i->type);
        $this->assertSame(2, $i->snapshot[0]['quantity']);
        $this->assertSame($i->id, $q->fresh()->invoice_id);
        $this->assertSame(0, Service::count());
        $this->assertSame(today()->addDays(7)->format('Y-m-d'), $i->due_date->format('Y-m-d'));
    }

    public function test_accepted_quote_invoice_uses_existing_wallet_and_pdf_flow(): void
    {
        $q = $this->quote();
        $q = app(Quotes::class)->decide($this->client, $q, 'accept', $q->version);
        app(Billing::class)->wallet($this->client->id, 1998, 'test-credit', 'test');
        $i = app(Billing::class)->payWithWallet($this->client, $q->invoice_id);
        $this->assertSame('paid', $i->status);
        $this->assertSame(0, $this->client->fresh()->balance_minor);
        $this->actingAs($this->client)->get('/painel/faturas/'.$i->id)->assertOk()->assertSee('A execução dos itens segue as condições do orçamento');
        $this->get('/painel/faturas/'.$i->id.'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_quote_owner_isolation(): void
    {
        $q = $this->quote();
        $this->actingAs(User::factory()->create())->get('/painel/orcamentos/'.$q->id)->assertNotFound();
        $this->post('/painel/orcamentos/'.$q->id.'/decisao', $this->decision($q))->assertNotFound();
        $this->get('/painel/orcamentos')->assertDontSee('Proposta privada');
        $this->assertSame(0, Invoice::count());
    }

    public function test_draft_and_withdrawn_unsent_quotes_remain_private(): void
    {
        $q = $this->quote(false);
        $this->actingAs($this->client)->get('/painel/orcamentos/'.$q->id)->assertNotFound();
        app(Quotes::class)->transition($this->admin, $q, 'withdraw', $q->version);
        $this->get('/painel/orcamentos/'.$q->id)->assertNotFound();
        $this->get('/painel/orcamentos')->assertDontSee('Proposta privada');
    }

    public function test_expired_quote_cannot_be_accepted(): void
    {
        $q = $this->quote();
        $this->travel(11)->days();
        $this->actingAs($this->client)->post('/painel/orcamentos/'.$q->id.'/decisao', $this->decision($q))->assertConflict();
        $this->assertSame(0, Invoice::count());
    }

    public function test_withdrawal_prevents_acceptance(): void
    {
        $q = $this->quote();
        app(Quotes::class)->transition($this->admin, $q, 'withdraw', $q->version);
        $this->actingAs($this->client)->post('/painel/orcamentos/'.$q->id.'/decisao', $this->decision($q))->assertConflict();
        $this->assertSame(0, Invoice::count());
    }

    public function test_quote_decline_is_idempotent_and_does_not_invoice(): void
    {
        $q = $this->quote();
        $this->actingAs($this->client)->post('/painel/orcamentos/'.$q->id.'/decisao', $this->decision($q, 'decline'))->assertRedirect();
        $this->post('/painel/orcamentos/'.$q->id.'/decisao', $this->decision($q, 'decline'))->assertRedirect();
        $this->post('/painel/orcamentos/'.$q->id.'/decisao', $this->decision($q))->assertConflict();
        $this->assertSame('declined', $q->fresh()->status);
        $this->assertSame(0, Invoice::count());
    }

    public function test_stale_quote_version_rejected(): void
    {
        $q = $this->quote();
        $this->actingAs($this->client)->post('/painel/orcamentos/'.$q->id.'/decisao', array_replace($this->decision($q), ['version' => 1]))->assertConflict();
    }

    public function test_explicit_acceptance_acknowledgement_required(): void
    {
        $q = $this->quote();
        $data = $this->decision($q);
        unset($data['ack']);
        $this->actingAs($this->client)->post('/painel/orcamentos/'.$q->id.'/decisao', $data)->assertSessionHasErrors('ack');
        $this->assertSame(0, Invoice::count());
    }

    public function test_sent_quote_cannot_be_repriced(): void
    {
        $q = $this->quote();
        $data = $this->quoteData() + ['version' => $q->version];
        $this->expectException(HttpException::class);
        app(Quotes::class)->save($this->admin, $data, $q);
    }

    public function test_admin_quote_workflow_and_stale_edit(): void
    {
        $this->actingAs($this->admin)->get('/admin/orcamentos/novo')->assertOk();
        $data = $this->quoteData();
        $data['items_json'] = json_encode($data['items']);
        unset($data['items']);
        $this->post('/admin/orcamentos', $data)->assertSessionHasNoErrors()->assertRedirect();
        $q = Quote::sole();
        $this->get('/admin/orcamentos/'.$q->id.'/editar')->assertOk();
        $this->post('/admin/orcamentos/'.$q->id, $data + ['version' => 1])->assertSessionHasNoErrors();
        $this->post('/admin/orcamentos/'.$q->id, $data + ['version' => 1])->assertConflict();
        $this->post('/admin/orcamentos/'.$q->id.'/estado', ['action' => 'send', 'version' => 2])->assertSessionHasNoErrors();
        $this->get('/admin/orcamentos')->assertOk()->assertSee('Proposta privada');
        $this->actingAs($this->client)->get('/painel/orcamentos/'.$q->id)->assertOk()->assertSee('Aceitar e gerar fatura');
    }

    public static function invalidItems(): array
    {
        return [[[]], [[['name' => 'X', 'quantity' => 0, 'unit_minor' => 100]]], [[['name' => 'X', 'quantity' => 1, 'unit_minor' => -1]]], [[['name' => 'X', 'quantity' => 1.5, 'unit_minor' => 100]]], [[['name' => 'X', 'quantity' => 1000, 'unit_minor' => 100000000]]]];
    }

    #[DataProvider('invalidItems')]
    public function test_quote_invalid_or_excessive_values_are_rejected(array $items): void
    {
        $data = $this->quoteData();
        $data['items'] = $items;
        $this->expectException(ValidationException::class);
        app(Quotes::class)->save($this->admin, $data);
    }

    public function test_quote_read_permission_does_not_allow_mutation(): void
    {
        $q = $this->quote(false);
        $this->actingAs($this->operator(['billing.view']))->get('/admin/orcamentos/'.$q->id)->assertOk()->assertDontSee('Disponibilizar ao cliente');
        $this->post('/admin/orcamentos/'.$q->id.'/estado', ['action' => 'send', 'version' => 1])->assertForbidden();
    }

    public function test_quote_html_is_escaped(): void
    {
        $data = $this->quoteData();
        $data['terms'] = '<script>alert(1)</script>';
        $q = app(Quotes::class)->save($this->admin, $data);
        $q = app(Quotes::class)->transition($this->admin, $q, 'send', 1);
        $this->actingAs($this->client)->get('/painel/orcamentos/'.$q->id)->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_bulletin_public_visibility_and_scheduled_publication(): void
    {
        $b = $this->bulletin();
        $draft = $this->bulletin(['title' => 'Draft secret', 'published' => false]);
        $future = $this->bulletin(['title' => 'Future secret', 'published_at' => now()->addDay()]);
        $this->get('/avisos')->assertOk()->assertSee('Incidente público')->assertDontSee('Draft secret')->assertDontSee('Future secret');
        $this->get('/avisos/'.$draft->id)->assertNotFound();
        $this->get('/avisos/'.$future->id)->assertNotFound();
        $this->travel(2)->days();
        $this->get('/avisos/'.$future->id)->assertOk();
    }

    public function test_bulletin_update_history_and_resolution(): void
    {
        $b = $this->bulletin();
        $this->actingAs($this->admin)->post('/admin/avisos/'.$b->id, ['version' => 1, 'body' => 'Problema identificado', 'state' => 'monitoring', 'published' => 1])->assertRedirect();
        $this->post('/admin/avisos/'.$b->id, ['version' => 2, 'body' => 'Serviço recuperado', 'state' => 'resolved', 'published' => 1])->assertRedirect();
        $this->get('/avisos/'.$b->id)->assertOk()->assertSee('Problema identificado')->assertSee('Serviço recuperado');
        $this->assertSame(2, $b->updates()->count());
        $this->get('/avisos')->assertSee('Nenhum incidente com impacto informado');
    }

    public function test_bulletin_stale_write_is_rejected_without_duplicate_history(): void
    {
        $b = $this->bulletin();
        $this->actingAs($this->admin)->post('/admin/avisos/'.$b->id, ['version' => 1, 'body' => 'Atualização', 'state' => 'monitoring', 'published' => 1])->assertRedirect();
        $this->post('/admin/avisos/'.$b->id, ['version' => 1, 'body' => 'Duplicada', 'state' => 'resolved', 'published' => 1])->assertConflict();
        $this->assertSame(1, $b->updates()->count());
    }

    public function test_bulletin_can_be_hidden_without_erasing_history(): void
    {
        $b = $this->bulletin();
        $this->actingAs($this->admin)->post('/admin/avisos/'.$b->id, ['version' => 1, 'body' => 'Retirada por correção', 'state' => 'open'])->assertRedirect();
        $this->get('/avisos/'.$b->id)->assertNotFound();
        $this->get('/admin/avisos/'.$b->id)->assertOk()->assertSee('Retirada por correção');
    }

    public function test_bulletin_create_and_escaping(): void
    {
        $this->actingAs($this->admin)->post('/admin/avisos', ['title' => '<b>Aviso</b>', 'body' => '<script>alert(1)</script>', 'kind' => 'announcement', 'severity' => 'outage', 'published' => 1])->assertRedirect();
        $b = Bulletin::sole();
        $this->assertSame('information', $b->severity);
        $this->get('/avisos/'.$b->id)->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
        $this->get('/admin/avisos')->assertOk();
    }

    public function test_bulletin_read_only_role_cannot_publish(): void
    {
        $b = $this->bulletin();
        $this->actingAs($this->operator(['bulletins.view']))->get('/admin/avisos')->assertOk()->assertDontSee('Nova publicação');
        $this->post('/admin/avisos/'.$b->id, ['version' => 1, 'state' => 'resolved', 'body' => 'Not allowed'])->assertForbidden();
    }

    public function test_download_encrypted_at_rest_and_private_response(): void
    {
        $a = $this->asset();
        $bytes = Storage::disk('local')->get($a->path);
        $this->assertStringNotContainsString('conteudo-confidencial-123', $bytes);
        $this->assertArrayNotHasKey('path', $a->toArray());
        $this->actingAs($this->client)->get('/painel/downloads')->assertOk()->assertSee('Arquivo reservado')->assertDontSee($a->path);
        $this->get('/painel/downloads/'.$a->id.'/arquivo')->assertOk()->assertContent('conteudo-confidencial-123')->assertHeader('Content-Type', 'application/octet-stream')->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
    }

    public function test_download_guests_and_unverified_accounts_cannot_read(): void
    {
        $a = $this->asset();
        $this->get('/painel/downloads/'.$a->id.'/arquivo')->assertRedirect('/entrar');
        $u = User::factory()->unverified()->create();
        $this->actingAs($u)->get('/painel/downloads/'.$a->id.'/arquivo')->assertRedirect('/verificar-email');
    }

    public function test_product_download_requires_own_active_service(): void
    {
        $p = $this->product();
        $a = $this->asset($p);
        $this->service($p, User::factory()->create());
        $this->actingAs($this->client)->get('/painel/downloads')->assertDontSee('Arquivo reservado');
        $this->get('/painel/downloads/'.$a->id.'/arquivo')->assertNotFound();
        $s = $this->service($p, $this->client);
        $this->get('/painel/downloads/'.$a->id.'/arquivo')->assertOk();
        $s->update(['status' => 'suspended']);
        $this->get('/painel/downloads/'.$a->id.'/arquivo')->assertNotFound();
    }

    public static function inactiveStates(): array
    {
        return [['pending'], ['suspended'], ['cancelled']];
    }

    #[DataProvider('inactiveStates')]
    public function test_nonactive_service_never_grants_download(string $state): void
    {
        $p = $this->product();
        $a = $this->asset($p);
        $this->service($p, $this->client, $state);
        $this->actingAs($this->client)->get('/painel/downloads/'.$a->id.'/arquivo')->assertNotFound();
    }

    public function test_deactivated_download_revokes_client_access(): void
    {
        $a = $this->asset();
        $this->actingAs($this->admin)->post('/admin/downloads/'.$a->id.'/estado', ['active' => 0])->assertRedirect();
        $this->actingAs($this->client)->get('/painel/downloads/'.$a->id.'/arquivo')->assertNotFound();
        $this->actingAs($this->admin)->get('/admin/downloads/'.$a->id.'/arquivo')->assertOk();
    }

    public function test_corrupted_download_is_not_delivered(): void
    {
        $a = $this->asset();
        Storage::disk('local')->put($a->path, 'bad encrypted file');
        $this->actingAs($this->client)->get('/painel/downloads/'.$a->id.'/arquivo')->assertConflict()->assertDontSee('bad encrypted file');
    }

    public function test_download_integrity_is_checked(): void
    {
        $a = $this->asset();
        $a->update(['sha256' => str_repeat('0', 64)]);
        $this->actingAs($this->client)->get('/painel/downloads/'.$a->id.'/arquivo')->assertConflict();
    }

    public function test_download_path_traversal_is_refused(): void
    {
        $a = $this->asset();
        $a->update(['path' => '../../.env']);
        $this->actingAs($this->client)->get('/painel/downloads/'.$a->id.'/arquivo')->assertNotFound();
    }

    public function test_download_read_only_role_cannot_upload_or_activate(): void
    {
        $a = $this->asset();
        $u = $this->operator(['downloads.view']);
        $this->actingAs($u)->get('/admin/downloads')->assertOk()->assertDontSee('Adicionar arquivo');
        $this->post('/admin/downloads/'.$a->id.'/estado', ['active' => 0])->assertForbidden();
        $this->post('/admin/downloads', [])->assertForbidden();
    }

    public function test_download_upload_http_workflow(): void
    {
        $this->actingAs($this->admin)->post('/admin/downloads', ['title' => 'Guia novo', 'description' => 'Material confiável', 'active' => 1, 'file' => UploadedFile::fake()->createWithContent('guia.txt', 'manual')])->assertSessionHasNoErrors()->assertRedirect();
        $a = DownloadAsset::sole();
        $this->assertTrue($a->active);
        $this->assertSame(6, $a->size);
        $this->get('/admin/downloads')->assertOk()->assertSee('Guia novo');
    }

    public function test_download_executable_extension_rejected(): void
    {
        $this->actingAs($this->admin)->post('/admin/downloads', ['title' => 'Invalid', 'description' => 'Não publicar', 'file' => UploadedFile::fake()->createWithContent('file.php', '<?php echo 1;')])->assertSessionHasErrors('file');
        $this->assertSame(0, DownloadAsset::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_download_oversize_rejected_before_storage(): void
    {
        $this->actingAs($this->admin)->post('/admin/downloads', ['title' => 'Large', 'description' => 'Too big', 'file' => UploadedFile::fake()->create('data.zip', 10241)])->assertSessionHasErrors('file');
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_clients_cannot_access_new_admin_modules(): void
    {
        $this->actingAs($this->client);
        foreach (['/admin/orcamentos', '/admin/avisos', '/admin/downloads'] as $url) {
            $this->get($url)->assertForbidden();
            $this->post($url, [])->assertForbidden();
        }
    }

    public function test_file_is_removed_if_database_audit_fails(): void
    {
        AuditEvent::creating(function () {
            throw new \RuntimeException('Test rollback');
        });
        try {
            $this->asset();
            $this->fail('Expected transaction failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Test rollback', $e->getMessage());
        } finally {
            AuditEvent::flushEventListeners();
        }
        $this->assertSame(0, DownloadAsset::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_empty_download_is_rejected(): void
    {
        $this->actingAs($this->admin)->post('/admin/downloads', ['title' => 'Empty', 'description' => 'Empty file', 'file' => UploadedFile::fake()->createWithContent('file.txt', '')])->assertSessionHasErrors('file');
        $this->assertSame(0, DownloadAsset::count());
    }

    public function test_other_product_does_not_grant_download(): void
    {
        $a = $this->asset($this->product());
        $p = Product::create(['name' => 'Other', 'slug' => 'other', 'price_minor' => 100, 'cycle' => 'monthly']);
        $this->service($p, $this->client);
        $this->actingAs($this->client)->get('/painel/downloads/'.$a->id.'/arquivo')->assertNotFound();
    }

    public static function rollbackData(): array
    {
        return [['quote'], ['bulletin'], ['download']];
    }

    #[DataProvider('rollbackData')]
    public function test_rollback_preserves_commercial_records(string $kind): void
    {
        match ($kind) {
            'quote' => $this->quote(), 'bulletin' => $this->bulletin(),'download' => $this->asset()
        };
        $m = require database_path('migrations/2026_10_03_000014_commercial_content.php');
        $this->expectException(\RuntimeException::class);
        $m->down();
    }

    public function test_accepted_quote_cannot_be_withdrawn_or_edited(): void
    {
        $q = $this->quote();
        $q = app(Quotes::class)->decide($this->client, $q, 'accept', $q->version);
        $this->actingAs($this->admin)->post('/admin/orcamentos/'.$q->id.'/estado', ['action' => 'withdraw', 'version' => $q->version])->assertConflict();
        $this->get('/admin/orcamentos/'.$q->id.'/editar')->assertConflict();
        $this->assertSame(1,Invoice::count());
    }
}
