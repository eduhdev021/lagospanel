<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\FinancialLedger;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function refund(Request $request, Payment $payment, FinancialLedger $ledger)
    {
        $data = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1'],
            'reference' => ['required', 'string', 'max:190', 'regex:/^[a-zA-Z0-9._:-]+$/'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $ledger->refund($payment, $data['amount_minor'], $data['reference'], $data['reason'] ?? '', $request->user()->id);

        return back()->with('status', 'Reembolso registrado no ledger financeiro.');
    }

    public function chargeback(Request $request, Payment $payment, FinancialLedger $ledger)
    {
        $data = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1'],
            'reference' => ['required', 'string', 'max:190', 'regex:/^[a-zA-Z0-9._:-]+$/'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $ledger->chargeback($payment, $data['amount_minor'], $data['reference'], $data['reason'] ?? '', $request->user()->id);

        return back()->with('status', 'Chargeback registrado e fatura marcada para conciliação.');
    }
}
