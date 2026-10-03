<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Audit;
use App\Services\InvoicePdf;
use Illuminate\Http\Request;

class InvoiceDocumentController extends Controller
{
    public function download(Request $r, Invoice $invoice, InvoicePdf $pdf)
    {
        abort_unless($r->routeIs('admin.*') || $invoice->user_id === $r->user()->id, 404);
        $bytes = $pdf->render($invoice->load('user'));
        Audit::record('invoice.pdf', 'invoice:'.$invoice->id, [], $r->user()->id);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="fatura-'.$invoice->id.'.pdf"',
            'Cache-Control' => 'no-store, private',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
