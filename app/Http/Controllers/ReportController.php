<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentReview;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\FinancialReports;

class ReportController extends Controller
{
    public function reviews()
    {
        return view('admin.reviews', ['reviews' => PaymentReview::latest()->paginate(25)]);
    }

    public function resolve(Request $r, PaymentReview $review)
    {
        $v = $r->validate(['resolution' => 'required|string|min:10|max:1000']);
        DB::transaction(function () use ($r, $review, $v) {
            $review = PaymentReview::lockForUpdate()->findOrFail($review->id);
            if ($review->status === 'resolved') {
                return;
            }$review->update(['status' => 'resolved', 'resolution' => $v['resolution'], 'actor_id' => $r->user()->id]);
            Audit::record('payment.review_resolved', 'review:'.$review->id, ['resolution' => $v['resolution']], $r->user()->id);
        });

        return back()->with('status', 'Análise encerrada. Nenhuma quitação ou devolução automática foi executada.');
    }

    private function range(Request $r): array
    {
        $r->merge(['from' => $r->input('from', today()->startOfMonth()->toDateString()), 'to' => $r->input('to', today()->toDateString())]);

        return $r->validate(['from' => 'required|date_format:Y-m-d|before_or_equal:to', 'to' => 'required|date_format:Y-m-d']);
    }

    public function index(Request $r, FinancialReports $financialReports)
    {
        $range = $this->range($r);
        $p = Payment::whereDate('created_at', '>=', $range['from'])->whereDate('created_at', '<=', $range['to']);
        $external = (clone $p)->whereNotIn('gateway', ['wallet', 'free'])->sum('amount_minor');
        $wallet = (clone $p)->where('gateway', 'wallet')->sum('amount_minor');
        $open = Invoice::whereIn('status', ['unpaid', 'overdue'])->sum('total_minor');
        $balances = User::sum('balance_minor');
        $payments = $p->latest()->paginate(25)->withQueryString();

        $summary = $financialReports->summary($range['from'], $range['to']);
        return view('admin.reports', compact('range', 'external', 'wallet', 'open', 'balances', 'payments', 'summary'));
    }

    public function export(Request $r)
    {
        $range = $this->range($r);
        $q = Invoice::whereDate('created_at', '>=', $range['from'])->whereDate('created_at', '<=', $range['to'])->orderBy('id');
        abort_if((clone $q)->count() > 10000, 422, 'Reduza o intervalo: limite de 10 mil faturas por exportação.');
        Audit::record('report.exported', 'invoices', $range, $r->user()->id);

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['fatura_id', 'cliente_id', 'status', 'total_centavos', 'moeda', 'vencimento', 'paga_em'], ',', '"', '');
            foreach ($q->cursor() as $i) {
                fputcsv($out, [$i->id, $i->user_id, $i->status, $i->total_minor, $i->currency, $i->due_date->toDateString(), $i->paid_at?->toIso8601String()], ',', '"', '');
            }fclose($out);
        }, 'faturas-'.$range['from'].'-'.$range['to'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
