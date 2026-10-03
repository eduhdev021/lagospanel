<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OrderLifecycle
{
    public function cancel(int $id, ?int $actor, string $reason, bool $expiredOnly = false): bool
    {
        return DB::transaction(function () use ($id, $actor, $reason, $expiredOnly) {
            $i = Invoice::lockForUpdate()->findOrFail($id);
            if ($i->status === 'cancelled') {
                return false;
            }if ($expiredOnly && (! $i->expires_at || $i->expires_at->isFuture() || ! in_array($i->status, ['unpaid', 'overdue']))) {
                return false;
            }
            if (! $i->order_id || ! in_array($i->status, ['unpaid', 'overdue'])) {
                throw ValidationException::withMessages(['invoice' => 'Somente pedidos ainda não pagos podem ser cancelados.']);
            }
            $services = $i->services()->orderBy('services.id')->lockForUpdate()->get();
            foreach ($services as $s) {
                if (! in_array($s->status, ['pending', 'cancelled'])) {
                    throw ValidationException::withMessages(['invoice' => 'Serviço já executado; necessária conciliação manual.']);
                }
            }
            foreach ($services as $s) {
                $s->update(['status' => 'cancelled', 'auto_renew' => false]);
            }
            foreach (StockReservation::where('invoice_id', $id)->where('status', 'held')->orderBy('product_id')->lockForUpdate()->get() as $r) {
                $p = Product::lockForUpdate()->findOrFail($r->product_id);
                if ($p->stock !== null) {
                    $p->increment('stock', $r->quantity);
                }$r->update(['status' => 'released']);
            }
            foreach (CouponRedemption::where('invoice_id', $id)->where('status', 'held')->lockForUpdate()->get() as $r) {
                $c = Coupon::lockForUpdate()->findOrFail($r->coupon_id);
                if ($c->uses > 0) {
                    $c->decrement('uses');
                }$r->update(['status' => 'released']);
            }
            $i->update(['status' => 'cancelled']);
            Audit::record($expiredOnly ? 'order.expired' : 'order.cancelled', 'invoice:'.$id, ['reason' => $reason], $actor);

            return true;
        }, 5);
    }

    public function expire(): int
    {
        $count = 0;
        Invoice::whereNotNull('order_id')->whereIn('status', ['unpaid', 'overdue'])->where('expires_at', '<=', now())->chunkById(100, function ($invoices) use (&$count) {
            foreach ($invoices as $i) {
                try {
                    $count += (int) $this->cancel($i->id, null, 'Prazo de reserva expirado', true);
                } catch (ValidationException) {
                    Audit::record('order.expiry_review', 'invoice:'.$i->id);
                }
            }
        });

        return $count;
    }
}
