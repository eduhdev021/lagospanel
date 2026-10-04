<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payments')->orderBy('id')->chunkById(500, function ($payments): void {
            foreach ($payments as $payment) {
                $invoice = DB::table('invoices')->where('id', $payment->invoice_id)->first(['id', 'user_id']);
                if (! $invoice) {
                    continue;
                }

                DB::table('financial_ledger_entries')->insertOrIgnore([
                    'user_id' => $invoice->user_id,
                    'invoice_id' => $payment->invoice_id,
                    'payment_id' => $payment->id,
                    'type' => 'payment',
                    'reference' => 'payment:'.$payment->gateway.':'.$payment->reference,
                    'amount_minor' => $payment->amount_minor,
                    'currency' => $payment->currency,
                    'metadata' => json_encode(['legacy' => true], JSON_THROW_ON_ERROR),
                    'created_at' => $payment->created_at,
                    'updated_at' => $payment->created_at,
                ]);
            }
        });

        DB::table('invoices')->orderBy('id')->chunkById(500, function ($invoices): void {
            foreach ($invoices as $invoice) {
                $paid = (int) DB::table('financial_ledger_entries')
                    ->where('invoice_id', $invoice->id)
                    ->whereIn('type', ['payment', 'refund', 'chargeback'])
                    ->sum('amount_minor');
                DB::table('invoices')->where('id', $invoice->id)->update(['paid_minor' => max(0, $paid)]);
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('O backfill financeiro não deve ser revertido; preserve o ledger e restaure um backup se necessário.');
    }
};
