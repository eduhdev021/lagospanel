<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Audit;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function challenge(Request $r)
    {
        if (! $r->session()->has('two_factor_challenge')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor');
    }

    public function confirm(Request $r)
    {
        $v = $r->validate(['code' => 'required|string|max:40']);
        $key = '2fa:'.$r->session()->get('two_factor_challenge', 'none');
        $data = Cache::get($key);
        $attempts = Cache::increment($key.':attempts');
        if (! $data || $attempts > 5) {
            Cache::forget($key);
            $r->session()->forget('two_factor_challenge');
            throw ValidationException::withMessages(['code' => 'Desafio expirado. Entre novamente.']);
        }
        $user = DB::transaction(function () use ($data, $v) {
            $u = User::lockForUpdate()->find($data['user']);
            if (! $u || ! $u->totp_secret || ! hash_equals($data['binding'], hash('sha256', $u->password.'|'.$u->totp_secret))) {
                return null;
            }
            $step = Totp::step($u->totp_secret, $v['code'], $u->totp_last_step);
            if ($step !== null) {
                $u->totp_last_step = $step;
                $u->save();

                return $u;
            }
            $hash = hash('sha256', strtolower(str_replace('-', '', $v['code'])));
            $codes = $u->recovery_codes ?? [];
            foreach ($codes as $i => $c) {
                if (hash_equals($c, $hash)) {
                    unset($codes[$i]);
                    $u->recovery_codes = array_values($codes);
                    $u->save();

                    return $u;
                }
            }

            return null;
        });
        if (! $user) {
            throw ValidationException::withMessages(['code' => 'Código inválido ou já utilizado.']);
        }Cache::forget($key);
        Cache::forget($key.':attempts');
        $r->session()->forget('two_factor_challenge');
        Auth::login($user, (bool) $data['remember']);
        $r->session()->regenerate();
        Audit::record('auth.two_factor_login', 'user:'.$user->id, [], $user->id);

        return redirect()->intended(route('dashboard'));
    }

    public function begin(Request $r)
    {
        $r->validate(['password' => 'required|current_password']);
        abort_if($r->user()->totp_secret, 422);
        $r->session()->put('totp_setup', ['secret' => Totp::secret(), 'expires' => now()->addMinutes(10)->timestamp]);

        return back()->with('status', 'Adicione a chave ao autenticador e confirme um código.');
    }

    public function enable(Request $r)
    {
        $v = $r->validate(['code' => 'required|string']);
        $setup = $r->session()->get('totp_setup');
        $step = $setup && $setup['expires'] >= now()->timestamp ? Totp::step($setup['secret'], $v['code']) : null;
        if ($step === null) {
            throw ValidationException::withMessages(['code' => 'Código inválido ou configuração expirada.']);
        }
        $raw = [];
        for ($i = 0; $i < 8; $i++) {
            $raw[] = bin2hex(random_bytes(10));
        }DB::transaction(function () use ($r, $setup, $step, $raw) {
            $u = User::lockForUpdate()->findOrFail($r->user()->id);
            abort_if($u->totp_secret, 422);
            $u->forceFill(['totp_secret' => $setup['secret'], 'totp_last_step' => $step, 'recovery_codes' => array_map(fn ($c) => hash('sha256', $c), $raw)])->save();
        });
        $r->session()->forget('totp_setup');
        Audit::record('auth.two_factor_enabled', 'user:'.$r->user()->id, [], $r->user()->id);

        return back()->with('recovery_codes', $raw)->with('status', 'Autenticação em duas etapas ativada. Guarde os códigos de recuperação.');
    }

    public function disable(Request $r)
    {
        $v = $r->validate(['password' => 'required|current_password', 'code' => 'required|string']);
        DB::transaction(function () use ($r, $v) {
            $u = User::lockForUpdate()->findOrFail($r->user()->id);
            $step = $u->totp_secret ? Totp::step($u->totp_secret, $v['code'], $u->totp_last_step) : null;
            if ($step === null) {
                throw ValidationException::withMessages(['code' => 'Código inválido ou já utilizado.']);
            }
            $u->forceFill(['totp_secret' => null, 'totp_last_step' => -1, 'recovery_codes' => null])->save();
        });
        Audit::record('auth.two_factor_disabled', 'user:'.$r->user()->id, [], $r->user()->id);

        return back()->with('status', 'Autenticação em duas etapas desativada.');
    }
}
