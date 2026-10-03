<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\CannedReply;
use App\Models\Invoice;
use App\Models\StaffRole;
use App\Models\User;
use App\Services\InvoicePdf;
use App\Services\SupportDesk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DocumentsAndTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function staff(array $permissions): User
    {
        $role = StaffRole::create(['name' => 'Role '.uniqid(), 'permissions' => $permissions]);
        $u = User::factory()->create();
        $u->forceFill(['staff_role_id' => $role->id])->save();

        return $u;
    }

    private function invoice(): Invoice
    {
        return Invoice::create(['user_id' => User::factory()->create()->id, 'total_minor' => 12345, 'status' => 'unpaid', 'due_date' => today()->addDays(3), 'snapshot' => [['name' => 'Instalação <script>bad()</script>', 'quantity' => 2, 'unit_minor' => 6000, 'setup_minor' => 200], ['name' => 'Desconto', 'quantity' => 1, 'unit_minor' => -55]]]);
    }

    private function template(array $extra = []): CannedReply
    {
        return CannedReply::create(array_replace(['title' => 'Saudação', 'body' => 'Olá! Como podemos ajudar?', 'active' => true, 'department' => null], $extra));
    }

    public function test_owner_downloads_real_private_pdf_without_financial_side_effect(): void
    {
        $i = $this->invoice();
        $before = $i->fresh()->getAttributes();
        $r = $this->actingAs($i->user)->get('/painel/faturas/'.$i->id.'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Content-Disposition', 'attachment; filename="fatura-'.$i->id.'.pdf"');
        $this->assertStringStartsWith('%PDF-', $r->getContent());
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->assertSame($before, $i->fresh()->getAttributes());
        $this->assertDatabaseHas('audit_events', ['event' => 'invoice.pdf', 'user_id' => $i->user_id]);
    }

    public function test_other_customer_cannot_download_invoice_even_as_staff_on_customer_route(): void
    {
        $i = $this->invoice();
        $this->actingAs(User::factory()->create())->get('/painel/faturas/'.$i->id.'/pdf')->assertNotFound();
        $this->actingAs($this->staff(['billing.view']))->get('/painel/faturas/'.$i->id.'/pdf')->assertNotFound();
        $this->assertDatabaseMissing('audit_events', ['event' => 'invoice.pdf']);
    }

    public function test_pdf_requires_login_verification_and_billing_permission(): void
    {
        $i = $this->invoice();
        $this->get('/painel/faturas/'.$i->id.'/pdf')->assertRedirect('/entrar');
        $this->actingAs(User::factory()->unverified()->create())->get('/painel/faturas/'.$i->id.'/pdf')->assertRedirect('/verificar-email');
        $this->actingAs($this->staff(['support.view']))->get('/admin/faturas/'.$i->id.'/pdf')->assertForbidden();
        $this->actingAs($this->staff(['billing.view']))->get('/admin/faturas/'.$i->id.'/pdf')->assertOk();
    }

    public function test_renderer_disables_network_scripts_and_file_protocols(): void
    {
        $o = app(InvoicePdf::class)->options();
        $this->assertFalse($o->getIsRemoteEnabled());
        $this->assertFalse($o->getIsPhpEnabled());
        $this->assertFalse($o->getIsJavascriptEnabled());
        $this->assertSame([], $o->getAllowedProtocols());
        $this->assertSame([storage_path('app/private/pdf-runtime')], $o->getChroot());
    }

    public function test_pdf_template_escapes_untrusted_names_and_identifies_non_fiscal_document(): void
    {
        $i = $this->invoice();
        $html = view('pdf.invoice', ['invoice' => $i, 'generatedAt' => now()])->render();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('não é nota fiscal', $html);
        $this->assertStringContainsString('R$ 123,45', $html);
        $this->assertStringContainsString('cadastro atual', $html);
    }

    public function test_template_create_update_disable_and_audit_without_body(): void
    {
        $u = $this->staff(['support.view', 'support.manage']);
        $this->actingAs($u)->post('/admin/suporte/modelos', ['title' => 'Teste', 'body' => 'Texto restrito', 'department' => 'support', 'active' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $t = CannedReply::sole();
        $this->assertSame(1, $t->version);
        $this->post('/admin/suporte/modelos/'.$t->id, ['title' => 'Novo', 'body' => 'Outro texto', 'department' => 'billing', 'version' => 1])->assertSessionHasNoErrors();
        $this->assertFalse($t->fresh()->active);
        $this->assertSame(2, $t->fresh()->version);
        $this->assertStringNotContainsString('Texto restrito', AuditEvent::all()->toJson());
        $this->assertDatabaseHas('audit_events', ['event' => 'support.template.saved', 'user_id' => $u->id]);
    }

    public function test_stale_template_update_is_rejected_without_overwriting(): void
    {
        $t = $this->template();
        $this->actingAs($this->staff(['support.view', 'support.manage']));
        $payload = ['title' => 'Primeira', 'body' => 'Preservar', 'active' => 1, 'version' => 1];
        $this->post('/admin/suporte/modelos/'.$t->id, $payload)->assertRedirect();
        $this->post('/admin/suporte/modelos/'.$t->id, array_replace($payload, ['body' => 'Perder']))->assertStatus(409);
        $this->assertSame('Preservar', $t->fresh()->body);
    }

    public function test_readonly_staff_cannot_create_or_edit_templates(): void
    {
        $t = $this->template();
        $this->actingAs($this->staff(['support.view']));
        $this->get('/admin/suporte/modelos')->assertOk()->assertDontSee('Salvar resposta pronta');
        $this->post('/admin/suporte/modelos', ['title' => 'Bad', 'body' => 'Bad'])->assertForbidden();
        $this->post('/admin/suporte/modelos/'.$t->id, ['title' => 'Bad', 'body' => 'Bad', 'version' => 1])->assertForbidden();
    }

    public function test_customer_and_non_support_staff_cannot_read_library_or_content(): void
    {
        $t = $this->template();
        $owner = User::factory()->create();
        $ticket = app(SupportDesk::class)->open($owner, ['subject' => 'Ajuda', 'body' => 'Texto', 'department' => 'support']);
        foreach ([$owner, $this->staff(['billing.view'])] as $u) {
            $this->actingAs($u)->get('/admin/suporte/modelos')->assertForbidden();
            $this->get('/admin/suporte/'.$ticket->id.'/modelos/'.$t->id)->assertForbidden();
        }
    }

    public function test_content_endpoint_respects_current_active_and_department_without_replying(): void
    {
        $t = $this->template();
        $owner = User::factory()->create();
        $ticket = app(SupportDesk::class)->open($owner, ['subject' => 'Ajuda', 'body' => 'Texto', 'department' => 'support']);
        $this->actingAs($this->staff(['support.view', 'support.manage']));
        $url = '/admin/suporte/'.$ticket->id.'/modelos/'.$t->id;
        $this->getJson($url)->assertOk()->assertJson(['body' => $t->body]);
        $this->assertSame(0, $ticket->replies()->count());
        Notification::assertNothingSent();
        $t->update(['department' => 'billing']);
        $this->getJson($url)->assertNotFound();
        $t->update(['department' => null, 'active' => false]);
        $this->getJson($url)->assertNotFound();
    }

    public function test_template_validation_and_xss_escaping(): void
    {
        $this->actingAs($this->staff(['support.view', 'support.manage']));
        $this->post('/admin/suporte/modelos', ['title' => '', 'body' => str_repeat('x', 10001), 'department' => 'unknown'])->assertSessionHasErrors(['title', 'body', 'department']);
        $t = $this->template(['title' => '<script>alert(1)</script>', 'body' => '</textarea><script>alert(2)</script>']);
        $this->get('/admin/suporte/modelos/'.$t->id.'/editar')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(', false);
    }

    public function test_ticket_thread_and_list_include_template_picker_only_for_managers(): void
    {
        $this->template();
        $owner = User::factory()->create();
        $ticket = app(SupportDesk::class)->open($owner, ['subject' => 'Ajuda', 'body' => 'Texto', 'department' => 'support']);
        $this->actingAs($this->staff(['support.view', 'support.manage']));
        $this->get('/admin/suporte')->assertOk()->assertSee('Inserir no rascunho');
        $this->get('/admin/suporte/'.$ticket->id)->assertOk()->assertSee('Saudação');
        $this->actingAs($this->staff(['support.view']))->get('/admin/suporte')->assertOk()->assertDontSee('Inserir no rascunho');
        $this->actingAs($owner)->get('/painel/suporte')->assertOk()->assertDontSee('Saudação');
    }

    public function test_schema_downgrade_cannot_silently_delete_canned_replies(): void
    {
        $this->template();
        $migration = require database_path('migrations/2026_10_03_000008_canned_replies.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }
}
