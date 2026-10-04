<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\User;
use App\Services\Audit;
use App\Services\Affiliates;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    public function clientIndex(Request $r)
    {
        $affiliate = Affiliate::where('user_id', $r->user()->id)->first();
        $commissions = $affiliate ? $affiliate->commissions()->with('referredUser', 'invoice')->latest()->paginate(15) : collect();
        $referralsCount = User::where('referred_by_id', $r->user()->id)->count();

        return view('client.affiliates', [
            'affiliate' => $affiliate,
            'commissions' => $commissions,
            'referralsCount' => $referralsCount,
            'minWithdrawal' => Affiliates::MIN_WITHDRAWAL_MINOR,
        ]);
    }

    public function enroll(Request $r, Affiliates $affiliates)
    {
        $affiliates->enroll($r->user());

        return back()->with('status', 'Programa de afiliados ativado na sua conta.');
    }

    public function withdraw(Request $r, Affiliates $affiliates)
    {
        $withdrawn = $affiliates->withdrawToWallet($r->user());

        return back()->with('status', 'Comissão de '.brl($withdrawn).' transferida para sua carteira.');
    }

    public function adminIndex()
    {
        return view('admin.affiliates', [
            'affiliates' => Affiliate::with('user')->latest()->paginate(20),
            'commissions' => AffiliateCommission::with('affiliate.user', 'referredUser', 'invoice')->latest()->limit(30)->get(),
        ]);
    }

    public function adminUpdate(Request $r, Affiliate $affiliate)
    {
        $v = $r->validate([
            'rate_percent' => 'required|integer|min:1|max:80',
        ]);
        $affiliate->update([
            'rate_percent' => (int) $v['rate_percent'],
            'active' => $r->boolean('active'),
        ]);
        Audit::record('affiliate.updated', 'affiliate:'.$affiliate->id, ['rate_percent' => $affiliate->rate_percent, 'active' => $affiliate->active], $r->user()->id);

        return back()->with('status', 'Configuração do afiliado atualizada.');
    }
}
