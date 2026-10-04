<?php

namespace App\Services;

use App\Models\FinancialLedgerEntry;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentChargeback;
use App\Models\PaymentRefund;
use Illuminate\Support\Facades\DB;

final class FinancialReports
{
    public function summary(string $from, string $to): array
    {
        $payments = Payment::whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to);
        $refunds = PaymentRefund::whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to);
        $chargebacks = PaymentChargeback::whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to);
        $ledger = FinancialLedgerEntry::whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to);

        $openInvoices = fn () => Invoice::whereIn('status', ['unpaid', 'overdue', 'partial']);
        $aging = [
            'a_vencer' => (int) $openInvoices()->whereDate('due_date', '>=', today())->sum(DB::raw('total_minor - paid_minor')),
            '1_7_dias' => (int) $openInvoices()->whereBetween('due_date', [today()->subDays(7), today()->subDay()])->sum(DB::raw('total_minor - paid_minor')),
            '8_30_dias' => (int) $openInvoices()->whereBetween('due_date', [today()->subDays(30), today()->subDays(8)])->sum(DB::raw('total_minor - paid_minor')),
            'mais_30_dias' => (int) $openInvoices()->whereDate('due_date', '<', today()->subDays(30))->sum(DB::raw('total_minor - paid_minor')),
        ];

        return [
            'gross' => (int) (clone $payments)->whereNotIn('gateway', ['wallet', 'free'])->sum('amount_minor'),
            'wallet' => (int) (clone $payments)->where('gateway', 'wallet')->sum('amount_minor'),
            'refunds' => (int) $refunds->sum('amount_minor'),
            'chargebacks' => (int) $chargebacks->sum('amount_minor'),
            'net' => (int) $ledger->whereIn('type', ['payment', 'refund', 'chargeback'])->sum('amount_minor'),
            'open' => (int) Invoice::whereIn('status', ['unpaid', 'overdue', 'partial'])->sum(DB::raw('total_minor - paid_minor')),
            'overdue' => (int) Invoice::where('status', 'overdue')->sum(DB::raw('total_minor - paid_minor')),
            'aging' => $aging,
        ];
    }
}
