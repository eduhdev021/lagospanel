<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\StockReservation;
use App\Models\User;
use App\Notifications\InvoiceNotice;
use App\Provisioning\NativeConfig;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class Checkout
{
    public function create(User $user, int $productId, int $quantity, string $key, ?string $code = null, array $valueIds = []): Invoice
    {
        $code = strtoupper(trim($code ?? ''));
        $valueIds = array_map('intval', $valueIds);
        sort($valueIds);
        $fingerprint = hash('sha256', json_encode($valueIds ? [$productId, $quantity, $code, $valueIds] : [$productId, $quantity, $code]));

        return $this->run($user, [['product_id' => $productId, 'quantity' => $quantity, 'value_ids' => $valueIds]], $key, $code, $fingerprint);
    }

    public function cart(User $user, array $lines, string $key, ?string $code = null): Invoice
    {
        $code = strtoupper(trim($code ?? ''));
        $normalized = [];
        foreach ($lines as $line) {
            $ids = array_map('intval', $line['value_ids'] ?? []);
            sort($ids);
            $pid = (int) $line['product_id'];
            $signature = $pid.':'.implode(',', $ids);
            if (! isset($normalized[$signature])) {
                $normalized[$signature] = ['product_id' => $pid, 'quantity' => 0, 'value_ids' => $ids];
            }$normalized[$signature]['quantity'] += (int) $line['quantity'];
        }
        ksort($normalized);
        $lines = array_values($normalized);

        return $this->run($user, $lines, $key, $code, hash('sha256', json_encode(['cart', $lines, $code])), 'cart');
    }

    private function run(User $user, array $lines, string $key, string $code, string $fingerprint, string $source = 'single'): Invoice
    {
        if (! $lines || count($lines) > 50 || array_sum(array_column($lines, 'quantity')) > 50) {
            throw ValidationException::withMessages(['cart' => 'Carrinho vazio ou acima de 50 unidades.']);
        }
        foreach ($lines as $line) {
            if ($line['quantity'] < 1 || $line['quantity'] > 10) {
                throw ValidationException::withMessages(['quantity' => 'Cada configuração aceita de 1 a 10 unidades.']);
            }
        }
        $existing = function () use ($user, $key, $fingerprint) {
            $order = Order::where('user_id', $user->id)->where('request_key', $key)->lockForUpdate()->first();
            if ($order && $order->fingerprint !== $fingerprint) {
                throw ValidationException::withMessages(['order' => 'Este identificador já foi usado para outro pedido.']);
            }

            return $order?->invoice;
        };
        try {
            return DB::transaction(function () use ($user, $lines, $key, $code, $fingerprint, $existing, $source) {
                // Serialize orders per customer; then lock products in numeric order for stock/limits.
                User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                if ($old = $existing()) {
                    return $old;
                }
                $ids = array_unique(array_column($lines, 'product_id'));
                sort($ids);
                $products = Product::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                if (count($ids) !== $products->count()) {
                    throw ValidationException::withMessages(['product' => 'Produto não encontrado.']);
                }
                $totals = [];
                foreach ($lines as $line) {
                    $totals[$line['product_id']] = ($totals[$line['product_id']] ?? 0) + $line['quantity'];
                }
                foreach ($products as $p) {
                    $qty = $totals[$p->id];
                    if (! $p->active || ($p->stock !== null && $p->stock < $qty) || (! $p->allow_quantity && $qty > 1)) {
                        throw ValidationException::withMessages(['product' => 'Produto indisponível, quantidade não permitida ou estoque insuficiente.']);
                    }if ($p->max_per_user !== null) {
                        $owned = Service::where('user_id', $user->id)->where('product_id', $p->id)->where('status', '!=', 'cancelled')->lockForUpdate()->get()->count();
                        if ($owned + $qty > $p->max_per_user) {
                            throw ValidationException::withMessages(['product' => 'Limite de serviços por cliente atingido.']);
                        }
                    }
                }
                $subtotal = 0;
                $priced = [];
                $snapshot = [];
                foreach ($lines as $line) {
                    $p = $products[$line['product_id']];
                    $q = app(Pricing::class)->quote($p, $line['value_ids']);
                    $subtotal += ($q['recurring_minor'] + $q['setup_minor']) * $line['quantity'];
                    $priced[] = [$p, $line['quantity'], $q];
                    $snapshot[] = ['product_id' => $p->id, 'name' => $p->name, 'quantity' => $line['quantity'], 'unit_minor' => $q['recurring_minor'], 'setup_minor' => $q['setup_minor'], 'cycle' => $p->cycle, 'configuration' => $q['configuration']];
                }
                $discount = 0;
                $coupon = null;
                if ($code !== '') {
                    $coupon = Coupon::where('code', $code)->lockForUpdate()->first();
                    if (! $coupon || ! $coupon->active || ($coupon->expires_at && $coupon->expires_at->isPast()) || ($coupon->max_uses !== null && $coupon->uses >= $coupon->max_uses)) {
                        throw ValidationException::withMessages(['coupon' => 'Cupom inválido ou esgotado.']);
                    }if ($coupon->per_user_limit !== null && CouponRedemption::where('coupon_id', $coupon->id)->where('user_id', $user->id)->whereIn('status', ['held', 'consumed'])->count() >= $coupon->per_user_limit) {
                        throw ValidationException::withMessages(['coupon' => 'Limite de uso do cupom por cliente atingido.']);
                    }$discount = $coupon->kind === 'fixed' ? min($subtotal, $coupon->fixed_minor) : intdiv($subtotal * $coupon->percent + 50, 100);
                    $coupon->increment('uses');
                    if ($discount) {
                        $snapshot[] = ['name' => 'Cupom '.$code, 'quantity' => 1, 'unit_minor' => -$discount];
                    }
                }
                $order = Order::create(['user_id' => $user->id, 'source' => $source, 'coupon_code' => $code, 'request_key' => $key, 'fingerprint' => $fingerprint, 'total_minor' => $subtotal - $discount, 'snapshot' => $snapshot]);
                $expiry = now()->addMinutes((int) config('lagos.reservation_minutes', 1440));
                $invoice = Invoice::create(['user_id' => $user->id, 'order_id' => $order->id, 'total_minor' => $order->total_minor, 'snapshot' => $snapshot, 'due_date' => $expiry->toDateString(), 'expires_at' => $expiry]);
                foreach ($priced as [$p,$qty,$q]) {
                    for ($i = 0; $i < $qty; $i++) {
                        $s = Service::create(['user_id' => $user->id, 'product_id' => $p->id, 'connector_id' => $p->connector_id, 'name' => $p->name, 'cycle' => $p->cycle, 'price_minor' => $q['recurring_minor'], 'configuration' => $q['configuration']]);
                        NativeConfig::snapshot($s, $p);
                        $invoice->services()->attach($s);
                    }
                }
                foreach ($products as $p) {
                    if ($p->stock !== null) {
                        $p->decrement('stock', $totals[$p->id]);
                        StockReservation::create(['invoice_id' => $invoice->id, 'product_id' => $p->id, 'quantity' => $totals[$p->id]]);
                    }
                }
                if ($coupon) {
                    CouponRedemption::create(['coupon_id' => $coupon->id, 'invoice_id' => $invoice->id, 'user_id' => $user->id]);
                }
                Audit::record('order.created', 'invoice:'.$invoice->id, ['total_minor' => $invoice->total_minor, 'lines' => count($lines)], $user->id);
                $user->notify(new InvoiceNotice($invoice->id));
                if ($invoice->total_minor === 0) {
                    app(Billing::class)->settle($invoice->id, 'free', 'order:'.$order->id, 0, 'BRL');
                }

                return $invoice->fresh();
            }, 5);
        } catch (QueryException $e) {
            if ($old = $existing()) {
                return $old;
            }throw $e;
        }
    }
}
