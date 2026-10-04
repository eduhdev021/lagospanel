<?php

namespace Tests\Feature;

use App\Models\HomepageSetting;
use App\Models\SocialIdentity;
use App\Models\SocialProvider;
use App\Models\User;
use App\Services\SiteConfiguration;
use App\Services\SocialGateway;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Two\User as RemoteUser;
use Tests\TestCase;

class SocialAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function setting(string $provider = 'github'): SocialProvider
    {
        return SocialProvider::create(['provider' => $provider, 'client_id' => 'fixture-client', 'client_secret' => 'fixture-secret', 'enabled' => true]);
    }

    private function flow(SocialProvider $s, ?User $u = null): array
    {
        return ['provider' => $s->provider, 'version' => $s->version ?? 0, 'expires' => time() + 600, 'user' => $u?->id, 'binding' => $u?->apiCredentialFingerprint()];
    }

    private function remote(string $id = 'remote-123', ?string $email = 'person@example.test'): void
    {
        $remote = (new RemoteUser)->map(['id' => $id, 'name' => 'Cliente social', 'email' => $email]);
        $driver = \Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($remote);
        $this->mock(SocialGateway::class, fn ($m) => $m->shouldReceive('driver')->once()->andReturn($driver));
    }

    private function identity(SocialProvider $s, User $u): SocialIdentity
    {
        return SocialIdentity::create(['user_id' => $u->id, 'provider' => $s->provider, 'client_hash' => $s->fingerprint(), 'subject' => 'remote-123']);
    }

    public function test_all_five_drivers_redirect_with_session_state_and_expected_host(): void
    {
        foreach (['github' => 'github.com', 'twitter' => 'x.com', 'google' => 'accounts.google.com', 'facebook' => 'www.facebook.com', 'microsoft' => 'login.microsoftonline.com'] as $provider => $host) {
            $this->setting($provider);
            $response = $this->get(route('social.start', $provider));
            $response->assertRedirect();
            $url = $response->headers->get('Location');
            $this->assertSame($host, parse_url($url, PHP_URL_HOST));
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            $this->assertNotEmpty($query['state']);
            $this->assertStringEndsWith('/entrar/social/'.$provider.'/retorno', $query['redirect_uri']);
            if (in_array($provider, ['twitter', 'google', 'microsoft'])) {
                $this->assertSame('S256', $query['code_challenge_method']);
            }
        }
    }

    public function test_disabled_and_unknown_provider_cannot_redirect(): void
    {
        $s = $this->setting();
        $s->update(['enabled' => false]);
        $this->get(route('social.start', 'github'))->assertNotFound();
        $this->get(route('social.start', 'arbitrary-host'))->assertNotFound();
    }

    public function test_missing_flow_cannot_authenticate(): void
    {
        $this->get(route('social.callback', 'github'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_real_drivers_reject_invalid_state_without_http_request(): void
    {
        foreach (array_keys(SocialProvider::LABELS) as $name) {
            $s = $this->setting($name);
            $this->withSession(['social_flow' => $this->flow($s), 'state' => 'expected'])->get(route('social.callback', $name).'?state=wrong&code=fake')->assertRedirect(route('login'))->assertSessionHasErrors('social');
            $this->assertGuest();
        }
    }

    public function test_cancelled_authorization_is_safe(): void
    {
        $s = $this->setting();
        $this->withSession(['social_flow' => $this->flow($s), 'state' => 'abc'])->get(route('social.callback', 'github').'?error=access_denied')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull(session('state'));
    }

    public function test_changed_settings_invalidate_flow(): void
    {
        $s = $this->setting();
        $flow = $this->flow($s);
        $s->update(['version' => 1]);
        $this->withSession(['social_flow' => $flow])->get(route('social.callback', 'github'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_existing_customer_logs_in_by_subject_not_email(): void
    {
        $s = $this->setting();
        $u = User::factory()->create();
        $this->identity($s, $u);
        $this->remote();
        $this->withSession(['social_flow' => $this->flow($s)])->get(route('social.callback', 'github'))->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($u);
        $this->assertTrue(session('customer_social_auth'));
    }

    public function test_equal_email_never_automatically_links_account(): void
    {
        $s = $this->setting();
        $u = User::factory()->create(['email' => 'person@example.test']);
        $this->remote();
        $this->withSession(['social_flow' => $this->flow($s)])->get(route('social.callback', 'github'))->assertRedirect(route('social.complete'));
        $this->assertGuest();
        $this->assertDatabaseCount('social_identities', 0);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_staff_identity_is_rejected(): void
    {
        $s = $this->setting();
        $u = User::factory()->create(['is_admin' => true]);
        $this->identity($s, $u);
        $this->remote();
        $this->withSession(['social_flow' => $this->flow($s)])->get(route('social.callback', 'github'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_migrated_password_requires_reset_even_with_social_identity(): void
    {
        $s = $this->setting();
        $u = User::factory()->create(['password_reset_required' => true]);
        $this->identity($s, $u);
        $this->remote();
        $this->withSession(['social_flow' => $this->flow($s)])->get(route('social.callback', 'github'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_social_login_preserves_totp_challenge(): void
    {
        $s = $this->setting();
        $u = User::factory()->create(['totp_secret' => Totp::secret()]);
        $this->identity($s, $u);
        $this->remote();
        $this->withSession(['social_flow' => $this->flow($s)])->get(route('social.callback', 'github'))->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
    }

    public function test_signup_without_provider_email_requires_own_verification_and_password(): void
    {
        Notification::fake();
        $s = $this->setting('twitter');
        $this->remote('remote-123', null);
        $this->withSession(['social_flow' => $this->flow($s)])->get(route('social.callback', 'twitter'))->assertRedirect(route('social.complete'));
        $this->get(route('social.complete'))->assertOk();
        $this->post(route('social.register'), ['name' => 'Pessoa', 'email' => 'nova@example.test', 'password' => 'LocalRecovery123', 'password_confirmation' => 'LocalRecovery123', 'terms' => 1])->assertRedirect(route('verification.notice'));
        $u = User::first();
        $this->assertAuthenticatedAs($u);
        $this->assertNull($u->email_verified_at);
        $this->assertFalse($u->isStaff());
        $this->assertDatabaseCount('social_identities', 1);
        $this->assertNull(session('social_pending'));
    }

    public function test_registration_disabled_blocks_new_social_accounts(): void
    {
        config(['site.registration_enabled' => false]);
        app()->forgetInstance(SiteConfiguration::class);
        $s = $this->setting();
        $this->remote();
        $this->withSession(['social_flow' => $this->flow($s)])->get(route('social.callback', 'github'))->assertRedirect(route('login'));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_link_requires_password_and_rejects_staff(): void
    {
        $this->setting();
        $u = User::factory()->create();
        $this->actingAs($u)->post(route('social.link', 'github'), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->actingAs(User::factory()->create(['is_admin' => true]))->post(route('social.link', 'github'), ['password' => 'password'])->assertForbidden();
    }

    public function test_link_and_unlink_with_local_password(): void
    {
        $s = $this->setting();
        $u = User::factory()->create();
        $this->actingAs($u)->post(route('social.link', 'github'), ['password' => 'password'])->assertRedirect();
        $this->remote();
        $this->get(route('social.callback', 'github'))->assertRedirect(route('profile'));
        $this->assertDatabaseHas('social_identities', ['user_id' => $u->id, 'provider' => 'github']);
        $this->delete(route('social.unlink', 'github'), ['password' => 'password'])->assertRedirect();
        $this->assertDatabaseCount('social_identities', 0);
    }

    public function test_link_refuses_identity_owned_by_someone_else(): void
    {
        $s = $this->setting();
        $owner = User::factory()->create();
        $this->identity($s, $owner);
        $u = User::factory()->create();
        $this->remote();
        $this->actingAs($u)->withSession(['social_flow' => $this->flow($s, $u)])->get(route('social.callback', 'github'))->assertSessionHasErrors('social');
        $this->assertSame($owner->id, SocialIdentity::first()->user_id);
    }

    public function test_credentials_are_encrypted_and_not_rendered_or_flashed(): void
    {
        $s = $this->setting();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->assertStringNotContainsString('fixture-secret', DB::table('social_providers')->value('client_secret'));
        $this->actingAs($admin)->get(route('admin.settings.social'))->assertOk()->assertDontSee('fixture-secret');
        $this->post(route('admin.settings.social.save', 'github'), ['version' => 0, 'enabled' => 1, 'client_id' => 'fixture-client', 'client_secret' => 'replacement-secret', 'password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertNull(session('_old_input.client_secret'));
    }

    public function test_provider_save_keeps_blank_secret_and_rejects_stale_version(): void
    {
        $s = $this->setting();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $payload = ['version' => 0, 'enabled' => 1, 'client_id' => 'fixture-client', 'client_secret' => '', 'password' => 'password'];
        $this->post(route('admin.settings.social.save', 'github'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('fixture-secret', $s->fresh()->client_secret);
        $this->post(route('admin.settings.social.save', 'github'), $payload)->assertStatus(409);
    }

    public function test_homepage_and_settings_render(): void
    {
        $this->get('/')->assertOk()->assertSee('Grandes ideias')->assertSee('Novos planos a caminho');
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('admin.settings.index'))->assertOk()->assertSee('Login social')->assertSee('data-settings-filter', false);
        $this->get(route('admin.settings.homepage'))->assertOk();
    }

    public function test_homepage_is_editable_with_concurrency_control(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $payload = ['version' => 0] + array_replace(HomepageSetting::DEFAULTS, ['title' => 'Minha hospedagem']);
        $this->post(route('admin.settings.homepage.save'), $payload)->assertRedirect();
        $this->get('/')->assertSee('Minha hospedagem');
        $this->post(route('admin.settings.homepage.save'), $payload)->assertStatus(409);
    }

    public function test_social_session_cannot_gain_admin_access_after_promotion(): void
    {
        $u = User::factory()->create(['is_admin' => true]);
        $this->actingAs($u)->withSession(['customer_social_auth' => true])->get('/admin')->assertForbidden();
    }

    public function test_provider_tokens_are_not_saved_in_pending_session(): void
    {
        $s = $this->setting();
        $this->remote();
        $this->withSession(['social_flow' => $this->flow($s)])->get(route('social.callback', 'github'));
        $this->assertArrayNotHasKey('token', session('social_pending'));
        $this->assertArrayNotHasKey('refresh_token', session('social_pending'));
    }

    public function test_social_totp_cannot_complete_after_provider_is_disabled(): void
    {
        $s = $this->setting();
        $u = User::factory()->create(['totp_secret' => Totp::secret()]);
        $this->identity($s, $u);
        $this->remote();
        $this->withSession(['social_flow' => $this->flow($s)])->get(route('social.callback', 'github'))->assertRedirect(route('two-factor.challenge'));
        $s->update(['enabled' => false]);
        $this->post(route('two-factor.confirm'), ['code' => Totp::code($u->totp_secret, intdiv(now()->timestamp, 30))])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_social_totp_cannot_complete_after_identity_is_removed(): void
    {
        $s = $this->setting();
        $u = User::factory()->create(['totp_secret' => Totp::secret()]);
        $identity = $this->identity($s, $u);
        $this->remote();
        $this->withSession(['social_flow' => $this->flow($s)])->get(route('social.callback', 'github'));
        $identity->delete();
        $this->post(route('two-factor.confirm'), ['code' => Totp::code($u->totp_secret, intdiv(now()->timestamp, 30))])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_social_totp_can_complete_with_valid_new_code(): void
    {
        $s = $this->setting();
        $u = User::factory()->create(['totp_secret' => Totp::secret()]);
        $this->identity($s, $u);
        $this->remote();
        $this->withSession(['social_flow' => $this->flow($s)])->get(route('social.callback', 'github'));
        $this->post(route('two-factor.confirm'), ['code' => Totp::code($u->totp_secret, intdiv(now()->timestamp, 30))])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($u);
        $this->assertTrue(session('customer_social_auth'));
    }

    public function test_expired_oauth_request_and_signup_are_rejected(): void
    {
        $s = $this->setting();
        $flow = $this->flow($s);
        $flow['expires'] = time() - 1;
        $this->withSession(['social_flow' => $flow])->get(route('social.callback', 'github'))->assertRedirect(route('login'));
        $this->withSession(['social_pending' => ['expires' => time() - 1]])->get(route('social.complete'))->assertRedirect(route('login'));
    }

    public function test_callback_origin_does_not_follow_untrusted_host(): void
    {
        $this->setting();
        $response = $this->withHeader('Host', 'attacker.example')->get('/entrar/social/github');
        $response->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertStringNotContainsString('attacker.example', $query['redirect_uri']);
    }

    public function test_customer_cannot_configure_oauth_secrets(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.settings.social'))->assertForbidden();
        $this->post(route('admin.settings.social.save', 'github'), ['enabled' => 1, 'client_id' => 'test', 'client_secret' => 'test', 'version' => 0, 'password' => 'password'])->assertForbidden();
    }

    public function test_public_privacy_page_has_deletion_instructions(): void
    {
        $this->get(route('privacy'))->assertOk()->assertSee('Remover um vínculo')->assertSee('Não guardamos os tokens');
    }
}
