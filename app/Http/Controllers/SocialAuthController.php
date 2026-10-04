<?php

namespace App\Http\Controllers;

use App\Models\SocialIdentity;
use App\Models\SocialProvider;
use App\Models\User;
use App\Services\Audit;
use App\Services\CustomerConfirmation;
use App\Services\SocialGateway;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class SocialAuthController extends Controller
{
    private function setting(string $provider): SocialProvider
    {
        abort_unless(isset(SocialProvider::LABELS[$provider]), 404);

        return SocialProvider::whereKey($provider)->where('enabled', true)->firstOrFail();
    }

    private function fail(string $message = 'Não foi possível concluir o login social. Tente novamente.')
    {
        return redirect()->route('login')->withErrors(['social' => $message]);
    }

    public function start(Request $r, string $provider, SocialGateway $gateway)
    {
        return $this->begin($r, $provider, $gateway, null);
    }

    public function link(Request $r, string $provider, SocialGateway $gateway, CustomerConfirmation $confirmation)
    {
        $u = $confirmation->verify($r);

        return $this->begin($r, $provider, $gateway, $u);
    }

    private function begin(Request $r, string $provider, SocialGateway $gateway, ?User $u)
    {
        $s = $this->setting($provider);
        $r->session()->forget(['social_pending', 'social_flow', 'state', 'code_verifier']);
        $r->session()->put('social_flow', ['provider' => $provider, 'version' => $s->version, 'expires' => time() + 600, 'user' => $u?->id, 'binding' => $u?->apiCredentialFingerprint()]);

        return $gateway->driver($s)->redirect();
    }

    public function callback(Request $r, string $provider, SocialGateway $gateway)
    {
        $flow = $r->session()->pull('social_flow');
        if (! $flow || ($flow['provider'] ?? null) !== $provider || ($flow['expires'] ?? 0) < time()) {
            return $this->fail('Solicitação expirada. Inicie o login novamente.');
        }
        try {
            $s = $this->setting($provider);
            if ($s->version !== $flow['version']) {
                return $this->fail('O provedor foi alterado. Inicie novamente.');
            }
            if ($r->has('error')) {
                $r->session()->forget(['state', 'code_verifier']);

                return $this->fail('Autorização cancelada no provedor.');
            }
            $remote = $gateway->driver($s)->user();
            $s->refresh();
            if (! $s->enabled || $s->version !== $flow['version']) {
                throw new \RuntimeException('Provider changed');
            } // Socialite validates and consumes session state; never stateless.
            $subject = (string) $remote->getId();
            if ($subject === '' || strlen($subject) > 191) {
                throw new \RuntimeException('Invalid subject');
            }
        } catch (\Throwable) {
            $r->session()->forget(['state', 'code_verifier']);

            return $this->fail();
        }
        $key = ['provider' => $provider, 'client_hash' => $s->fingerprint(), 'subject' => $subject];
        if ($flow['user']) {
            if (! $r->user() || $r->user()->id !== $flow['user']) {
                return $this->fail();
            }

            return DB::transaction(function () use ($flow, $key) {
                $u = User::lockForUpdate()->findOrFail($flow['user']);
                abort_if($u->isStaff() || $u->password_reset_required || ! $u->hasVerifiedEmail() || ! hash_equals($flow['binding'], $u->apiCredentialFingerprint()), 403);
                $existing = SocialIdentity::where($key)->first();
                if ($existing && $existing->user_id !== $u->id) {
                    throw ValidationException::withMessages(['social' => 'Esta identidade já está vinculada.']);
                }
                if (SocialIdentity::where('user_id', $u->id)->where('provider', $key['provider'])->where(fn ($q) => $q->where('subject', '!=', $key['subject'])->orWhere('client_hash', '!=', $key['client_hash']))->exists()) {
                    throw ValidationException::withMessages(['social' => 'Desvincule a conta anterior antes de trocar de identidade.']);
                }
                try {
                    $linked = SocialIdentity::firstOrCreate($key, ['user_id' => $u->id]);
                    if ($linked->user_id !== $u->id) {
                        throw ValidationException::withMessages(['social' => 'Esta identidade já está vinculada.']);
                    }
                } catch (UniqueConstraintViolationException) {
                    throw ValidationException::withMessages(['social' => 'Vínculo já utilizado. Tente novamente.']);
                }
                Audit::record('social.linked', 'user:'.$u->id, ['provider' => $key['provider']], $u->id);

                return redirect()->route('profile')->with('status', 'Conta social vinculada.');
            }, 5);
        }
        if ($r->user()) {
            return redirect()->route('profile')->withErrors(['social' => 'Para vincular, use a seção Contas conectadas.']);
        }
        $identity = SocialIdentity::where($key)->first();
        if ($identity) {
            $u = $identity->user;
            if (! $u || $u->isStaff() || $u->password_reset_required) {
                return $this->fail('Use o login com senha para esta conta.');
            }

            return $this->login($r, $u, $provider, $identity, $s);
        }
        if (! config('site.registration_enabled')) {
            return $this->fail('Novos cadastros estão desativados. Entre com senha para vincular uma conta existente.');
        }
        // Never auto-link by email, including email reported as verified by a provider.
        $r->session()->put('social_pending', $key + ['version' => $s->version, 'name' => mb_substr((string) $remote->getName(), 0, 100), 'email' => mb_substr((string) $remote->getEmail(), 0, 254), 'expires' => time() + 600]);

        return redirect()->route('social.complete');
    }

    public function complete(Request $r)
    {
        $p = $r->session()->get('social_pending');
        if (! $p || $p['expires'] < time()) {
            return $this->fail('Cadastro social expirado. Inicie novamente.');
        }

        return view('auth.social-complete', ['pending' => $p]);
    }

    public function register(Request $r)
    {
        abort_unless(config('site.registration_enabled'), 403);
        $p = $r->session()->get('social_pending');
        abort_unless($p && $p['expires'] >= time(), 419);
        $s = $this->setting($p['provider']);
        abort_unless($s->version === $p['version'] && hash_equals($s->fingerprint(), $p['client_hash']), 409);
        $r->merge(['email' => Str::lower(trim((string) $r->input('email')))]);
        $v = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:254|unique:users,email', 'password' => ['required', 'confirmed', 'max:128', Password::min(12)->letters()->numbers()], 'terms' => 'accepted']);
        try {
            $u = DB::transaction(function () use ($v, $p) {
                $u = User::create(['name' => $v['name'], 'email' => $v['email'], 'password' => $v['password']]);
                SocialIdentity::create(['user_id' => $u->id, 'provider' => $p['provider'], 'client_hash' => $p['client_hash'], 'subject' => $p['subject']]);
                Audit::record('social.registered', 'user:'.$u->id, ['provider' => $p['provider']], $u->id);

                return $u;
            }, 5);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Conta ou vínculo já existente. Entre com senha e vincule em Minha conta.']);
        }
        $r->session()->forget('social_pending');
        event(new Registered($u));
        $r->session()->put('customer_social_auth', true);
        Auth::login($u);
        $r->session()->regenerate();

        return redirect()->route('verification.notice');
    }

    private function login(Request $r, User $u, string $provider, SocialIdentity $identity, SocialProvider $setting)
    {
        $r->session()->put('customer_social_auth', true);
        if ($u->totp_secret) {
            $challenge = bin2hex(random_bytes(32));
            $key = '2fa:'.$challenge;
            Cache::put($key, ['user' => $u->id, 'binding' => hash('sha256', $u->password.'|'.$u->totp_secret), 'remember' => false, 'social_customer' => true, 'social_identity' => $identity->id, 'social_provider' => $provider, 'social_version' => $setting->version], 300);
            Cache::put($key.':attempts', 0, 300);
            $r->session()->regenerate();
            $r->session()->put('two_factor_challenge', $challenge);

            return redirect()->route('two-factor.challenge');
        }
        Auth::login($u);
        $r->session()->regenerate();
        Audit::record('social.login', 'user:'.$u->id, ['provider' => $provider], $u->id);

        return redirect()->route($u->hasVerifiedEmail() ? 'dashboard' : 'verification.notice');
    }

    public function unlink(Request $r, string $provider, CustomerConfirmation $confirmation)
    {
        $u = $confirmation->verify($r);
        DB::transaction(function () use ($u, $provider) {
            User::whereKey($u->id)->lockForUpdate()->firstOrFail();
            SocialIdentity::where('user_id', $u->id)->where('provider', $provider)->delete();
            Audit::record('social.unlinked', 'user:'.$u->id, ['provider' => $provider], $u->id);
        }, 5);

        return back()->with('status', 'Vínculo removido. Sua senha continua disponível para entrar.');
    }
}
