<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PaymentReview;
use App\Support\Money;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

final class Gateways
{
    public function enabled(string $gateway): void
    {
        abort_unless(in_array($gateway, ['stripe', 'mercadopago'], true) && config('lagos.payments.'.$gateway.'.enabled'), 503, 'Gateway desativado.');
        abort_if(app()->isProduction() && ! config('lagos.payments.live'), 503, 'Pagamentos reais não foram habilitados.');
    }

    public function checkout(Invoice $invoice, string $gateway): string
    {
        $this->enabled($gateway);
        abort_if($invoice->expires_at && $invoice->expires_at->isPast(), 422, 'Reserva expirada. Gere um novo pedido.');
        abort_unless(in_array($invoice->status, ['unpaid', 'overdue']) && $invoice->total_minor > 0, 422, 'Fatura indisponível.');
        if ($gateway === 'stripe') {
            abort_unless(config('lagos.payments.stripe.secret') && config('lagos.payments.stripe.webhook_secret'), 503, 'Configure o gateway e o webhook.');
            $r = Http::asForm()->withToken(config('lagos.payments.stripe.secret'))->withoutRedirecting()->timeout(20)->withHeaders(['Idempotency-Key' => 'lagos-invoice-'.$invoice->id.'-'.$invoice->total_minor])->post('https://api.stripe.com/v1/checkout/sessions', ['mode' => 'payment', 'success_url' => route('invoices.show', $invoice).'?retorno=stripe', 'cancel_url' => route('invoices.show', $invoice), 'client_reference_id' => (string) $invoice->id, 'metadata' => ['invoice_id' => (string) $invoice->id], 'line_items' => [['quantity' => 1, 'price_data' => ['currency' => 'brl', 'unit_amount' => $invoice->total_minor, 'product_data' => ['name' => 'LagosPanel — Fatura #'.$invoice->id]]]]]);
            $url = $r->json('url');
            abort_unless($r->successful() && is_string($url) && str_starts_with($url, 'https://checkout.stripe.com/'), 502, 'O provedor não criou uma sessão válida.');

            return $url;
        }
        abort_unless(config('lagos.payments.mercadopago.token'), 503, 'Configure o gateway.');
        $r = Http::withToken(config('lagos.payments.mercadopago.token'))->withoutRedirecting()->timeout(20)->post('https://api.mercadopago.com/checkout/preferences', ['items' => [['title' => 'LagosPanel — Fatura #'.$invoice->id, 'quantity' => 1, 'currency_id' => 'BRL', 'unit_price' => $invoice->total_minor / 100]], 'external_reference' => 'LAGOS-'.$invoice->id, 'notification_url' => url('/webhooks/mercadopago'), 'back_urls' => ['success' => route('invoices.show', $invoice), 'pending' => route('invoices.show', $invoice), 'failure' => route('invoices.show', $invoice)]]);
        $url = $r->json(config('lagos.payments.live') ? 'init_point' : 'sandbox_init_point');
        abort_unless($r->successful() && is_string($url) && str_starts_with($url, 'https://'), 502, 'O provedor não criou uma sessão válida.');

        return $url;
    }

    public function stripe(string $body, string $signature): ?Invoice
    {
        $this->enabled('stripe');
        $secret = config('lagos.payments.stripe.webhook_secret');
        abort_unless($secret, 503);
        $parts = [];
        foreach (explode(',', $signature) as $part) {
            [$k,$v] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$k][] = $v;
        }
        $timestamp = $parts['t'][0] ?? '';
        abort_unless(ctype_digit($timestamp) && abs(time() - (int) $timestamp) <= 300, 403, 'Assinatura expirada.');
        $valid = false;
        foreach ($parts['v1'] ?? [] as $sig) {
            $valid = $valid || hash_equals(hash_hmac('sha256', $timestamp.'.'.$body, $secret), $sig);
        }abort_unless($valid, 403, 'Assinatura inválida.');
        $e = json_decode($body, true);
        abort_unless(is_array($e), 400);
        if (! in_array($e['type'] ?? '', ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            return null;
        }
        $p = $e['data']['object'] ?? [];
        if (($p['payment_status'] ?? '') !== 'paid') {
            return null;
        }
        abort_unless(($e['livemode'] ?? null) === (bool) config('lagos.payments.live'), 422, 'Ambiente de pagamento incorreto.');
        abort_unless(is_int($p['amount_total'] ?? null), 422);

        return $this->settleVerified((int) ($p['metadata']['invoice_id'] ?? 0), 'stripe', (string) ($p['payment_intent'] ?? $p['id'] ?? ''), $p['amount_total'], strtoupper($p['currency'] ?? ''));
    }

    public function mercadoPago(string $id): ?Invoice
    {
        $this->enabled('mercadopago');
        abort_unless(preg_match('/^\d{1,30}$/D', $id), 400);
        abort_unless(config('lagos.payments.mercadopago.token'), 503);
        $r = Http::withToken(config('lagos.payments.mercadopago.token'))->withoutRedirecting()->timeout(20)->get('https://api.mercadopago.com/v1/payments/'.$id);
        abort_unless($r->successful(), 503, 'Falha na consulta ao provedor.');
        $p = $r->json();
        if (($p['status'] ?? '') !== 'approved') {
            return null;
        }
        abort_unless(($p['live_mode'] ?? null) === (bool) config('lagos.payments.live'), 422);
        abort_unless(preg_match('/^LAGOS-(\d+)$/D', $p['external_reference'] ?? '', $m), 422);

        return $this->settleVerified((int) $m[1], 'mercadopago', (string) $p['id'], Money::parse((string) ($p['transaction_amount'] ?? '')), $p['currency_id'] ?? '');
    }

    private function settleVerified(int $id, string $gateway, string $reference, int $amount, string $currency): Invoice
    {
        try {
            return app(Billing::class)->settle($id, $gateway, $reference, $amount, $currency);
        } catch (ValidationException|ModelNotFoundException $e) {
            $review = PaymentReview::firstOrCreate(['fingerprint' => hash('sha256', $gateway.'|'.$reference)], ['invoice_id' => Invoice::find($id)?->id, 'provider_invoice_ref' => (string) $id, 'gateway' => $gateway, 'reference' => mb_substr($reference, 0, 160), 'amount_minor' => $amount, 'currency' => mb_substr($currency, 0, 12), 'reason' => mb_substr($e->getMessage(), 0, 1000)]);
            if ($review->wasRecentlyCreated) {
                Audit::record('payment.review', 'review:'.$review->id);
            }
            throw $e;
        }
    }
}
