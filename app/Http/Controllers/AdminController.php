<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Connector;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Operation;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use App\Provisioning\AaPanelConfig;
use App\Provisioning\NativeConfig;
use App\Services\Audit;
use App\Services\Billing;
use App\Services\Provisioning;
use App\Services\SupportDesk;
use App\Support\Cycle;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function dashboard()
    {
        return view('admin.dashboard', ['users' => User::count(), 'unpaid' => Invoice::whereIn('status', ['unpaid', 'overdue'])->count(), 'paid' => Payment::sum('amount_minor'), 'review' => Operation::where('status', 'review')->count(), 'events' => AuditEvent::latest('id')->limit(15)->get()]);
    }

    public function products(?Product $product = null)
    {
        return view('admin.products', ['products' => Product::latest()->paginate(15), 'editing' => $product, 'connectors' => Connector::orderBy('name')->get()]);
    }

    public function saveProduct(Request $r, ?Product $product = null)
    {
        $v = $r->validate(['name' => 'required|string|max:180', 'slug' => ['required', 'alpha_dash', 'max:180', Rule::unique('products', 'slug')->ignore($product?->id)], 'category' => 'nullable|string|max:100', 'description' => 'nullable|string|max:10000', 'price' => 'required|string', 'setup' => 'nullable|string', 'cycle' => ['required', Rule::in(array_keys(Cycle::LABELS))], 'stock' => 'nullable|integer|min:0|max:100000', 'connector_id' => 'nullable|integer|exists:connectors,id', 'max_per_user' => 'nullable|integer|min:1|max:10000', 'allow_quantity' => 'sometimes|boolean', 'allow_upgrade' => 'sometimes|boolean', 'cpanel_plan' => 'nullable|string|max:100', 'cpanel_domain_suffix' => 'nullable|string|max:190', 'aapanel_domain_suffix' => 'nullable|string|max:190', 'aapanel_php_version' => 'nullable|string|max:2', 'ptero_config' => 'nullable|string|max:20000', 'hosting_config' => 'nullable|string|max:10000', 'vps_config' => 'nullable|string|max:10000']);
        $data = ['name' => $v['name'], 'slug' => $v['slug'], 'category' => $v['category'] ?? null, 'description' => $v['description'] ?? '', 'price_minor' => Money::parse($v['price']), 'setup_minor' => Money::parse($v['setup'] ?? '0'), 'cycle' => $v['cycle'], 'stock' => $v['stock'] ?? null, 'connector_id' => $v['connector_id'] ?? null, 'active' => $r->boolean('active'), 'max_per_user' => $v['max_per_user'] ?? null, 'allow_quantity' => $r->boolean('allow_quantity', true), 'allow_upgrade' => $r->boolean('allow_upgrade', true)];
        $data['provisioning'] = NativeConfig::product(isset($v['connector_id']) ? Connector::find($v['connector_id']) : null, $v);
        $product?->exists ? $product->update($data) : $product = Product::create($data);
        Audit::record('product.saved', 'product:'.$product->id, [], $r->user()->id);

        return redirect()->route('admin.products')->with('status', 'Produto salvo.');
    }

    public function invoices()
    {
        return view('admin.invoices', ['invoices' => Invoice::with('user')->latest()->paginate(20)]);
    }

    public function paid(Request $r, Invoice $invoice, Billing $billing)
    {
        $v = $r->validate(['note' => 'required|string|min:5|max:500']);
        $billing->settle($invoice->id, 'manual', 'invoice:'.$invoice->id, $invoice->total_minor, 'BRL', $r->user()->id, $v['note']);

        return back()->with('status', 'Recebimento manual registrado. A ativação é uma etapa separada.');
    }

    public function services()
    {
        return view('admin.services', ['services' => Service::with('user', 'connector')->latest()->paginate(20)]);
    }

    public function serviceAction(Request $r, Service $service, Provisioning $provisioning)
    {
        $v = $r->validate(['status' => 'required|in:active,suspended,cancelled', 'note' => 'required|string|min:5|max:500']);
        if ($service->connector_id) {
            if ($v['status'] === 'active') {
                abort_unless($service->invoices()->where('status', 'paid')->exists(), 422, 'Confirme o pagamento.');
            }
            $action = ['active' => $service->remote_id ? 'unsuspend' : 'create', 'suspended' => 'suspend', 'cancelled' => 'terminate'][$v['status']];
            if ($action === 'terminate' && in_array($service->connector?->driver, ['cpanel', 'aapanel', 'pterodactyl', 'directadmin', 'plesk', 'proxmox', 'virtualizor'], true)) {
                $r->validate(['confirm_termination' => 'accepted']);
            }
            $provisioning->enqueue($service, $action, 'admin:'.Str::uuid());
            Audit::record('operation.requested', 'service:'.$service->id, ['action' => $action, 'note' => $v['note']], $r->user()->id);
        } else {
            $provisioning->manuallySet($service, $v['status'], $r->user()->id, $v['note']);
        }

        return back()->with('status', 'Ação registrada. Operações remotas são processadas pela fila.');
    }

    public function tickets(Request $r)
    {
        $v = $r->validate(['status' => 'nullable|in:open,customer_reply,answered,closed', 'department' => 'nullable|in:support,billing', 'priority' => 'nullable|in:low,normal,high,urgent', 'overdue' => 'nullable|in:1', 'mine' => 'nullable|in:1']);
        $q = Ticket::with(['user', 'assignee', 'replies' => fn ($q) => $q->latest('id')->limit(20)->with('user', 'attachments'), 'attachments' => fn ($q) => $q->whereNull('ticket_reply_id')]);
        foreach (['status', 'department', 'priority'] as $field) {
            if (! empty($v[$field])) {
                $q->where($field, $v[$field]);
            }
        }
        if ($r->boolean('overdue')) {
            $q->whereIn('status', ['open', 'customer_reply'])->where('response_due_at', '<', now());
        }
        if ($r->boolean('mine')) {
            $q->where('assigned_to', $r->user()->id);
        }
        $staff = User::with('staffRole')->where(fn ($q) => $q->where('is_admin', true)->orWhereNotNull('staff_role_id'))->get()->filter(fn ($u) => $u->hasPermission('support.view') && $u->hasPermission('support.manage'));

        return view('admin.tickets', ['tickets' => $q->latest()->paginate(10)->withQueryString(), 'staff' => $staff]);
    }

    public function reply(Request $r, Ticket $ticket)
    {
        $v = $r->validate(['body' => 'required|string|max:10000', 'status' => 'required|in:answered,closed', 'internal' => 'sometimes|boolean'] + SupportDesk::FILE_RULES);
        $desk = app(SupportDesk::class);
        $desk->reply($ticket, $r->user(), $v['body'], $desk->prepare($r->file('attachments', [])), true, $v['status'], $r->boolean('internal'));

        return back()->with('status', $r->boolean('internal') ? 'Nota interna registrada.' : 'Resposta registrada.');
    }

    public function users()
    {
        return view('admin.users', ['users' => User::latest()->paginate(25)]);
    }

    public function operations()
    {
        return view('admin.operations', ['operations' => Operation::with('service.connector')->latest()->paginate(25)]);
    }

    public function connectors()
    {
        return view('admin.connectors', ['connectors' => Connector::all()]);
    }

    public function connectorCreate(Request $r)
    {
        $v = $r->validate(['name' => 'required|string|max:120', 'endpoint' => 'required|url:https|max:255', 'token' => 'required|string|min:16|max:2000', 'driver' => 'sometimes|in:json,cpanel,aapanel,pterodactyl,directadmin,plesk,proxmox,virtualizor', 'whm_username' => 'nullable|string|max:32', 'account_prefix' => 'nullable|string|max:2', 'client_url' => 'nullable|url:https|max:255']);
        abort_if(parse_url($v['endpoint'], PHP_URL_USER) || parse_url($v['endpoint'], PHP_URL_PASS) || parse_url($v['endpoint'], PHP_URL_QUERY) || parse_url($v['endpoint'], PHP_URL_FRAGMENT) || preg_match('/[\r\n]/', $v['token']), 422);
        $driver = $v['driver'] ?? 'json';
        $settings = null;
        if (in_array($driver, ['proxmox', 'virtualizor'], true)) {
            $v['endpoint'] = NativeConfig::origin($v['endpoint'], [8006, 4085, 4083, 443]);
            if ($r->boolean('active')) {
                $r->validate(['ack_native' => 'accepted']);
            }
        }
        if ($driver === 'cpanel') {
            $r->validate(['whm_username' => ['required', 'regex:/^[a-z][a-z0-9_]{0,31}$/D'], 'account_prefix' => ['required', 'regex:/^[a-z]{2}$/D'], 'client_url' => 'required|url:https|max:255']);
            $v['endpoint'] = NativeConfig::origin($v['endpoint'], [2087, 443]);
            $settings = ['username' => $v['whm_username'], 'prefix' => $v['account_prefix'], 'client_url' => NativeConfig::origin($v['client_url'], [2083, 443])];
            if ($r->boolean('active')) {
                $r->validate(['ack_native' => 'accepted']);
            }
        }
        if ($driver === 'aapanel') {
            $r->validate(['account_prefix' => ['required', 'regex:/^[a-z]{2}$/D']]);
            $v['endpoint'] = AaPanelConfig::origin($v['endpoint']);
            $settings = ['prefix' => $v['account_prefix']];
            if ($r->boolean('active')) {
                $r->validate(['ack_native' => 'accepted']);
            }
        }
        if (in_array($driver, ['directadmin', 'plesk'], true)) {
            $r->validate(['account_prefix' => ['required', 'regex:/^[a-z]{2}$/D']]);
            $v['endpoint'] = NativeConfig::origin($v['endpoint'], $driver === 'directadmin' ? [2222, 443] : [8443, 443]);
            $settings = ['prefix' => $v['account_prefix']];
            if ($driver === 'directadmin') {
                $r->validate(['whm_username' => ['required', 'regex:/^[a-z][a-z0-9]{0,31}$/D']]);
                $settings['username'] = $v['whm_username'];
            }
            if ($r->boolean('active')) {
                $r->validate(['ack_native' => 'accepted']);
            }
        }
        if ($driver === 'pterodactyl') {
            $v['endpoint'] = AaPanelConfig::origin($v['endpoint']);
            if ($r->boolean('active')) {
                $r->validate(['ack_native' => 'accepted']);
            }
        }
        Connector::create(['name' => $v['name'], 'endpoint' => $v['endpoint'], 'token' => $v['token'], 'driver' => $driver, 'settings' => $settings, 'active' => $r->boolean('active')]);
        Audit::record('connector.created', 'connector', [], $r->user()->id);

        return back()->with('status', 'Integração cadastrada. Homologue antes de vincular a um produto.');
    }

    public function coupons()
    {
        return view('admin.coupons', ['coupons' => Coupon::latest()->paginate(20)]);
    }

    public function couponCreate(Request $r)
    {
        $r->merge(['code' => strtoupper((string) $r->input('code'))]);
        $v = $r->validate(['code' => 'required|alpha_dash|max:40|unique:coupons', 'kind' => 'required|in:percent,fixed', 'percent' => 'nullable|required_if:kind,percent|integer|min:1|max:100', 'fixed' => 'nullable|required_if:kind,fixed|string', 'per_user_limit' => 'nullable|integer|min:1', 'max_uses' => 'nullable|integer|min:1', 'expires_at' => 'nullable|date|after:now']);
        Coupon::create(['code' => $v['code'], 'kind' => $v['kind'], 'percent' => $v['kind'] === 'percent' ? $v['percent'] : 0, 'fixed_minor' => $v['kind'] === 'fixed' ? Money::parse($v['fixed']) : 0, 'per_user_limit' => $v['per_user_limit'] ?? null, 'max_uses' => $v['max_uses'] ?? null, 'expires_at' => $v['expires_at'] ?? null, 'active' => true]);

        return back()->with('status', 'Cupom criado.');
    }

    public function audit()
    {
        return view('admin.audit', ['events' => AuditEvent::latest('id')->paginate(30)]);
    }
}
