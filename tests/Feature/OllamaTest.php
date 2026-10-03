<?php

namespace Tests\Feature;

use App\Jobs\AnswerAiTurn;
use App\Models\AiSetting;
use App\Models\AiThread;
use App\Models\AiTurn;
use App\Models\AuditEvent;
use App\Models\StaffRole;
use App\Models\User;
use App\Services\AiChat;
use App\Services\Ollama;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OllamaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Queue::fake();
    }

    private function root(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => true])->save();

        return $u;
    }

    private function setting(): AiSetting
    {
        return AiSetting::create(['id' => 1, 'endpoint' => 'https://ollama-fixture.invalid', 'token' => 'private-ollama-api-key', 'model' => 'test-model:latest', 'models' => ['test-model:latest'], 'active' => true, 'version' => 1]);
    }

    private function thread(?User $u = null): AiThread
    {
        $u ??= User::factory()->create();

        return AiThread::create(['user_id' => $u->id]);
    }

    private function send(AiThread $t, string $body = 'Como abrir um chamado?'): AiTurn
    {
        return app(AiChat::class)->send($t->user, $t, $body, (string) Str::uuid());
    }

    private function response(string $body = 'Abra um chamado no painel.'): array
    {
        return ['done' => true, 'message' => ['role' => 'assistant', 'content' => $body]];
    }

    public function test_admin_can_save_hidden_key_discover_models_and_select_available_model(): void
    {
        $this->actingAs($this->root());
        $base = '/admin/integracoes/ollama';
        $this->post($base, ['endpoint' => 'https://ollama-fixture.invalid', 'token' => 'private-ollama-api-key', 'version' => 0])->assertSessionHasNoErrors();
        Http::fake(['*' => Http::response(['models' => [['name' => 'model-a'], ['name' => 'model-b']]])]);
        $this->post($base.'/modelos')->assertSessionHasNoErrors();
        $s = AiSetting::findOrFail(1);
        $this->assertSame(['model-a', 'model-b'], $s->models);
        Http::assertSent(fn ($r) => $r->url() === 'https://ollama-fixture.invalid/api/tags' && $r->hasHeader('Authorization', 'Bearer private-ollama-api-key'));
        $this->post($base, ['endpoint' => $s->endpoint, 'model' => 'model-b', 'active' => 1, 'ack' => 1, 'version' => $s->version])->assertSessionHasNoErrors();
        $this->assertTrue($s->fresh()->active);
        $this->assertSame('model-b', $s->fresh()->model);
        $this->get($base)->assertOk()->assertDontSee('private-ollama-api-key');
        $this->assertStringNotContainsString('private-ollama-api-key', DB::table('ai_settings')->value('token'));
        $this->assertArrayNotHasKey('token', $s->toArray());
    }

    public function test_invalid_model_cannot_be_forged_and_connection_rotation_clears_catalog(): void
    {
        $s = $this->setting();
        $this->actingAs($this->root());
        $base = '/admin/integracoes/ollama';
        $this->post($base, ['endpoint' => $s->endpoint, 'model' => 'invented-model', 'active' => 1, 'ack' => 1, 'version' => 1])->assertSessionHasErrors('model');
        $this->post($base, ['endpoint' => $s->endpoint, 'token' => 'rotated-private-key', 'version' => 1])->assertSessionHasNoErrors();
        $s->refresh();
        $this->assertFalse($s->active);
        $this->assertSame([], $s->models);
        $this->assertNull($s->model);
        $this->post($base, ['endpoint' => $s->endpoint, 'version' => 1])->assertStatus(409);
    }

    public function test_catalog_failure_is_sanitized_and_does_not_replace_existing_models(): void
    {
        $s = $this->setting();
        $this->actingAs($this->root());
        Http::fake(['*' => Http::response(['error' => 'secret-provider-key'], 401)]);
        $r = $this->post('/admin/integracoes/ollama/modelos')->assertSessionHasErrors('models');
        $this->assertSame(['test-model:latest'], $s->fresh()->models);
        $this->assertStringNotContainsString('secret-provider-key', session('errors')->first('models'));
    }

    public function test_readonly_integrations_cannot_change_key_or_query_models(): void
    {
        $role = StaffRole::create(['name' => 'Read integrations', 'permissions' => ['integrations.view']]);
        $u = User::factory()->create();
        $u->forceFill(['staff_role_id' => $role->id])->save();
        $this->setting();
        $this->actingAs($u)->get('/admin/integracoes/ollama')->assertOk()->assertDontSee('Salvar configuração Ollama');
        $this->post('/admin/integracoes/ollama', [])->assertForbidden();
        $this->post('/admin/integracoes/ollama/modelos')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_customer_cannot_access_administrative_ai_settings(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/integracoes/ollama')->assertForbidden();
        $this->post('/admin/integracoes/ollama/modelos')->assertForbidden();
    }

    public function test_http_private_network_and_url_credentials_are_denied_by_default(): void
    {
        foreach (['http://127.0.0.1:11434', 'http://192.168.1.2:11434', 'https://user:pass@example.test', 'https://example.test/api'] as $url) {
            try {
                Ollama::origin($url);
                $this->fail('Unsafe origin accepted');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
        config(['ai.allow_loopback_http' => true]);
        $this->assertSame('http://127.0.0.1:11434', Ollama::origin('http://127.0.0.1:11434'));
    }

    public function test_chat_uses_selected_model_and_stores_encrypted_text_without_executing_html(): void
    {
        $this->setting();
        $t = $this->thread();
        $turn = $this->send($t, 'Texto privado');
        Http::fake(['*' => Http::response($this->response('<script>window.ai_xss=1</script>'))]);
        (new AnswerAiTurn($turn->id))->handle();
        $turn->refresh();
        $this->assertSame('done', $turn->status);
        $this->assertSame('<script>window.ai_xss=1</script>', $turn->assistant_text);
        $this->assertStringNotContainsString('Texto privado', DB::table('ai_turns')->value('user_text'));
        $this->assertStringNotContainsString('<script>', DB::table('ai_turns')->value('assistant_text'));
        Http::assertSent(fn ($r) => $r['model'] === 'test-model:latest' && $r['stream'] === false && ! isset($r['tools']) && $r['messages'][1]['role'] === 'user' && ! str_contains($r->body(), $t->user->email));
        $this->actingAs($t->user)->get('/painel/chat/'.$t->id)->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>window.ai_xss', false);
    }

    public function test_duplicate_request_and_job_do_not_duplicate_inference(): void
    {
        $this->setting();
        $t = $this->thread();
        $key = (string) Str::uuid();
        $a = app(AiChat::class)->send($t->user, $t, 'Hello', $key);
        $b = app(AiChat::class)->send($t->user, $t, 'Hello', $key);
        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, DB::table('ai_daily_usages')->value('requests'));
        Http::fake(['*' => Http::response($this->response())]);
        (new AnswerAiTurn($a->id))->handle();
        (new AnswerAiTurn($a->id))->handle();
        Http::assertSentCount(1);
    }

    public function test_one_pending_request_per_user_even_across_threads(): void
    {
        $this->setting();
        $t = $this->thread();
        $this->send($t);
        $other = $this->thread($t->user);
        $this->actingAs($t->user)->post('/painel/chat/'.$other->id, ['body' => 'Next', 'request_key' => (string) Str::uuid(), 'consent' => 1])->assertStatus(409);
        $this->assertDatabaseCount('ai_turns', 1);
    }

    public function test_daily_quota_survives_conversation_deletion(): void
    {
        $this->setting();
        config(['ai.daily_requests' => 1]);
        $t = $this->thread();
        $this->send($t);
        $u = $t->user;
        $this->actingAs($u)->post('/painel/chat/'.$t->id.'/excluir')->assertRedirect();
        $next = $this->thread($u);
        $this->post('/painel/chat/'.$next->id, ['body' => 'Next', 'request_key' => (string) Str::uuid(), 'consent' => 1])->assertStatus(429);
        $this->assertDatabaseCount('ai_turns', 0);
    }

    public function test_messages_require_consent_and_reject_oversized_input(): void
    {
        $this->setting();
        $t = $this->thread();
        $this->actingAs($t->user)->post('/painel/chat/'.$t->id, ['body' => str_repeat('x', 2001), 'request_key' => (string) Str::uuid()])->assertSessionHasErrors(['body', 'consent']);
        $this->assertDatabaseCount('ai_turns', 0);
    }

    public function test_other_user_cannot_read_send_poll_or_delete_thread(): void
    {
        $this->setting();
        $t = $this->thread();
        $this->send($t);
        $this->actingAs(User::factory()->create());
        $base = '/painel/chat/'.$t->id;
        $this->get($base)->assertNotFound();
        $this->getJson($base.'/estado')->assertNotFound();
        $this->post($base, ['body' => 'bad'])->assertNotFound();
        $this->post($base.'/excluir')->assertNotFound();
    }

    public function test_pause_or_rotation_before_processing_prevents_http(): void
    {
        $s = $this->setting();
        $t = $this->thread();
        $turn = $this->send($t);
        $s->update(['active' => false, 'version' => 2]);
        (new AnswerAiTurn($turn->id))->handle();
        $this->assertSame('failed', $turn->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_pause_during_inference_prevents_late_publication(): void
    {
        $s = $this->setting();
        $t = $this->thread();
        $turn = $this->send($t);
        Http::fake(function () use ($s) {
            $s->update(['active' => false, 'version' => 2]);

            return Http::response($this->response());
        });
        (new AnswerAiTurn($turn->id))->handle();
        $this->assertSame('failed', $turn->fresh()->status);
        $this->assertNull($turn->fresh()->assistant_text);
    }

    public function test_deletion_during_inference_does_not_recreate_conversation(): void
    {
        $this->setting();
        $t = $this->thread();
        $turn = $this->send($t);
        Http::fake(function () use ($t) {
            $t->delete();

            return Http::response($this->response());
        });
        (new AnswerAiTurn($turn->id))->handle();
        $this->assertDatabaseCount('ai_turns', 0);
        $this->assertDatabaseCount('ai_threads', 0);
    }

    public function test_tool_calls_fail_closed_without_retry(): void
    {
        $this->setting();
        $t = $this->thread();
        $turn = $this->send($t);
        Http::fake(['*' => Http::response(['done' => true, 'message' => ['role' => 'assistant', 'content' => 'Run a command', 'tool_calls' => [['function' => ['name' => 'shell']]]]])]);
        (new AnswerAiTurn($turn->id))->handle();
        $this->assertSame('failed', $turn->fresh()->status);
        (new AnswerAiTurn($turn->id))->handle();
        Http::assertSentCount(1);
        $this->assertNull($turn->fresh()->assistant_text);
    }

    public function test_history_is_limited_to_own_thread_and_successful_exchanges(): void
    {
        $this->setting();
        $t = $this->thread();
        $other = $this->thread();
        foreach ([[$t, 'my-history'], [$other, 'foreign-secret']] as [$thread,$text]) {
            $thread->turns()->create(['request_key' => (string) Str::uuid(), 'user_text' => $text, 'assistant_text' => 'previous response', 'status' => 'done', 'settings_version' => 1, 'model' => 'test-model:latest']);
        }
        $turn = $this->send($t);
        Http::fake(['*' => Http::response($this->response())]);
        (new AnswerAiTurn($turn->id))->handle();
        Http::assertSent(fn ($r) => str_contains($r->body(), 'my-history') && ! str_contains($r->body(), 'foreign-secret') && count($r['messages']) === 4);
    }

    public function test_tls_redirect_and_bearer_policy(): void
    {
        $this->setting();
        $t = $this->thread();
        $turn = $this->send($t);
        Http::fake(function ($r, $opts) {
            $this->assertTrue($opts['verify']);
            $this->assertFalse($opts['allow_redirects']);
            $this->assertSame(45, $opts['timeout']);
            $this->assertTrue($r->hasHeader('Authorization', 'Bearer private-ollama-api-key'));

            return Http::response($this->response());
        });
        (new AnswerAiTurn($turn->id))->handle();
        $this->assertSame('done', $turn->fresh()->status);
    }

    public function test_config_disabled_chat_keeps_human_support_link_and_no_http(): void
    {
        $s = $this->setting();
        $s->update(['active' => false]);
        $t = $this->thread();
        $this->actingAs($t->user)->get('/painel/chat/'.$t->id)->assertOk()->assertSee('Abrir chamado humano')->assertDontSee('Enviar para a IA');
        $this->post('/painel/chat/'.$t->id, ['body' => 'Hello', 'request_key' => (string) Str::uuid(), 'consent' => 1])->assertStatus(409);
        Http::assertNothingSent();
    }

    public function test_connection_timeout_is_not_logged_as_raw_provider_error_or_retried(): void
    {
        $this->setting();
        $t = $this->thread();
        $turn = $this->send($t);
        Http::fake(fn () => throw new ConnectionException('private-ollama-api-key raw-provider-error'));
        (new AnswerAiTurn($turn->id))->handle();
        $this->assertSame('failed', $turn->fresh()->status);
        $this->assertNull($turn->fresh()->assistant_text);
        $this->assertStringNotContainsString('private-ollama-api-key', AuditEvent::all()->toJson());
    }

    public function test_stale_queue_request_is_failed_before_new_request_and_old_job_cannot_send(): void
    {
        $this->setting();
        $t = $this->thread();
        $old = $this->send($t);
        $this->travel(6)->minutes();
        $new = $this->send($t, 'Nova pergunta');
        $this->assertSame('failed', $old->fresh()->status);
        (new AnswerAiTurn($old->id))->handle();
        Http::assertNothingSent();
        $this->assertSame('queued', $new->fresh()->status);
    }

    public function test_same_request_key_with_different_body_conflicts(): void
    {
        $this->setting();
        $t = $this->thread();
        $key = (string) Str::uuid();
        app(AiChat::class)->send($t->user, $t, 'Primeira', $key);
        $this->actingAs($t->user)->post('/painel/chat/'.$t->id, ['body' => 'Segunda', 'request_key' => $key, 'consent' => 1])->assertStatus(409);
        $this->assertDatabaseCount('ai_turns', 1);
    }
}
