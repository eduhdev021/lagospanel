<?php

namespace App\Services;

use App\Models\CouponRedemption;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ServiceAddon;
use App\Models\StockReservation;
use App\Models\User;
use App\Models\WalletEntry;
use App\Notifications\InvoiceNotice;
use App\Support\Cycle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class Billing
{
    private function error(string $m): never
    {
        throw ValidationException::withMessages(['billing' => $m]);
    }

    public function settle(int $id, string $gateway, string $reference, int $amount, string $currency, ?int $actor = null, ?string $note = null): Invoice
    {
        return DB::transaction(function () use ($id, $gateway, $reference, $amount, $currency, $actor, $note) {
            $invoice = Invoice::lockForUpdate()->findOrFail($id);
            if (! preg_match('/^[a-z0-9_-]{1,40}$/D', $gateway) || strlen($reference) > 160 || $reference === '') {
                $this->error('Referência de pagamento inválida.');
            }
            if ($currency !== 'BRL' || $currency !== $invoice->currency || $amount < 0 || $amount !== $invoice->total_minor) {
                $this->error('Valor ou moeda não corresponde à fatura.');
            }
            $old = Payment::where('gateway', $gateway)->where('reference', $reference)->first();
            if ($old) {
                if ($old->invoice_id !== $id || $old->amount_minor !== $amount || $old->currency !== $currency) {
                    $this->error('Transação vinculada a outra fatura.');
                }

                return $invoice;
            }
            if (! in_array($invoice->status, ['unpaid', 'overdue'], true)) {
                $this->error('Fatura não aceita novo pagamento. Concilie o recebimento.');
            }
            if ($invoice->expires_at && $invoice->expires_at->isPast()) {
                $this->error('Reserva expirada. Concilie qualquer recebimento antes de criar outro pedido.');
            }
            $services = $invoice->services()->orderBy('services.id')->lockForUpdate()->get();
            foreach ($services as $service) {
                if ($service->user_id !== $invoice->user_id || $service->status === 'cancelled') {
                    $this->error('Serviço encerrado ou vinculado a outro cliente.');
                }
            }
            Payment::create(['invoice_id' => $id, 'gateway' => $gateway, 'reference' => $reference, 'amount_minor' => $amount, 'currency' => $currency, 'actor_id' => $actor, 'note' => $note]);
            $invoice->update(['status' => 'paid', 'paid_at' => now()]);
            StockReservation::where('invoice_id', $id)->where('status', 'held')->update(['status' => 'consumed']);
            CouponRedemption::where('invoice_id', $id)->where('status', 'held')->update(['status' => 'consumed']);
            if ($invoice->type === 'deposit') {
                $this->wallet($invoice->user_id, $amount, 'deposit:'.$id, 'Recarga da fatura #'.$id);
            } elseif ($invoice->type === 'upgrade') {
                app(ServiceUpgrades::class)->completeInvoice($invoice);
            } elseif (in_array($invoice->type, ['domain', 'domain_renewal'], true)) {
                app(Domains::class)->completeInvoice($invoice);
            } elseif ($invoice->type === 'addon') {
                foreach (ServiceAddon::where('invoice_id', $id)->lockForUpdate()->get() as $sa) {
                    $sa->update(['status' => 'active', 'next_due' => Cycle::next(CarbonImmutable::today(), $sa->cycle, CarbonImmutable::today()->day)]);
                }
            } else {
                foreach ($services as $service) {
                    $base = $invoice->type === 'renewal' ? ($invoice->period_start ?? $service->next_due ?? CarbonImmutable::today()) : CarbonImmutable::today();
                    $anchor = $service->billing_anchor ?? $base->day;
                    $service->update(['next_due' => Cycle::next(CarbonImmutable::instance($base), $service->cycle, $anchor), 'billing_anchor' => $anchor]);
                    if ($service->connector_id) {
                        $action = ! $service->remote_id ? 'create' : ($service->status === 'suspended' ? 'unsuspend' : null);
                        if ($action) {
                            app(Provisioning::class)->enqueue($service, $action, 'invoice:'.$id.':'.$service->id);
                        }
                    }
                }
            }
            app(Affiliates::class)->creditForInvoice($invoice);
            Audit::record('invoice.paid', 'invoice:'.$id, ['gateway' => $gateway, 'amount_minor' => $amount], $actor);
            $invoice->user->notify(new InvoiceNotice($id, true));

            return $invoice->fresh();
        }, 5);
    }

    public function wallet(int $uid, int $amount, string $reference, string $description): bool
    {
        return DB::transaction(function () use ($uid, $amount, $reference, $description) {
            $user = User::lockForUpdate()->findOrFail($uid);
            $old = WalletEntry::where('reference', $reference)->first();
            if ($old) {
                if ($old->user_id !== $uid || $old->amount_minor !== $amount) {
                    $this->error('Movimento de saldo conflitante.');
                }

                return false;
            }
            if ($user->balance_minor + $amount < 0) {
                $this->error('Saldo insuficiente.');
            }
            $user->balance_minor += $amount;
            $user->save();
            WalletEntry::create(['user_id' => $uid, 'reference' => $reference, 'amount_minor' => $amount, 'balance_after_minor' => $user->balance_minor, 'description' => $description]);

            return true;
        }, 5);
    }

    public function payWithWallet(User $user, int $id): Invoice
    {
        return DB::transaction(function () use ($user, $id) {
            $invoice = Invoice::lockForUpdate()->findOrFail($id);
            abort_unless($invoice->user_id === $user->id, 404);
            if ($invoice->type === 'deposit') {
                $this->error('Não é possível recarregar saldo usando saldo.');
            }
            if ($invoice->status === 'paid' && Payment::where('invoice_id', $id)->where('gateway', 'wallet')->exists()) {
                return $invoice;
            }
            if (! in_array($invoice->status, ['unpaid', 'overdue'], true)) {
                $this->error('Fatura indisponível.');
            }
            $this->wallet($user->id, -$invoice->total_minor, 'wallet-payment:'.$id, 'Pagamento da fatura #'.$id);

            return $this->settle($id, 'wallet', 'invoice:'.$id, $invoice->total_minor, 'BRL', $user->id);
        }, 5);
    }
}
