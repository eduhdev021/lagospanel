<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WalletEntry;
use App\Services\Audit;
use App\Services\Billing;
use App\Services\Checkout;
use App\Services\Gateways;
use App\Services\SupportDesk;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PortalController extends Controller
{
    public function store()
    {
        return view('store', ['products' => Product::with(['options.values'])->where('active', true)->orderBy('id')->paginate(12)]);
    }

    public function dashboard(Request $r)
    {
        $u = $r->user();

        return view('client.dashboard', ['services' => $u->services()->latest()->limit(5)->get(), 'open' => $u->invoices()->whereIn('status', ['unpaid', 'overdue'])->count(), 'active' => $u->services()->where('status', 'active')->count(), 'tickets' => $u->tickets()->where('status', '!=', 'closed')->count()]);
    }

    public function order(Request $r, Checkout $checkout)
    {
        $r->merge(['value_ids' => array_values(array_filter((array) $r->input('value_ids', []), fn ($v) => $v !== null && $v !== ''))]);
        $v = $r->validate(['product_id' => 'required|integer', 'quantity' => 'required|integer|min:1|max:10', 'request_key' => 'required|uuid', 'coupon' => 'nullable|string|max:40', 'value_ids' => 'sometimes|array|max:20', 'value_ids.*' => 'integer|min:1']);
        $invoice = $checkout->create($r->user(), (int) $v['product_id'], (int) $v['quantity'], $v['request_key'], $v['coupon'] ?? null, $v['value_ids'] ?? []);

        return redirect()->route('invoices.show', $invoice)->with('status', 'Pedido criado.');
    }

    public function invoices(Request $r)
    {
        return view('client.invoices', ['invoices' => $r->user()->invoices()->latest()->paginate(15)]);
    }

    public function invoice(Request $r, Invoice $invoice)
    {
        abort_unless($invoice->user_id === $r->user()->id, 404);

        return view('client.invoice', ['invoice' => $invoice->load('services', 'payments')]);
    }

    public function walletPay(Request $r, Invoice $invoice, Billing $billing)
    {
        $billing->payWithWallet($r->user(), $invoice->id);

        return back()->with('status', 'Pagamento registrado com saldo.');
    }

    public function gateway(Request $r, Invoice $invoice, Gateways $gateways)
    {
        abort_unless($invoice->user_id === $r->user()->id, 404);
        $v = $r->validate(['gateway' => 'required|in:stripe,mercadopago']);

        return redirect()->away($gateways->checkout($invoice, $v['gateway']));
    }

    public function services(Request $r)
    {
        return view('client.services', ['services' => $r->user()->services()->latest()->paginate(15)]);
    }

    public function cancellation(Request $r, Service $service)
    {
        abort_unless($service->user_id === $r->user()->id, 404);
        $v = $r->validate(['reason' => 'required|string|min:5|max:2000']);
        abort_if($service->status === 'cancelled', 422);
        $service->update(['cancellation_requested_at' => now(), 'cancellation_reason' => $v['reason'], 'auto_renew' => false]);
        Audit::record('service.cancellation_requested', 'service:'.$service->id, [], $r->user()->id);

        return back()->with('status', 'Pedido de cancelamento recebido. A equipe fará a análise; nenhum recurso foi apagado.');
    }

    public function tickets(Request $r)
    {
        return view('client.tickets', ['tickets' => $r->user()->tickets()->with(['replies' => fn ($q) => $q->where('is_internal', false)->latest('id')->limit(20)->with('user', 'attachments'), 'attachments' => fn ($q) => $q->where('is_internal', false)->whereNull('ticket_reply_id')])->latest()->paginate(10)]);
    }

    public function ticketCreate(Request $r)
    {
        $v = $r->validate(['subject' => 'required|string|max:180', 'body' => 'required|string|max:10000', 'department' => 'required|in:support,billing', 'priority' => 'sometimes|in:low,normal,high,urgent'] + SupportDesk::FILE_RULES);
        $desk = app(SupportDesk::class);
        $desk->open($r->user(), $v, $desk->prepare($r->file('attachments', [])));

        return back()->with('status', 'Ticket criado.');
    }

    public function reply(Request $r, Ticket $ticket)
    {
        abort_unless($ticket->user_id === $r->user()->id, 404);
        $v = $r->validate(['body' => 'required|string|max:10000'] + SupportDesk::FILE_RULES);
        $desk = app(SupportDesk::class);
        $desk->reply($ticket, $r->user(), $v['body'], $desk->prepare($r->file('attachments', [])));

        return back()->with('status', 'Resposta enviada.');
    }

    public function profile(Request $r)
    {
        return view('client.profile', ['entries' => WalletEntry::where('user_id', $r->user()->id)->latest()->paginate(15)]);
    }

    public function profileUpdate(Request $r)
    {
        $v = $r->validate(['name' => 'required|string|max:100']);
        $r->user()->update($v);

        return back()->with('status', 'Nome atualizado.');
    }

    public function deposit(Request $r)
    {
        $v = $r->validate(['amount' => 'required|string', 'request_key' => 'required|uuid']);
        $amount = Money::parse($v['amount']);
        if ($amount < 100 || $amount > 1000000) {
            throw ValidationException::withMessages(['amount' => 'Recargas de R$ 1 a R$ 10.000.']);
        }
        $ref = 'deposit-request:'.$r->user()->id.':'.$v['request_key'];
        $invoice = DB::transaction(function () use ($r, $amount, $ref) {
            $u = User::lockForUpdate()->findOrFail($r->user()->id);
            $audit = AuditEvent::where('event', 'deposit.created')->where('subject', $ref)->first();
            if ($audit) {
                return Invoice::findOrFail($audit->context['invoice']);
            }
            $i = $u->invoices()->create(['type' => 'deposit', 'total_minor' => $amount, 'due_date' => today()->addDays(3), 'snapshot' => [['name' => 'Recarga de saldo', 'quantity' => 1, 'unit_minor' => $amount]]]);
            Audit::record('deposit.created', $ref, ['invoice' => $i->id], $u->id);

            return $i;
        }, 5);

        return redirect()->route('invoices.show', $invoice);
    }
}
