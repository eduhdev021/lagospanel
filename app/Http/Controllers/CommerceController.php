<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Invoice;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\User;
use App\Services\Checkout;
use App\Services\OrderLifecycle;
use App\Services\Pricing;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommerceController extends Controller
{
    public function cart(Request $r)
    {
        $items = CartItem::with('product')->where('user_id', $r->user()->id)->orderBy('id')->get();
        $quotes = [];
        $total = 0;
        foreach ($items as $item) {
            try {
                $q = app(Pricing::class)->quote($item->product, $item->value_ids);
                $q['total_minor'] = ($q['recurring_minor'] + $q['setup_minor']) * $item->quantity;
                $total += $q['total_minor'];
                $quotes[$item->id] = $q;
            } catch (ValidationException) {
                $quotes[$item->id] = ['error' => 'Opção indisponível. Remova este item e escolha novamente.'];
            }
        }

        return view('client.cart', compact('items', 'quotes', 'total'));
    }

    public function add(Request $r)
    {
        $r->merge(['value_ids' => array_values(array_filter((array) $r->input('value_ids', []), fn ($v) => $v !== null && $v !== ''))]);
        $v = $r->validate(['product_id' => 'required|integer|exists:products,id', 'quantity' => 'required|integer|min:1|max:10', 'value_ids' => 'sometimes|array|max:20', 'value_ids.*' => 'integer|min:1']);
        $ids = array_map('intval', $v['value_ids'] ?? []);
        sort($ids);
        DB::transaction(function () use ($r, $v, $ids) {
            User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            $p = Product::findOrFail($v['product_id']);
            abort_unless($p->active, 422);
            app(Pricing::class)->quote($p, $ids);
            $signature = hash('sha256', json_encode([$p->id, $ids]));
            $item = CartItem::firstOrNew(['user_id' => $r->user()->id, 'signature' => $signature]);
            if (! $item->exists && CartItem::where('user_id', $r->user()->id)->count() >= 50) {
                throw ValidationException::withMessages(['cart' => 'Limite de 50 configurações no carrinho.']);
            }$quantity = ($item->quantity ?? 0) + (int) $v['quantity'];
            if ($quantity > 10 || (! $p->allow_quantity && $quantity > 1)) {
                throw ValidationException::withMessages(['quantity' => 'Quantidade não permitida.']);
            }$item->fill(['product_id' => $p->id, 'quantity' => $quantity, 'value_ids' => $ids])->save();
        });

        return redirect()->route('cart.index')->with('status', 'Item adicionado. Preços e disponibilidade serão conferidos na finalização.');
    }

    public function update(Request $r, CartItem $item)
    {
        abort_unless($item->user_id === $r->user()->id, 404);
        $v = $r->validate(['quantity' => 'required|integer|min:1|max:10']);
        $item->update($v);

        return back()->with('status', 'Quantidade atualizada.');
    }

    public function remove(Request $r, CartItem $item)
    {
        abort_unless($item->user_id === $r->user()->id, 404);
        $item->delete();

        return back()->with('status', 'Item removido.');
    }

    public function checkout(Request $r, Checkout $checkout)
    {
        $v = $r->validate(['request_key' => 'required|uuid', 'coupon' => 'nullable|string|max:40']);
        $invoice = DB::transaction(function () use ($r, $v, $checkout) {
            User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            $old = Order::where('user_id', $r->user()->id)->where('request_key', $v['request_key'])->lockForUpdate()->first();
            if ($old) {
                if ($old->source !== 'cart' || $old->coupon_code !== strtoupper(trim($v['coupon'] ?? ''))) {
                    throw ValidationException::withMessages(['cart' => 'Identificador já utilizado em outro pedido.']);
                }

                return $old->invoice;
            }$items = CartItem::where('user_id', $r->user()->id)->orderBy('id')->lockForUpdate()->get();
            if ($items->isEmpty()) {
                $old = Order::where('user_id', $r->user()->id)->where('request_key', $v['request_key'])->where('source', 'cart')->first();
                if ($old) {
                    if ($old->coupon_code !== strtoupper(trim($v['coupon'] ?? ''))) {
                        throw ValidationException::withMessages(['cart' => 'Cupom diferente do pedido já finalizado.']);
                    }

                    return $old->invoice;
                }throw ValidationException::withMessages(['cart' => 'Carrinho vazio.']);
            }$invoice = $checkout->cart($r->user(), $items->map(fn ($i) => ['product_id' => $i->product_id, 'quantity' => $i->quantity, 'value_ids' => $i->value_ids])->all(), $v['request_key'], $v['coupon'] ?? null);
            CartItem::whereIn('id', $items->modelKeys())->delete();

            return $invoice;
        }, 5);

        return redirect()->route('invoices.show', $invoice);
    }

    public function cancel(Request $r, Invoice $invoice, OrderLifecycle $lifecycle)
    {
        abort_unless($invoice->user_id === $r->user()->id, 404);
        $lifecycle->cancel($invoice->id, $r->user()->id, 'Cancelamento solicitado pelo cliente antes do pagamento');

        return back()->with('status', 'Pedido cancelado e reservas liberadas.');
    }

    public function options(Product $product)
    {
        return view('admin.options', ['product' => $product->load('options.values')]);
    }

    public function createOption(Request $r, Product $product)
    {
        $v = $r->validate(['name' => 'required|string|max:100']);
        DB::transaction(function () use ($r, $product, $v) {
            Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $product->options()->create($v + ['required' => $r->boolean('required')]);
        });

        return back()->with('status', 'Opção criada. Adicione valores antes de disponibilizar pedidos.');
    }

    public function createValue(Request $r, Product $product, ProductOption $option)
    {
        abort_unless($option->product_id === $product->id, 404);
        $v = $r->validate(['label' => 'required|string|max:100', 'recurring' => 'required|string', 'setup' => 'required|string']);
        DB::transaction(function () use ($product, $option, $v) {
            Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $option->values()->create(['label' => $v['label'], 'recurring_minor' => Money::parse($v['recurring']), 'setup_minor' => Money::parse($v['setup'])]);
        });

        return back()->with('status', 'Valor adicionado.');
    }

    public function toggleValue(Request $r, Product $product, OptionValue $value)
    {
        $option = ProductOption::findOrFail($value->product_option_id);
        abort_unless($option->product_id === $product->id, 404);
        DB::transaction(function () use ($product, $value) {
            Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $locked = OptionValue::lockForUpdate()->findOrFail($value->id);
            $locked->update(['active' => ! $locked->active]);
        });

        return back()->with('status', 'Disponibilidade alterada; serviços já contratados preservam seu snapshot.');
    }
}
