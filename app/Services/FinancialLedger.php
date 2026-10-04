<?php

namespace App\Services;

use App\Models\FinancialLedgerEntry;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentChargeback;
use App\Models\PaymentRefund;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FinancialLedger
{
    public function post(array $data): FinancialLedgerEntry
    {
        $existing = FinancialLedgerEntry::where('reference', $data['reference'])->first();
        if ($existing) {
            if ($existing->amount_minor !== (int) $data['amount_minor'] || $existing->type !== $data['type']) {
                throw ValidationException::withMessages(['finance' => 'Referência contábil já usada com valores diferentes.']);
            }

            return $existing;
        }

        return FinancialLedgerEntry::create($data);
    }

    public function refund(Payment $payment, int $amount, string $reference, string $reason = '', ?int $actor = null, ?string $providerReference = null): PaymentRefund
    {
        return DB::transaction(function () use ($payment, $amount, $reference, $reason, $actor, $providerReference): PaymentRefund {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $invoice = Invoice::whereKey($payment->invoice_id)->lockForUpdate()->firstOrFail();
            $this->assertPositiveAmount($amount);
            $available = $payment->amount_minor - $payment->refunded_minor;
            if ($amount > $available) {
                throw ValidationException::withMessages(['finance' => 'O reembolso excede o valor ainda disponível do pagamento.']);
            }
            if ($payment->status === 'chargeback' || $invoice->status === 'chargeback') {
                throw ValidationException::withMessages(['finance' => 'Pagamento já está em chargeback.']);
            }

            $refund = PaymentRefund::where('reference', $reference)->first();
            if ($refund) {
                if ($refund->payment_id !== $payment->id || $refund->amount_minor !== $amount) {
                    throw ValidationException::withMessages(['finance' => 'Referência de reembolso já usada com dados diferentes.']);
                }

                return $refund;
            }
            $refund = PaymentRefund::create(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'actor_id' => $actor, 'gateway' => $payment->gateway, 'provider_reference' => $providerReference, 'reference' => $reference, 'amount_minor' => $amount, 'currency' => $payment->currency, 'reason' => $reason, 'status' => 'processed']);
            if ($refund->payment_id !== $payment->id || $refund->amount_minor !== $amount) {
                throw ValidationException::withMessages(['finance' => 'Referência de reembolso já usada com dados diferentes.']);
            }

            $payment->increment('refunded_minor', $amount);
            $payment->refresh();
            $this->post(['user_id' => $invoice->user_id, 'invoice_id' => $invoice->id, 'payment_id' => $payment->id, 'type' => 'refund', 'reference' => 'refund:'.$refund->reference, 'amount_minor' => -$amount, 'currency' => $payment->currency, 'metadata' => ['reason' => $reason]]);
            $this->refreshInvoiceStatus($invoice);
            Audit::record('payment.refunded', 'payment:'.$payment->id, ['refund_id' => $refund->id, 'amount_minor' => $amount], $actor);

            return $refund->fresh();
        }, 5);
    }

    public function chargeback(Payment $payment, int $amount, string $reference, string $reason = '', ?int $actor = null, ?string $providerReference = null): PaymentChargeback
    {
        return DB::transaction(function () use ($payment, $amount, $reference, $reason, $actor, $providerReference): PaymentChargeback {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $invoice = Invoice::whereKey($payment->invoice_id)->lockForUpdate()->firstOrFail();
            $this->assertPositiveAmount($amount);
            if ($amount > $payment->amount_minor - $payment->refunded_minor) {
                throw ValidationException::withMessages(['finance' => 'O chargeback excede o valor disponível do pagamento.']);
            }

            $chargeback = PaymentChargeback::firstOrCreate(
                ['reference' => $reference],
                ['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'actor_id' => $actor, 'gateway' => $payment->gateway, 'provider_reference' => $providerReference, 'amount_minor' => $amount, 'currency' => $payment->currency, 'reason' => $reason, 'status' => 'open']
            );
            if ($chargeback->payment_id !== $payment->id || $chargeback->amount_minor !== $amount) {
                throw ValidationException::withMessages(['finance' => 'Referência de chargeback já usada com dados diferentes.']);
            }

            $payment->update(['status' => 'chargeback']);
            $this->post(['user_id' => $invoice->user_id, 'invoice_id' => $invoice->id, 'payment_id' => $payment->id, 'type' => 'chargeback', 'reference' => 'chargeback:'.$chargeback->reference, 'amount_minor' => -$amount, 'currency' => $payment->currency, 'metadata' => ['reason' => $reason]]);
            $invoice->update(['status' => 'chargeback']);
            Audit::record('payment.chargeback', 'payment:'.$payment->id, ['chargeback_id' => $chargeback->id, 'amount_minor' => $amount], $actor);

            return $chargeback->fresh();
        }, 5);
    }

    private function refreshInvoiceStatus(Invoice $invoice): void
    {
        $net = (int) FinancialLedgerEntry::where('invoice_id', $invoice->id)->sum('amount_minor');
        $invoice->update(['status' => $net >= $invoice->total_minor ? 'paid' : ($net > 0 ? 'partial' : 'refunded'), 'paid_minor' => max(0, $net)]);
    }

    private function assertPositiveAmount(int $amount): void
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['finance' => 'O valor financeiro deve ser positivo.']);
        }
    }
}
