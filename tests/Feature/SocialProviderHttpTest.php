<?php

namespace Tests\Feature;

use App\Models\SocialProvider;
use App\Services\SocialGateway;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialProviderHttpTest extends TestCase
{
    use RefreshDatabase;

    private function simulate(string $name, array $replies): void
    {
        $s = SocialProvider::create(['provider' => $name, 'client_id' => 'test-client', 'client_secret' => 'test-secret', 'enabled' => true]);
        $this->get(route('social.start', $name))->assertRedirect();
        $state = session('state');
        $handler = new MockHandler(array_map(fn ($data) => new Response(200, ['Content-Type' => 'application/json'], json_encode($data)), $replies));
        $this->app->bind(SocialGateway::class, fn () => new class($handler) extends SocialGateway
        {
            public function __construct(private $handler) {}

            public function driver(SocialProvider $s)
            {
                $p = parent::driver($s);

                return $p->setHttpClient(new Client(['handler' => HandlerStack::create($this->handler), 'allow_redirects' => false]));
            }
        });
        $this->get(route('social.callback', $name).'?state='.urlencode($state).'&code=test-authorization-code')->assertRedirect(route('social.complete'))->assertSessionHasNoErrors();
        $this->assertSame('123456', session('social_pending.subject'));
        $this->assertSame($name, session('social_pending.provider'));
        $this->assertSame(0, $handler->count());
        $this->assertGuest();
        $this->get(route('social.callback', $name).'?state='.urlencode($state).'&code=test-authorization-code')->assertRedirect(route('login'));
    }

    public function test_github_token_profile_and_private_email_exchange(): void
    {
        $this->simulate('github', [['access_token' => 'fixture-token'], ['id' => '123456', 'node_id' => 'node', 'login' => 'person', 'name' => 'Pessoa', 'avatar_url' => 'https://avatars.example.invalid/avatar'], [['email' => 'person@example.test', 'primary' => true, 'verified' => true]]]);
    }

    public function test_google_exchange(): void
    {
        $this->simulate('google', [['access_token' => 'fixture-token', 'expires_in' => 3600], ['sub' => '123456', 'name' => 'Pessoa', 'given_name' => 'Pessoa', 'family_name' => 'Teste', 'email' => 'person@example.test', 'email_verified' => true, 'picture' => 'https://example.invalid/avatar']]);
    }

    public function test_facebook_exchange(): void
    {
        $this->simulate('facebook', [['access_token' => 'fixture-token'], ['id' => '123456', 'name' => 'Pessoa', 'email' => 'person@example.test']]);
    }

    public function test_x_exchange_without_email(): void
    {
        $this->simulate('twitter', [['access_token' => 'fixture-token', 'expires_in' => 7200], ['data' => ['id' => '123456', 'username' => 'person', 'name' => 'Pessoa', 'profile_image_url' => 'https://example.invalid/avatar']]]);
    }

    public function test_microsoft_signed_id_token_and_graph_exchange(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        $details = openssl_pkey_get_details($key);
        $encode = fn ($v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        $tenant = '11111111-1111-4111-8111-111111111111';
        $token = JWT::encode(['iss' => 'https://login.microsoftonline.com/'.$tenant.'/v2.0', 'tid' => $tenant, 'aud' => 'test-client', 'sub' => 'subject', 'iat' => time(), 'exp' => time() + 600], $private, 'RS256', 'fixture-key');
        $this->simulate('microsoft', [['access_token' => 'fixture-token', 'id_token' => $token, 'expires_in' => 3600], ['id' => '123456', 'displayName' => 'Pessoa', 'userPrincipalName' => 'person@example.test'], ['issuer' => 'https://login.microsoftonline.com/{tenantid}/v2.0', 'jwks_uri' => 'https://login.microsoftonline.com/common/discovery/v2.0/keys', 'id_token_signing_alg_values_supported' => ['RS256']], ['keys' => [['kty' => 'RSA', 'use' => 'sig', 'kid' => 'fixture-key', 'alg' => 'RS256', 'n' => $encode($details['rsa']['n']), 'e' => $encode($details['rsa']['e'])]]]]);
    }
}
