<?php

namespace App\Http\Controllers;

use App\Models\DomainRegistration;
use App\Models\DomainTld;
use App\Models\Product;
use App\Services\Audit;
use App\Services\CustomerConfirmation;
use App\Services\Domains;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function clientIndex(Request $r, Domains $domains)
    {
        $lookup = null;
        if ($r->filled('q')) {
            $lookup = $domains->lookup((string) $r->query('q'));
        }

        return view('client.domains', [
            'tlds' => DomainTld::where('active', true)->orderBy('tld')->get(),
            'domains' => $r->user()->domains()->with('tld')->latest()->paginate(15),
            'lookup' => $lookup,
            'revealedEpp' => session('revealed_epp'),
        ]);
    }

    public function order(Request $r, Domains $domains)
    {
        $v = $r->validate([
            'domain' => 'required|string|max:190',
            'operation_type' => 'required|in:register,transfer',
            'years' => 'required|integer|min:1|max:10',
            'ns1' => 'required|string|max:190',
            'ns2' => 'required|string|max:190',
            'ns3' => 'nullable|string|max:190',
            'ns4' => 'nullable|string|max:190',
            'epp_code' => 'nullable|string|max:120',
        ]);
        $reg = $domains->order(
            $r->user(),
            $v['domain'],
            $v['operation_type'],
            (int) $v['years'],
            [$v['ns1'], $v['ns2'], $v['ns3'] ?? '', $v['ns4'] ?? ''],
            $v['epp_code'] ?? null
        );

        return redirect()->route('invoices.show', $reg->invoice_id)->with('status', 'Pedido de domínio criado.');
    }

    public function updateNameservers(Request $r, DomainRegistration $domain, Domains $domains)
    {
        abort_unless($domain->user_id === $r->user()->id, 404);
        $v = $r->validate([
            'ns1' => 'required|string|max:190',
            'ns2' => 'required|string|max:190',
            'ns3' => 'nullable|string|max:190',
            'ns4' => 'nullable|string|max:190',
        ]);
        $ns = $domains->validateNameservers([$v['ns1'], $v['ns2'], $v['ns3'] ?? '', $v['ns4'] ?? '']);
        $domain->update(['nameservers' => $ns]);
        Audit::record('domain.nameservers_updated', 'domain:'.$domain->id, ['nameservers' => $ns], $r->user()->id);

        return back()->with('status', 'Nameservers atualizados.');
    }

    public function toggleLock(Request $r, DomainRegistration $domain)
    {
        abort_unless($domain->user_id === $r->user()->id, 404);
        $domain->update(['transfer_lock' => ! $domain->transfer_lock]);
        Audit::record('domain.lock_toggled', 'domain:'.$domain->id, ['transfer_lock' => $domain->transfer_lock], $r->user()->id);

        return back()->with('status', $domain->transfer_lock ? 'Bloqueio de transferência ativado.' : 'Bloqueio de transferência desativado.');
    }

    public function revealEpp(Request $r, DomainRegistration $domain, CustomerConfirmation $confirmation)
    {
        abort_unless($domain->user_id === $r->user()->id, 404);
        $confirmation->verify($r);
        Audit::record('domain.epp_revealed', 'domain:'.$domain->id, [], $r->user()->id);

        return back()->with('revealed_epp', ['id' => $domain->id, 'domain' => $domain->domain, 'code' => $domain->epp_code]);
    }

    public function renew(Request $r, DomainRegistration $domain, Domains $domains)
    {
        $v = $r->validate(['years' => 'required|integer|min:1|max:10']);
        $invoice = $domains->renew($r->user(), $domain, (int) $v['years']);

        return redirect()->route('invoices.show', $invoice)->with('status', 'Fatura de renovação de domínio gerada.');
    }

    public function adminIndex()
    {
        return view('admin.domains', [
            'tlds' => DomainTld::orderBy('tld')->get(),
            'domains' => DomainRegistration::with('user', 'tld')->latest()->paginate(20),
        ]);
    }

    public function saveTld(Request $r)
    {
        $v = $r->validate([
            'tld' => ['required', 'string', 'max:64', 'regex:/^\.?[a-z0-9-]+(\.[a-z0-9-]+)*$/i'],
            'register_price' => 'required|string',
            'transfer_price' => 'required|string',
            'renew_price' => 'required|string',
            'registrar' => 'required|in:manual,registrobr,enom,namecheap,resellerclub',
        ]);
        $tld = '.'.ltrim(strtolower(trim($v['tld'])), '.');
        $record = DomainTld::updateOrCreate(
            ['tld' => $tld],
            [
                'register_minor' => Money::parse($v['register_price']),
                'transfer_minor' => Money::parse($v['transfer_price']),
                'renew_minor' => Money::parse($v['renew_price']),
                'registrar' => $v['registrar'],
                'active' => $r->boolean('active', true),
            ]
        );
        Audit::record('domain.tld_saved', 'tld:'.$record->id, ['tld' => $tld], $r->user()->id);

        return back()->with('status', 'Extensão TLD salva.');
    }

    public function adminStatus(Request $r, DomainRegistration $domain)
    {
        $v = $r->validate(['status' => 'required|in:pending,active,expired,cancelled']);
        $updates = ['status' => $v['status']];
        if ($v['status'] === 'active' && ! $domain->expires_at) {
            $updates['expires_at'] = CarbonImmutable::today()->addYears($domain->years);
        }
        $domain->update($updates);
        Audit::record('domain.admin_status', 'domain:'.$domain->id, ['status' => $v['status']], $r->user()->id);

        return back()->with('status', 'Estado do domínio atualizado.');
    }
}
