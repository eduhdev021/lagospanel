<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\StaffRole;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Notifications\TicketUpdated;
use App\Services\SupportDesk;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SupportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(now()->startOfSecond());
    }

    private function user(bool $root = false): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => $root])->save();

        return $u;
    }

    private function operator(array $permissions): User
    {
        $u = $this->user();
        $role = StaffRole::create(['name' => 'Role '.$u->id, 'permissions' => $permissions]);
        $u->forceFill(['staff_role_id' => $role->id])->save();

        return $u;
    }

    private function data(array $extra = []): array
    {
        return array_merge(['subject' => 'Ajuda', 'body' => 'Descrição do problema', 'department' => 'support'], $extra);
    }

    private function ticket(?User $owner = null): Ticket
    {
        return app(SupportDesk::class)->open($owner ?? $this->user(), $this->data());
    }

    private function txt(string $name = 'diagnostico.txt', string $text = 'Registro de teste privado'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $text);
    }

    private function upload(User $u): TicketAttachment
    {
        $this->actingAs($u)->post('/painel/suporte', $this->data(['attachments' => [$this->txt()]]))->assertRedirect()->assertSessionHasNoErrors();

        return TicketAttachment::sole();
    }

    public function test_creation_snapshots_priority_and_deadline_and_ignores_forged_assignment(): void
    {
        $u = $this->user();
        $this->actingAs($u)->post('/painel/suporte', $this->data(['priority' => 'urgent', 'assigned_to' => 999, 'response_due_at' => now()->addYear(), 'user_id' => 999]))->assertRedirect();
        $t = Ticket::sole();
        $this->assertSame($u->id, $t->user_id);
        $this->assertNull($t->assigned_to);
        $this->assertSame(2, $t->sla_hours);
        $this->assertTrue($t->response_due_at->equalTo(now()->addHours(2)));
    }

    public function test_private_attachment_is_encrypted_and_download_is_exact_and_non_executable(): void
    {
        $a = $this->upload($u = $this->user());
        $raw = DB::table('ticket_attachments')->value('payload');
        $this->assertStringNotContainsString(base64_encode('Registro de teste privado'), $raw);
        $this->assertStringNotContainsString('payload', $a->toJson());
        $this->get('/painel/anexos/'.$a->id)->assertOk()->assertContent('Registro de teste privado')->assertHeader('Content-Type', 'application/octet-stream')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
        $this->assertStringContainsString('attachment;', $this->get('/painel/anexos/'.$a->id)->headers->get('Content-Disposition'));
        $this->assertFalse(array_key_exists('payload', $a->ticket->attachments->first()->getAttributes()));
    }

    public function test_other_customer_cannot_read_thread_reply_change_state_or_download(): void
    {
        $a = $this->upload($this->user());
        $this->actingAs($this->user());
        $this->get('/painel/anexos/'.$a->id)->assertNotFound();
        $this->get('/painel/suporte/'.$a->ticket_id)->assertNotFound();
        $this->post('/painel/suporte/'.$a->ticket_id.'/responder', ['body' => 'Intruso'])->assertNotFound();
        $this->post('/painel/suporte/'.$a->ticket_id.'/estado', ['status' => 'closed'])->assertNotFound();
    }

    public function test_download_requires_login_and_support_permission_on_admin_route(): void
    {
        $a = $this->upload($this->user());
        auth()->logout();
        $this->get('/painel/anexos/'.$a->id)->assertRedirect('/entrar');
        $this->actingAs($this->operator(['billing.view']))->get('/admin/suporte/anexos/'.$a->id)->assertForbidden();
        $this->actingAs($this->operator(['support.view']))->get('/admin/suporte/anexos/'.$a->id)->assertOk();
    }

    public function test_production_download_does_not_bypass_staff_two_factor(): void
    {
        $a = $this->upload($this->user());
        $this->actingAs($this->user(true));
        $this->app['env'] = 'production';
        $this->get('/admin/suporte/anexos/'.$a->id)->assertRedirect('/painel/perfil');
    }

    public function test_internal_note_and_attachment_never_reach_customer_or_change_deadline(): void
    {
        $u = $this->user();
        $t = $this->ticket($u);
        $due = $t->response_due_at;
        $this->actingAs($this->user(true))->post('/admin/suporte/'.$t->id, ['body' => 'Segredo da equipe', 'status' => 'closed', 'internal' => 1, 'attachments' => [$this->txt('interno.txt')]])->assertRedirect()->assertSessionHasNoErrors();
        $t->refresh();
        $this->assertSame('open', $t->status);
        $this->assertTrue($t->response_due_at->equalTo($due));
        $this->assertNull($t->first_responded_at);
        Notification::assertNothingSent();
        $a = TicketAttachment::sole();
        $this->get('/admin/suporte/'.$t->id)->assertSee('Segredo da equipe')->assertSee('interno.txt');
        $this->actingAs($u)->get('/painel/suporte')->assertDontSee('Segredo da equipe')->assertDontSee('interno.txt');
        $this->get('/painel/suporte/'.$t->id)->assertDontSee('Segredo da equipe')->assertDontSee('interno.txt');
        $this->get('/painel/anexos/'.$a->id)->assertNotFound();
    }

    public function test_public_reply_clears_deadline_and_notifies_only_owner(): void
    {
        $u = $this->user();
        $t = $this->ticket($u);
        $this->travel(1)->hours();
        $this->actingAs($this->user(true))->post('/admin/suporte/'.$t->id, ['body' => 'Solução', 'status' => 'answered'])->assertRedirect();
        $t->refresh();
        $this->assertSame('answered', $t->status);
        $this->assertNull($t->response_due_at);
        $this->assertTrue($t->first_responded_at->equalTo(now()));
        Notification::assertSentTo($u, TicketUpdated::class);
        $this->actingAs($u)->post('/painel/suporte/'.$t->id.'/responder', ['body' => 'Ainda preciso de ajuda'])->assertRedirect();
        $this->assertTrue($t->fresh()->response_due_at->equalTo(now()->addHours(24)));
    }

    public function test_customer_followup_does_not_postpone_pending_response(): void
    {
        $u = $this->user();
        $t = $this->ticket($u);
        $due = $t->response_due_at;
        $this->travel(4)->hours();
        $this->actingAs($u)->post('/painel/suporte/'.$t->id.'/responder', ['body' => 'Complemento', 'internal' => 1])->assertRedirect();
        $this->assertTrue($t->fresh()->response_due_at->equalTo($due));
        $this->assertFalse($t->replies()->sole()->is_internal);
    }

    public function test_owner_close_reopen_is_idempotent_and_cannot_reply_to_closed_ticket(): void
    {
        $u = $this->user();
        $t = $this->ticket($u);
        $this->actingAs($u);
        $this->post('/painel/suporte/'.$t->id.'/estado', ['status' => 'closed'])->assertRedirect();
        $this->assertNull($t->fresh()->response_due_at);
        $this->post('/painel/suporte/'.$t->id.'/responder', ['body' => 'Não aceitar'])->assertStatus(422);
        $this->assertDatabaseCount('ticket_replies', 0);
        $this->post('/painel/suporte/'.$t->id.'/estado', ['status' => 'open'])->assertRedirect();
        $due = $t->fresh()->response_due_at;
        $this->travel(1)->hours();
        $this->post('/painel/suporte/'.$t->id.'/estado', ['status' => 'open'])->assertRedirect();
        $this->assertTrue($t->fresh()->response_due_at->equalTo($due));
    }

    public function test_triage_requires_manage_permission_and_suitable_assignee(): void
    {
        $t = $this->ticket();
        $viewer = $this->operator(['support.view']);
        $this->actingAs($viewer)->post('/admin/suporte/'.$t->id.'/triagem', ['priority' => 'urgent'])->assertForbidden();
        $this->actingAs($this->user(true))->post('/admin/suporte/'.$t->id.'/triagem', ['priority' => 'urgent', 'assigned_to' => $viewer->id])->assertSessionHasErrors('assigned_to');
        $staff = $this->operator(['support.view', 'support.manage']);
        $this->post('/admin/suporte/'.$t->id.'/triagem', ['priority' => 'urgent', 'assigned_to' => $staff->id])->assertSessionHasNoErrors();
        $this->assertSame($staff->id, $t->fresh()->assigned_to);
        $this->assertTrue($t->fresh()->response_due_at->equalTo(now()->addHours(2)));
    }

    public function test_downgrading_priority_cannot_extend_an_existing_deadline(): void
    {
        $t = $this->ticket();
        $due = $t->response_due_at;
        $this->actingAs($this->user(true))->post('/admin/suporte/'.$t->id.'/triagem', ['priority' => 'low'])->assertRedirect();
        $this->assertTrue($t->fresh()->response_due_at->equalTo($due));
        $this->assertSame(48, $t->fresh()->sla_hours);
    }

    public function test_overdue_mine_department_and_priority_filters(): void
    {
        $admin = $this->user(true);
        $a = $this->ticket();
        $a->update(['subject' => 'Atrasado único', 'priority' => 'urgent', 'assigned_to' => $admin->id, 'response_due_at' => now()->subMinute()]);
        $b = $this->ticket();
        $b->update(['subject' => 'Oculto sem atraso']);
        $this->actingAs($admin)->get('/admin/suporte?overdue=1&mine=1&priority=urgent&department=support')->assertOk()->assertSee('Atrasado único')->assertDontSee('Oculto sem atraso');
    }

    public function test_dangerous_or_mismatched_formats_are_rejected_without_orphans(): void
    {
        $this->actingAs($this->user());
        foreach (['script.php' => '<?php echo 1;', 'image.svg' => '<svg xmlns="http://www.w3.org/2000/svg"></svg>', 'fake.png' => 'plain text', 'index.html' => '<html><script>alert(1)</script></html>'] as $name => $body) {
            $this->post('/painel/suporte', $this->data(['attachments' => [$this->txt($name, $body)]]))->assertSessionHasErrors('attachments');
        }
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('ticket_attachments', 0);
    }

    public function test_file_size_count_and_empty_file_validation(): void
    {
        $this->actingAs($this->user());
        $this->post('/painel/suporte', $this->data(['attachments' => [$this->txt(), $this->txt(), $this->txt(), $this->txt()]]))->assertSessionHasErrors('attachments');
        $this->post('/painel/suporte', $this->data(['attachments' => [UploadedFile::fake()->create('large.txt', 2049, 'text/plain')]]))->assertSessionHasErrors('attachments.0');
        $this->post('/painel/suporte', $this->data(['attachments' => [$this->txt('empty.txt', '')]]))->assertSessionHasErrors('attachments');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_total_ticket_quota_failure_rolls_back_reply_and_state(): void
    {
        $a = $this->upload($u = $this->user());
        DB::table('ticket_attachments')->where('id', $a->id)->update(['size' => 10485760]);
        $this->post('/painel/suporte/'.$a->ticket_id.'/responder', ['body' => 'Quota atingida', 'attachments' => [$this->txt()]])->assertSessionHasErrors('attachments');
        $this->assertDatabaseCount('ticket_replies', 0);
        $this->assertDatabaseCount('ticket_attachments', 1);
        $this->assertSame('open', $a->ticket->status);
    }

    public function test_tampered_content_fails_integrity_check(): void
    {
        $a = $this->upload($this->user());
        $a->update(['payload' => base64_encode('Alterado')]);
        $this->get('/painel/anexos/'.$a->id)->assertStatus(409);
    }

    public function test_attachment_insert_failure_rolls_back_whole_creation(): void
    {
        $u = $this->user();
        $desk = app(SupportDesk::class);
        $files = $desk->prepare([$this->txt(), $this->txt()]);
        $count = 0;
        TicketAttachment::creating(function () use (&$count) {
            if (++$count === 2) {
                throw new \RuntimeException('Disk equivalent failure');
            }
        });
        try {
            $desk->open($u, $this->data(), $files);
            $this->fail('Failure expected');
        } catch (\RuntimeException $e) {
            $this->assertSame('Disk equivalent failure', $e->getMessage());
        } finally {
            TicketAttachment::flushEventListeners();
        }
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('ticket_attachments', 0);
    }

    public function test_mail_queue_failure_rolls_back_public_reply(): void
    {
        $t = $this->ticket();
        $admin = $this->user(true);
        Notification::swap(new ChannelManager($this->app));
        Schema::drop('jobs');
        try {
            app(SupportDesk::class)->reply($t, $admin, 'Teste', [], true);
            $this->fail('Queue failure expected');
        } catch (QueryException $e) {
            $this->assertStringContainsString('jobs', $e->getMessage());
        }
        $this->assertDatabaseCount('ticket_replies', 0);
        $this->assertSame('open', $t->fresh()->status);
    }

    public function test_large_threads_are_paginated_and_internal_notes_do_not_consume_client_pages(): void
    {
        $u = $this->user();
        $t = $this->ticket($u);
        for ($n = 1; $n <= 35; $n++) {
            $t->replies()->create(['user_id' => $u->id, 'body' => 'PUBLIC-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT)]);
        }
        $t->replies()->create(['user_id' => $u->id, 'body' => 'PRIVATE-HIDDEN', 'is_internal' => true]);
        $this->actingAs($u)->get('/painel/suporte')->assertOk()->assertSee('PUBLIC-035')->assertDontSee('PUBLIC-001')->assertDontSee('PRIVATE-HIDDEN');
        $this->get('/painel/suporte/'.$t->id)->assertOk()->assertSee('PUBLIC-006')->assertDontSee('PUBLIC-005')->assertDontSee('PRIVATE-HIDDEN');
        $this->get('/painel/suporte/'.$t->id.'?page=2')->assertSee('PUBLIC-001')->assertDontSee('PUBLIC-006');
    }

    public function test_legacy_ticket_has_no_invented_historical_deadline(): void
    {
        $u = $this->user();
        $t = $u->tickets()->create($this->data());
        $this->assertNull($t->response_due_at);
        $this->actingAs($u)->post('/painel/suporte/'.$t->id.'/responder', ['body' => 'Novo contato'])->assertRedirect();
        $this->assertTrue($t->fresh()->response_due_at->equalTo(now()->addHours(24)));
    }

    public function test_invalid_priority_is_rejected_and_message_html_is_escaped(): void
    {
        $u = $this->user();
        $this->actingAs($u)->post('/painel/suporte', $this->data(['priority' => 'root']))->assertSessionHasErrors('priority');
        $t = app(SupportDesk::class)->open($u, $this->data(['body' => '<script>alert(1)</script>']));
        $this->get('/painel/suporte/'.$t->id)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_binary_png_and_pdf_are_preserved(): void
    {
        $u = $this->user();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZxkAAAAASUVORK5CYII=');
        $pdf = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n";
        $this->actingAs($u)->post('/painel/suporte', $this->data(['attachments' => [UploadedFile::fake()->createWithContent('pixel.png', $png), UploadedFile::fake()->createWithContent('manual.pdf', $pdf)]]))->assertSessionHasNoErrors();
        $files = TicketAttachment::orderBy('id')->get();
        $this->assertCount(2, $files);
        $this->get('/painel/anexos/'.$files[0]->id)->assertContent($png);
        $this->get('/painel/anexos/'.$files[1]->id)->assertContent($pdf);
    }

    public function test_api_creation_uses_same_deadline_policy_and_does_not_accept_attachments(): void
    {
        $u = $this->user();
        $raw = 'lp_'.bin2hex(random_bytes(32));
        ApiToken::create(['user_id' => $u->id, 'name' => 'Test', 'token_hash' => hash('sha256', $raw), 'password_fingerprint' => $u->apiCredentialFingerprint(), 'scopes' => ['tickets:write'], 'expires_at' => now()->addDay()]);
        $this->withToken($raw)->postJson('/api/v1/tickets', $this->data(['priority' => 'high']))->assertCreated();
        $this->assertTrue(Ticket::sole()->response_due_at->equalTo(now()->addHours(8)));
        $this->withToken($raw)->postJson('/api/v1/tickets', $this->data(['attachments' => ['malicious']]))->assertUnprocessable();
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_notification_contains_no_private_message_and_rechecks_owner(): void
    {
        $u = $this->user();
        $t = $this->ticket($u);
        $n = new TicketUpdated($t->id);
        $this->assertTrue($n->shouldSend($u, 'mail'));
        $this->assertFalse($n->shouldSend($this->user(), 'mail'));
        $this->assertStringNotContainsString($t->body, json_encode($n->toMail($u)->toArray()));
        $this->assertInstanceOf(ShouldBeEncrypted::class, $n);
    }

    public function test_account_quota_spans_tickets_and_includes_staff_attachments_but_not_other_customers(): void
    {
        config(['support.account_attachment_bytes' => 20]);
        $u = $this->user();
        $a = $this->ticket($u);
        $b = $this->ticket($u);
        $staff = $this->user(true);
        $desk = app(SupportDesk::class);
        $desk->reply($a, $staff, 'Anexo público', $desk->prepare([$this->txt('a.txt', str_repeat('X', 20))]), true);
        $this->actingAs($u)->post('/painel/suporte/'.$b->id.'/responder', ['body' => 'Anexo excedente', 'attachments' => [$this->txt('b.txt', 'Next text')]])->assertSessionHasErrors('attachments');
        $this->assertSame(1, TicketAttachment::count());
        $this->post('/painel/suporte/'.$b->id.'/responder', ['body' => 'Sem arquivo funciona'])->assertRedirect();
        $this->assertSame(1, $b->replies()->count());
        $v = $this->user();
        $this->actingAs($v)->post('/painel/suporte', $this->data(['attachments' => [$this->txt('c.txt', 'Another text')]]))->assertSessionHasNoErrors();
        $this->assertSame(2, TicketAttachment::count());
    }

    public function test_corrupted_ciphertext_is_not_returned_as_a_download(): void
    {
        $a = $this->upload($this->user());
        DB::table('ticket_attachments')->where('id', $a->id)->update(['payload' => 'corrupted']);
        $this->get('/painel/anexos/'.$a->id)->assertStatus(409);
    }

    public function test_support_schema_roundtrip_preserves_legacy_ticket(): void
    {
        $t = $this->ticket();
        $migration = require database_path('migrations/2026_10_03_000006_expand_support.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('ticket_attachments'));
        $this->assertFalse(Schema::hasColumn('tickets', 'response_due_at'));
        $this->assertDatabaseHas('tickets', ['id' => $t->id, 'body' => $t->body]);
        $migration->up();
        $this->assertTrue(Schema::hasTable('ticket_attachments'));
        $this->assertNull($t->fresh()->response_due_at);
    }

    public function test_schema_downgrade_cannot_turn_private_notes_into_public_replies(): void
    {
        $t = $this->ticket();
        $t->replies()->create(['user_id' => $t->user_id, 'body' => 'Confidencial', 'is_internal' => true]);
        $migration = require database_path('migrations/2026_10_03_000006_expand_support.php');
        try {
            $migration->down();
            $this->fail('Must block unsafe rollback');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Rollback bloqueado', $e->getMessage());
        }
        $this->assertTrue(Schema::hasTable('ticket_attachments'));
        $this->assertTrue($t->replies()->sole()->is_internal);
    }

    public function test_schema_downgrade_cannot_silently_destroy_attachments(): void
    {
        $a = $this->upload($this->user());
        $migration = require database_path('migrations/2026_10_03_000006_expand_support.php');
        try {
            $migration->down();
            $this->fail('Must block data loss');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Rollback bloqueado', $e->getMessage());
        }
        $this->assertDatabaseCount('ticket_attachments', 1);
        $this->get('/painel/anexos/'.$a->id)->assertOk();
    }
}
