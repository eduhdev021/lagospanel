<?php

namespace App\Services;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CustomerConfirmation
{
    public function verify(Request $r): User
    {
        $v = $r->validate(['password' => 'required|string|max:256', 'code' => 'nullable|string|max:30']);

        return DB::transaction(function () use ($r, $v) {
            $u = User::lockForUpdate()->findOrFail($r->user()->id);
            abort_if($u->isStaff() || $u->password_reset_required, 403);
            if (! Hash::check($v['password'], $u->password)) {
                throw ValidationException::withMessages(['password' => 'Senha atual inválida.']);
            }
            if ($u->totp_secret) {
                $step = Totp::step($u->totp_secret, $v['code'] ?? '', $u->totp_last_step);
                if ($step === null) {
                    throw ValidationException::withMessages(['code' => 'Informe um código novo do autenticador.']);
                }$u->forceFill(['totp_last_step' => $step])->save();
            }

            return $u;
        }, 5);
    }
}
