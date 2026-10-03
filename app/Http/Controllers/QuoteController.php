<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Services\Quotes;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QuoteController extends Controller
{
    public function index(Request $r)
    {
        $admin = $r->routeIs('admin.*');
        $q = Quote::with('user');
        if (! $admin) {
            $q->where('user_id', $r->user()->id)->whereNotNull('sent_at')->where('status', '!=', 'draft');
        }

        return view('quotes.index', ['quotes' => $q->latest()->paginate(20), 'admin' => $admin]);
    }

    public function show(Request $r, Quote $quote)
    {
        $admin = $r->routeIs('admin.*');
        abort_unless($admin || ($quote->user_id === $r->user()->id && $quote->status !== 'draft' && $quote->sent_at), 404);

        return view('quotes.show', ['quote' => $quote, 'admin' => $admin]);
    }

    public function form(?Quote $quote = null)
    {
        abort_if($quote?->exists && $quote->status !== 'draft', 409);

        return view('quotes.form', ['quote' => $quote]);
    }

    public function save(Request $r, Quotes $service, ?Quote $quote = null)
    {
        $v = $r->validate(['items_json' => 'required|string|max:50000']);
        try {
            $items = json_decode($v['items_json'], true, 16, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['items_json' => 'JSON de itens inválido.']);
        }$q = $service->save($r->user(), array_replace($r->all(), ['items' => $items]), $quote);

        return redirect()->route('admin.quotes.show', $q)->with('status', 'Rascunho salvo. Publique para disponibilizar ao cliente.');
    }

    public function transition(Request $r, Quote $quote, Quotes $service)
    {
        $v = $r->validate(['action' => 'required|in:send,withdraw', 'version' => 'required|integer|min:1']);
        $service->transition($r->user(), $quote, $v['action'], (int) $v['version']);

        return back()->with('status', 'Estado do orçamento atualizado.');
    }

    public function decide(Request $r, Quote $quote, Quotes $service)
    {
        $v = $r->validate(['decision' => 'required|in:accept,decline', 'version' => 'required|integer|min:1', 'ack' => 'accepted']);
        $q = $service->decide($r->user(), $quote, $v['decision'], (int) $v['version']);

        return $q->invoice_id ? redirect()->route('invoices.show', $q->invoice_id) : back()->with('status', 'Orçamento recusado.');
    }
}
