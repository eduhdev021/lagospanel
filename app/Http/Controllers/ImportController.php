<?php

namespace App\Http\Controllers;

use App\Services\AdminConfirmation;
use App\Services\PanelImporter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ImportController extends Controller
{
    public function index()
    {
        return view('admin.import');
    }

    public function run(Request $r, PanelImporter $importer, AdminConfirmation $confirmation)
    {
        $v = $r->validate([
            'source' => 'required|in:whmcs,paymenter',
            'payload_json' => 'required|string|max:500000',
            'ack' => 'accepted',
        ]);
        $confirmation->verify($r);
        $decoded = json_decode($v['payload_json'], true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages(['payload_json' => 'Informe um JSON válido exportado do WHMCS ou Paymenter.']);
        }
        $counts = $importer->import($v['source'], $decoded, $r->user()->id);

        return back()->with('status', sprintf(
            'Importação %s concluída: %d clientes, %d produtos, %d serviços e %d faturas.',
            strtoupper($v['source']),
            $counts['clients'],
            $counts['products'],
            $counts['services'],
            $counts['invoices']
        ));
    }
}
