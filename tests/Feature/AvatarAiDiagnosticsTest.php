<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\User;
use App\Services\AiDiagnostics;
use App\Services\AiFailure;
use App\Services\Ollama;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AvatarAiDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    private function image(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1S8AAAAASUVORK5CYII=');
    }

    private function root(): User
    {
        $u = User::factory()->create(['password' => bcrypt('LocalOnly-4928')]);
        $u->forceFill(['is_admin' => true])->save();

        return $u;
    }

    private function setting(): AiSetting
    {
        return AiSetting::create(['id' => 1, 'endpoint' => 'https://ollama-fixture.invalid', 'token' => 'NEVER-RENDER-SECRET', 'model' => 'test', 'models' => ['test'], 'active' => true, 'version' => 1]);
    }

    public function test_gravatar_requires_consent_and_imports_only_own_email(): void
    {
        Http::preventStrayRequests();
        $u = User::factory()->create();
        $this->actingAs($u)->post(route('profile.avatar.save'), ['source' => 'gravatar'])->assertSessionHasErrors('consent');
        Http::assertNothingSent();
        Http::fake(['www.gravatar.com/*' => Http::response($this->image(), 200)]);
        $this->post(route('profile.avatar.save'), ['source' => 'gravatar', 'consent' => 1, 'email' => 'other@example.com'])->assertSessionHasNoErrors();
        Http::assertSent(fn ($r) => str_contains($r->url(), hash('sha256', strtolower(trim($u->email)))));
        $this->assertSame('gravatar', $u->fresh()->avatar_source);
        $this->get(route('profile.avatar'))->assertOk()->assertHeader('Content-Type', 'image/png')->assertContent($this->image());
        $this->assertArrayNotHasKey('avatar_content', $u->fresh()->toArray());
    }

    public function test_avatar_is_own_only_and_remove_works(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->post(route('profile.avatar.save'), ['source' => 'upload', 'avatar' => UploadedFile::fake()->createWithContent('me.png', $this->image())])->assertSessionHasNoErrors();
        $this->get(route('profile'))->assertOk()->assertSee('Foto enviada por você');
        $this->actingAs(User::factory()->create())->get(route('profile.avatar').'?user_id='.$u->id)->assertNotFound();
        $this->actingAs($u)->post(route('profile.avatar.save'), ['source' => 'remove'])->assertSessionHasNoErrors();
        $this->get(route('profile.avatar'))->assertNotFound();
    }

    public function test_bad_gravatar_preserves_existing_photo_and_svg_rejected(): void
    {
        $u = User::factory()->create();
        $u->forceFill(['avatar_content' => base64_encode($this->image()), 'avatar_mime' => 'image/png'])->save();
        Http::fake(['*' => Http::response('', 404)]);
        $this->actingAs($u)->post(route('profile.avatar.save'), ['source' => 'gravatar', 'consent' => 1])->assertSessionHasErrors('avatar');
        $this->assertNotNull($u->fresh()->avatar_content);
        $this->post(route('profile.avatar.save'), ['source' => 'upload', 'avatar' => UploadedFile::fake()->createWithContent('bad.svg', '<svg onload="alert(1)"/>')])->assertSessionHasErrors('avatar');
    }

    public function test_profile_has_unique_username_and_automatic_gravatar_fallback(): void
    {
        $u = User::factory()->create(['name' => 'Cliente Exemplo', 'email' => 'cliente@example.test']);
        $same = User::factory()->create(['name' => 'Outro Cliente', 'email' => 'cliente@example.test.2']);

        $this->assertSame('cliente', $u->username);
        $this->assertNotSame($u->username, $same->username);
        $this->assertStringContainsString(hash('sha256', $u->email), $u->avatarUrl());
        $this->assertStringContainsString('d=identicon', $u->avatarUrl());
        $this->actingAs($u)->get(route('profile'))->assertOk()->assertSee('Gravatar automático pelo e-mail');
    }

    public function test_worker_health_requires_correct_connection_queue_and_recent_signal(): void
    {
        $d = app(AiDiagnostics::class);
        $this->assertFalse($d->report()['worker_recent']);
        $d->heartbeat('redis', 'default');
        $d->heartbeat('database', 'wrong');
        $this->assertFalse($d->report()['worker_recent']);
        $d->heartbeat('database', config('queue.connections.database.queue'));
        $this->assertTrue($d->report()['worker_recent']);
        $this->travel(121)->seconds();
        $this->assertFalse($d->report()['worker_recent']);
    }

    public function test_admin_view_and_status_never_reveal_stored_token(): void
    {
        $this->setting();
        $this->actingAs($this->root())->get(route('admin.connectors.ai'))->assertOk()->assertSee('Chave salva')->assertDontSee('NEVER-RENDER-SECRET');
        $this->assertStringNotContainsString('NEVER-RENDER-SECRET', json_encode(app(AiDiagnostics::class)->report()));
    }

    public function test_probe_requires_ack_password_and_root_and_records_safe_result(): void
    {
        $this->setting();
        Http::preventStrayRequests();
        $this->actingAs($this->root())->post(route('admin.connectors.ai.probe'), ['ack' => 1])->assertSessionHasErrors('password');
        Http::assertNothingSent();
        Http::fake(['*' => Http::response(['done' => true, 'message' => ['role' => 'assistant', 'content' => 'OK']], 200)]);
        $this->post(route('admin.connectors.ai.probe'), ['ack' => 1, 'password' => 'LocalOnly-4928'])->assertSessionHasNoErrors();
        $this->assertSame('ok', AiSetting::find(1)->probe_status);
        $this->assertFalse(app(AiDiagnostics::class)->report()['worker_recent']);
    }

    public function test_http_error_is_safe_and_classified(): void
    {
        $s = $this->setting();
        Http::fake(['*' => Http::response('SECRET PROVIDER BODY', 401)]);
        try {
            app(Ollama::class)->chat($s, [['role' => 'user', 'content' => 'hello']]);
            $this->fail('Expected failure');
        } catch (AiFailure $e) {
            $this->assertSame('authentication', $e->reason);
            $this->assertStringNotContainsString('SECRET', $e->getMessage());
        }
    }

    public function test_client_cannot_probe_and_stale_probe_is_not_current(): void
    {
        Http::preventStrayRequests();
        $s = $this->setting();
        $this->actingAs(User::factory()->create())->post(route('admin.connectors.ai.probe'), ['ack' => 1, 'password' => 'LocalOnly-4928'])->assertForbidden();
        Http::assertNothingSent();
        $s->update(['probe_status' => 'ok', 'probe_version' => 1, 'probe_checked_at' => now()]);
        $this->assertSame('ok', app(AiDiagnostics::class)->report()['probe_status']);
        $s->update(['version' => 2]);
        $this->assertNull(app(AiDiagnostics::class)->report()['probe_status']);
    }
}
