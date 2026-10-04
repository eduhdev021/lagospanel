<?php

namespace Tests\Feature;

use App\Models\FinancialLedgerEntry;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing;
use App\Services\FinancialLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_captures_are_idempotent_and_reach_paid_total(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::create(['user_id' => $user->id, 'type' => 'order', 'total_minor' => 1000, 'snapshot' => [], 'due_date' => today()]);
        $billing = app(Billing::class);

        $first = $billing->capture($invoice->id, 'manual', 'part-1', 400, 'BRL');
        $billing->capture($invoice->id, 'manual', 'part-1', 400, 'BRL');
        $billing->capture($invoice->id, 'manual', 'part-2', 600, 'BRL');

        $this->assertSame(2, Payment::count());
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(1000, $invoice->fresh()->paid_minor);
        $this->assertSame(1000, FinancialLedgerEntry::where('invoice_id', $invoice->id)->sum('amount_minor'));
        $this->assertSame($first->id, Payment::where('reference', 'part-1')->first()->id);
    }

    public function test_refund_is_capped_idempotent_and_updates_invoice_status(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::create(['user_id' => $user->id, 'type' => 'order', 'total_minor' => 1000, 'snapshot' => [], 'due_date' => today()]);
        app(Billing::class)->settle($invoice->id, 'manual', 'paid-1', 1000, 'BRL');
        $payment = Payment::firstOrFail();
        $ledger = app(FinancialLedger::class);

        $ledger->refund($payment, 300, 'refund-1', 'Cliente solicitou');
        $ledger->refund($payment, 300, 'refund-1', 'Cliente solicitou');
        $this->assertSame(300, $payment->fresh()->refunded_minor);
        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertSame(700, $invoice->fresh()->paid_minor);
        $this->expectException(ValidationException::class);
        $ledger->refund($payment, 701, 'refund-2');
    }

    public function test_chargeback_marks_payment_and_invoice_without_forging_a_refund(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::create(['user_id' => $user->id, 'type' => 'order', 'total_minor' => 1000, 'snapshot' => [], 'due_date' => today()]);
        app(Billing::class)->settle($invoice->id, 'manual', 'paid-2', 1000, 'BRL');
        $payment = Payment::firstOrFail();
        app(FinancialLedger::class)->chargeback($payment, 1000, 'cb-1', 'Disputa do emissor');

        $this->assertSame('chargeback', $payment->fresh()->status);
        $this->assertSame('chargeback', $invoice->fresh()->status);
        $this->assertDatabaseHas('financial_ledger_entries', ['type' => 'chargeback', 'amount_minor' => -1000]);
        $this->assertDatabaseCount('payment_refunds', 0);
    }
}
