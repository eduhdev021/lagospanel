<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\AiThread;
use App\Models\AiTurn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        AiSetting::create(['id' => 1, 'endpoint' => 'https://fixture.invalid', 'model' => 'model', 'models' => ['model'], 'active' => true, 'version' => 1]);
    }

    private function message(): array
    {
        return ['body' => 'Como configurar meu domínio?', 'request_key' => (string) Str::uuid(), 'consent' => 1];
    }

    public function test_first_message_creates_thread_without_html_reload_and_is_idempotent(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);
        $v = $this->message();
        $r = $this->postJson(route('ai.create'), $v)->assertStatus(202)->assertJsonPath('turn.body', $v['body']);
        $this->postJson(route('ai.create'), $v)->assertStatus(202)->assertJsonPath('turn.id', $r->json('turn.id'));
        $this->assertDatabaseCount('ai_threads', 1);
        $this->assertDatabaseCount('ai_turns', 1);
        $this->assertNotNull(AiThread::first()->consented_at);
    }

    public function test_first_message_failure_does_not_create_empty_thread(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('ai.create'), array_diff_key($this->message(), ['consent' => 1]))->assertUnprocessable();
        $this->assertDatabaseCount('ai_threads', 0);
    }

    public function test_unavailable_ai_rolls_back_new_thread(): void
    {
        AiSetting::find(1)->update(['active' => false]);
        $this->actingAs(User::factory()->create())->postJson(route('ai.create'), $this->message())->assertStatus(409);
        $this->assertDatabaseCount('ai_threads', 0);
    }

    public function test_followup_reuses_conversation_consent_and_returns_json(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson(route('ai.create'), $this->message())->assertStatus(202);
        $t = AiThread::first();
        AiTurn::first()->update(['status' => 'done', 'assistant_text' => 'Uma resposta.']);
        $this->postJson(route('ai.send', $t), ['body' => 'Pode explicar melhor?', 'request_key' => (string) Str::uuid()])->assertStatus(202);
        $this->assertDatabaseCount('ai_threads', 1);
        $this->assertDatabaseCount('ai_turns', 2);
    }

    public function test_legacy_conversation_requires_first_consent(): void
    {
        $u = User::factory()->create();
        $t = AiThread::create(['user_id' => $u->id]);
        $this->actingAs($u)->postJson(route('ai.send', $t), ['body' => 'Oi', 'request_key' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('consent');
    }

    public function test_cross_account_chat_actions_are_not_found(): void
    {
        $u = User::factory()->create();
        $t = AiThread::create(['user_id' => $u->id]);
        $this->actingAs(User::factory()->create());
        $this->getJson(route('ai.state', $t))->assertNotFound();
        $this->postJson(route('ai.rename', $t), ['title' => 'alterado'])->assertNotFound();
        $this->postJson(route('ai.send', $t), $this->message())->assertNotFound();
        $this->post(route('ai.delete', $t))->assertNotFound();
    }

    public function test_titles_are_encrypted_and_escaped(): void
    {
        $u = User::factory()->create();
        $t = AiThread::create(['user_id' => $u->id]);
        $this->actingAs($u)->postJson(route('ai.rename', $t), ['title' => '<script>alert(1)</script>'])->assertOk();
        $this->assertStringNotContainsString('<script>', DB::table('ai_threads')->value('title'));
        $this->get(route('ai.show', $t))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_state_excludes_provider_secret_and_execution_claim(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('ai.create'), $this->message());
        $t = AiThread::first();
        AiTurn::first()->update(['execution_token' => 'private-claim', 'assistant_text' => 'unfinished']);
        $this->getJson(route('ai.state', $t))->assertOk()->assertJsonPath('turns.0.answer', null)->assertJsonMissingPath('turns.0.execution_token')->assertDontSee('private-claim')->assertDontSee('unfinished');
    }

    public function test_first_message_same_key_different_content_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $v = $this->message();
        $this->postJson(route('ai.create'), $v)->assertStatus(202);
        $v['body'] = 'outro texto';
        $this->postJson(route('ai.create'), $v)->assertStatus(409);
        $this->assertDatabaseCount('ai_turns', 1);
    }

    public function test_web_search_still_requires_specific_consent_and_query(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('ai.create'), $this->message() + ['web_requested' => 1])->assertUnprocessable()->assertJsonValidationErrors(['web_consent', 'web_query']);
    }

    public function test_plain_html_first_message_still_works(): void
    {
        $this->actingAs(User::factory()->create())->post(route('ai.create'), $this->message())->assertRedirect();
        $this->assertDatabaseCount('ai_turns', 1);
    }

    public function test_twenty_thread_limit_still_applies(): void
    {
        $u = User::factory()->create();
        for ($i = 0; $i < 20; $i++) {
            AiThread::create(['user_id' => $u->id]);
        }$this->actingAs($u)->postJson(route('ai.create'), $this->message())->assertStatus(422);
        $this->assertDatabaseCount('ai_threads', 20);
    }

    public function test_polling_expires_abandoned_work_without_resending(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('ai.create'), $this->message());
        $t = AiThread::first();
        $turn = AiTurn::first();
        $turn->update(['updated_at' => now()->subMinutes(6), 'execution_token' => 'old-claim']);
        $this->getJson(route('ai.state', $t))->assertOk()->assertJsonPath('turns.0.status', 'failed');
        $this->assertNull($turn->fresh()->execution_token);
        $this->assertDatabaseCount('ai_turns', 1);
    }
}
