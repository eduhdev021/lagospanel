<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\User;
use App\Services\Audit;
use App\Services\SupportDesk;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApiAccessController extends Controller
{
    const SCOPES = ['services:read', 'invoices:read', 'tickets:write'];

    public function index(Request $r)
    {
        return view('client.api', ['tokens' => ApiToken::where('user_id', $r->user()->id)->latest()->get(), 'scopes' => self::SCOPES]);
    }

    public function create(Request $r)
    {
        $v = $r->validate(['name' => 'required|string|max:100', 'password' => 'required|string|max:256', 'code' => 'nullable|string|max:30', 'days' => 'required|integer|min:1|max:90', 'scopes' => 'required|array|min:1', 'scopes.*' => ['string', Rule::in(self::SCOPES)]]);
        $raw = 'lp_'.bin2hex(random_bytes(32));
        DB::transaction(function () use ($r, $v, $raw) {
            $u = User::lockForUpdate()->findOrFail($r->user()->id);
            if (! Hash::check($v['password'], $u->password)) {
                throw ValidationException::withMessages(['password' => 'Senha atual inválida.']);
            }if ($u->totp_secret) {
                $step = Totp::step($u->totp_secret, $v['code'] ?? '', $u->totp_last_step);
                if ($step === null) {
                    throw ValidationException::withMessages(['code' => 'Informe um código novo do autenticador.']);
                }$u->totp_last_step = $step;
                $u->save();
            }if (ApiToken::where('user_id', $u->id)->count() >= 20) {
                throw ValidationException::withMessages(['token' => 'Revogue um token antes de criar outro; limite de 20.']);
            }$token = ApiToken::create(['user_id' => $u->id, 'name' => $v['name'], 'token_hash' => hash('sha256', $raw), 'password_fingerprint' => $u->apiCredentialFingerprint(), 'scopes' => array_values(array_unique($v['scopes'])), 'expires_at' => now()->addDays((int) $v['days'])]);
            Audit::record('api.token_created', 'token:'.$token->id, [], $u->id);
        });

        return back()->with('api_token', $raw)->with('status', 'Token criado. Copie agora: ele não será exibido novamente.');
    }

    public function revoke(Request $r, ApiToken $token)
    {
        abort_unless($token->user_id === $r->user()->id, 404);
        Audit::record('api.token_revoked', 'token:'.$token->id, [], $r->user()->id);
        $token->delete();

        return back()->with('status', 'Token revogado.');
    }

    public function services(Request $r)
    {
        return $r->user()->services()->select(['id', 'name', 'status', 'cycle', 'price_minor', 'next_due', 'created_at'])->orderBy('id')->paginate(50);
    }

    public function invoices(Request $r)
    {
        return $r->user()->invoices()->select(['id', 'type', 'status', 'total_minor', 'currency', 'due_date', 'paid_at', 'expires_at'])->orderBy('id')->paginate(50);
    }

    public function ticket(Request $r)
    {
        $v = $r->validate(['subject' => 'required|string|max:180', 'body' => 'required|string|max:10000', 'department' => 'required|in:support,billing', 'priority' => 'sometimes|in:low,normal,high,urgent', 'attachments' => 'prohibited']);
        $ticket = app(SupportDesk::class)->open($r->user(), $v);

        return response()->json(['id' => $ticket->id, 'status' => 'open'], 201);
    }
}
