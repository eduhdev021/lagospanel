<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\ServiceUpgrade;
use App\Services\Audit;
use App\Services\ServiceUpgrades;
use App\Support\Cycle;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpgradeAddonController extends Controller
{
    public function requestUpgrade(Request $r, Service $service, ServiceUpgrades $upgrades)
    {
        $v = $r->validate([
            'target_product_id' => 'required|integer|exists:products,id',
            'request_key' => 'required|uuid',
        ]);
        $target = Product::findOrFail((int) $v['target_product_id']);
        $upgrade = $upgrades->request($r->user(), $service, $target, $v['request_key']);

        if ($upgrade->invoice_id) {
            return redirect()->route('invoices.show', $upgrade->invoice_id)->with('status', 'Fatura de upgrade proporcional gerada.');
        }

        return back()->with('status', 'Plano alterado proporcionalmente. Eventual crédito foi lançado na sua carteira.');
    }

    public function orderAddon(Request $r, Service $service)
    {
        abort_unless($service->user_id === $r->user()->id, 404);
        if ($service->status !== 'active') {
            throw ValidationException::withMessages(['service' => 'Adicionais só podem ser contratados para serviços ativos.']);
        }
        $v = $r->validate(['addon_id' => 'required|integer|exists:product_addons,id']);
        $addon = ProductAddon::where('active', true)->findOrFail((int) $v['addon_id']);

        $invoice = DB::transaction(function () use ($r, $service, $addon) {
            $total = $addon->price_minor + $addon->setup_minor;
            $expiry = now()->addMinutes((int) config('lagos.reservation_minutes', 1440));
            $inv = Invoice::create([
                'user_id' => $r->user()->id,
                'type' => 'addon',
                'total_minor' => $total,
                'due_date' => $expiry->toDateString(),
                'expires_at' => $expiry,
                'snapshot' => [[
                    'name' => 'Addon '.$addon->name.' (Serviço #'.$service->id.')',
                    'quantity' => 1,
                    'unit_minor' => $total,
                ]],
            ]);
            ServiceAddon::create([
                'user_id' => $r->user()->id,
                'service_id' => $service->id,
                'product_addon_id' => $addon->id,
                'invoice_id' => $inv->id,
                'name' => $addon->name,
                'price_minor' => $addon->price_minor,
                'cycle' => $addon->cycle,
                'status' => 'pending',
            ]);
            Audit::record('service.addon_ordered', 'service:'.$service->id, ['addon_id' => $addon->id, 'invoice_id' => $inv->id], $r->user()->id);

            return $inv;
        }, 5);

        return redirect()->route('invoices.show', $invoice)->with('status', 'Fatura do adicional gerada.');
    }

    public function adminIndex()
    {
        return view('admin.addons', [
            'addons' => ProductAddon::latest()->get(),
            'upgrades' => ServiceUpgrade::with('user', 'service', 'fromProduct', 'toProduct', 'invoice')->latest()->paginate(20),
        ]);
    }

    public function saveAddon(Request $r)
    {
        $v = $r->validate([
            'name' => 'required|string|max:180',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|string',
            'setup' => 'nullable|string',
            'cycle' => ['required', Rule::in(array_keys(Cycle::LABELS))],
        ]);
        $addon = ProductAddon::create([
            'name' => $v['name'],
            'description' => $v['description'] ?? '',
            'price_minor' => Money::parse($v['price']),
            'setup_minor' => Money::parse($v['setup'] ?? '0'),
            'cycle' => $v['cycle'],
            'active' => $r->boolean('active', true),
        ]);
        Audit::record('addon.created', 'addon:'.$addon->id, [], $r->user()->id);

        return back()->with('status', 'Addon salvo.');
    }
}
