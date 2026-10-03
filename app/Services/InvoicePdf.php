<?php

namespace App\Services;

use App\Models\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

final class InvoicePdf
{
    public function options(): Options
    {
        $dir = storage_path('app/private/pdf-runtime');
        File::ensureDirectoryExists($dir, 0700, true);

        return new Options([
            'isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false,
            'allowedProtocols' => [], 'chroot' => [$dir], 'tempDir' => $dir,
            'fontCache' => $dir, 'defaultFont' => 'DejaVu Sans', 'isFontSubsettingEnabled' => true,
            'logOutputFile' => null,
        ]);
    }

    public function render(Invoice $invoice): string
    {
        $pdf = new Dompdf($this->options());
        $pdf->setPaper('A4');
        $pdf->loadHtml(view('pdf.invoice', ['invoice' => $invoice, 'generatedAt' => now()])->render(), 'UTF-8');
        $pdf->render();
        $pdf->getCanvas()->page_text(38, 816, 'LagosPanel | {PAGE_NUM}/{PAGE_COUNT}', $pdf->getFontMetrics()->getFont('DejaVu Sans'), 8, [0.3, 0.38, 0.48]);

        return $pdf->output();
    }
}
