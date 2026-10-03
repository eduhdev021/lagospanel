<?php

namespace App\Http\Controllers;

use App\Services\Gateways;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function stripe(Request $r, Gateways $g)
    {
        $invoice = $g->stripe($r->getContent(), $r->header('Stripe-Signature', ''));

        return response()->json(['received' => true, 'invoice' => $invoice?->id]);
    }

    public function mercadoPago(Request $r, Gateways $g)
    {
        $id = $r->input('data.id') ?? $r->query('data_id') ?? $r->input('id');
        $invoice = $g->mercadoPago((string) $id);

        return response()->json(['received' => true, 'invoice' => $invoice?->id]);
    }
}
