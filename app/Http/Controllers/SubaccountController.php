<?php

namespace App\Http\Controllers;

use App\Models\AccountContact;
use App\Models\User;
use App\Services\Audit;
use App\Services\CustomerConfirmation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubaccountController extends Controller
{
    public function index(Request $r)
    {
        return view('client.subaccounts', [
            'contacts' => $r->user()->contacts()->with('contactUser')->latest()->get(),
            'delegated' => AccountContact::with('owner')->where(fn ($q) => $q->where('contact_user_id', $r->user()->id)->orWhere('email', $r->user()->email))->where('active', true)->get(),
            'permissions' => AccountContact::PERMISSIONS,
        ]);
    }

    public function store(Request $r, CustomerConfirmation $confirmation)
    {
        $r->merge(['email' => strtolower(trim((string) $r->input('email')))]);
        $v = $r->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
            'permissions' => 'required|array|min:1',
            'permissions.*' => ['required', 'string', Rule::in(array_keys(AccountContact::PERMISSIONS))],
        ]);
        if ($v['email'] === strtolower($r->user()->email)) {
            throw ValidationException::withMessages(['email' => 'Você já é o titular desta conta.']);
        }
        $confirmation->verify($r);
        $linkedUser = User::where('email', $v['email'])->first();

        $contact = AccountContact::updateOrCreate(
            ['owner_id' => $r->user()->id, 'email' => $v['email']],
            [
                'contact_user_id' => $linkedUser?->id,
                'name' => $v['name'],
                'permissions' => array_values(array_unique($v['permissions'])),
                'receive_billing_emails' => $r->boolean('receive_billing_emails', true),
                'active' => true,
            ]
        );
        Audit::record('subaccount.saved', 'subaccount:'.$contact->id, ['email' => $v['email'], 'permissions' => $contact->permissions], $r->user()->id);

        return back()->with('status', 'Subconta / contato autorizado salvo.');
    }

    public function destroy(Request $r, AccountContact $contact)
    {
        abort_unless($contact->owner_id === $r->user()->id, 404);
        $contact->delete();
        Audit::record('subaccount.deleted', 'subaccount:'.$contact->id, [], $r->user()->id);

        return back()->with('status', 'Subconta removida.');
    }
}
