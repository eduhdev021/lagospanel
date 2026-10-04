<?php

namespace App\Services;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AdminConfirmation
{
    public function verify(Request $r): void
    {
        abort_unless($r->user()?->is_admin, 403);
        $v = $r->validate(['password' => 'required|string|max:256', 'code' => 'nullable|string|max:30']);
        DB::transaction(function () use ($r, $v) {
            $u = User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($u->is_admin, 403);
            if (! Hash::check($v['password'], $u->password)) {
                throw ValidationException::withMessages(['password' => 'Senha atual inválida.']);
            }
            if ($u->totp_secret) {
                $step = Totp::step($u->totp_secret, $v['code'] ?? '', $u->totp_last_step);
                if ($step === null) {
                    throw ValidationException::withMessages(['code' => 'Informe um código novo do autenticador.']);
                }$u->forceFill(['totp_last_step' => $step])->save();
            }
        }, 5);
    }
}
