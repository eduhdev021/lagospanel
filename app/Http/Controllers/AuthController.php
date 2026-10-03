<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        $r->merge(['email' => Str::lower(trim((string) $r->input('email')))]);
        $v = $r->validate(['email' => 'required|email|max:254', 'password' => 'required|string|max:256']);
        $key = 'login:'.hash('sha256', $v['email'].'|'.$r->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Muitas tentativas. Aguarde alguns minutos.']);
        }
        $user = User::where('email', $v['email'])->first();
        if (! $user || $user->password_reset_required || ! Hash::check($v['password'], $user->password)) {
            RateLimiter::hit($key, 900);
            throw ValidationException::withMessages(['email' => 'Credenciais inválidas. Contas migradas precisam redefinir a senha.']);
        }RateLimiter::clear($key);
        if ($user->totp_secret) {
            $challenge = bin2hex(random_bytes(32));
            $cacheKey = '2fa:'.$challenge;
            Cache::put($cacheKey, ['user' => $user->id, 'binding' => hash('sha256', $user->password.'|'.$user->totp_secret), 'remember' => $r->boolean('remember')], 300);
            Cache::put($cacheKey.':attempts', 0, 300);
            $r->session()->regenerate();
            $r->session()->put('two_factor_challenge', $challenge);

            return redirect()->route('two-factor.challenge');
        }
        Auth::login($user, $r->boolean('remember'));
        $r->session()->regenerate();
        Audit::record('auth.login', 'user:'.$user->id, [], $user->id);

        return redirect()->intended(route('dashboard'));
    }

    public function register(Request $r)
    {
        abort_unless(config('site.registration_enabled'), 403, 'Novos cadastros estão desativados.');
        $r->merge(['email' => Str::lower(trim((string) $r->input('email')))]);
        $v = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:254|unique:users', 'password' => ['required', 'confirmed', 'max:128', PasswordRule::min(12)->letters()->numbers()], 'terms' => 'accepted']);
        $u = User::create(['name' => $v['name'], 'email' => $v['email'], 'password' => $v['password']]);
        event(new Registered($u));
        Auth::login($u);
        $r->session()->regenerate();

        return redirect()->route('verification.notice');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function forgot(Request $r)
    {
        $v = $r->validate(['email' => 'required|email']);
        Password::sendResetLink($v);

        return back()->with('status', 'Se a conta existir, enviaremos um link para redefinir a senha.');
    }

    public function reset(Request $r)
    {
        $v = $r->validate(['token' => 'required', 'email' => 'required|email', 'password' => ['required', 'confirmed', 'max:128', PasswordRule::min(12)->letters()->numbers()]]);
        $result = Password::reset($v, function (User $u, string $pw) {
            $u->forceFill(['password' => $pw, 'password_reset_required' => false, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($u));
        });
        if ($result !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Link inválido ou expirado.']);
        }

        return redirect()->route('login')->with('status', 'Senha alterada. Entre com a nova senha.');
    }
}
