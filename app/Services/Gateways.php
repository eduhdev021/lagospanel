<?php

namespace App\Services;

use App\Models\GatewayCharge;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentReview;
use App\Support\Money;
use Efi\EfiPay;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

final class Gateways
{
    public function enabled(string $gateway): void
    {
        abort_unless(in_array($gateway, ['stripe', 'mercadopago', 'efi'], true) && config('lagos.payments.'.$gateway.'.enabled'), 503, 'Gateway desativado.');
        abort_if(app()->isProduction() && ! config('lagos.payments.live'), 503, 'Pagamentos reais não foram habilitados.');
    }

    public function checkout(Invoice $invoice, string $gateway): string
    {
        $this->enabled($gateway);
        abort_if($invoice->expires_at && $invoice->expires_at->isPast(), 422, 'Reserva expirada. Gere um novo pedido.');
        abort_unless(in_array($invoice->status, ['unpaid', 'overdue']) && $invoice->total_minor > 0, 422, 'Fatura indisponível.');

        if ($gateway === 'efi') {
            return $this->efiCheckout($invoice);
        }
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

    public function efiCharge(Invoice $invoice): ?GatewayCharge
    {
        return GatewayCharge::where('invoice_id', $invoice->id)->where('gateway', 'efi')->latest('id')->first();
    }

    public function configureEfiWebhook(?string $webhookUrl = null): array
    {
        $this->enabled('efi');
        $this->assertEfiConfig();
        $key = (string) config('lagos.payments.efi.pix_key');
        $url = $webhookUrl ?: url('/webhooks/efi?ignorar='.(config('lagos.payments.efi.webhook_hmac') ? '&hmac='.urlencode((string) config('lagos.payments.efi.webhook_hmac')) : ''));
        abort_unless(filter_var($url, FILTER_VALIDATE_URL) && str_starts_with($url, 'https://'), 422, 'A URL do webhook Efí precisa ser HTTPS.');
        try {
            return $this->efiBody($this->efiApi()->pixConfigWebhook(['chave' => $key], ['webhookUrl' => $url]));
        } catch (\Throwable $e) {
            abort(502, 'A Efí não configurou o webhook: '.mb_substr($e->getMessage(), 0, 180));
        }
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
        }
        abort_unless($valid, 403, 'Assinatura inválida.');
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

    /** Processa callbacks Pix somente depois de consultar a cobrança na Efí. */
    public function efiWebhook(array $payload): array
    {
        $this->enabled('efi');
        $items = $payload['pix'] ?? [];
        abort_unless(is_array($items), 400, 'Payload Pix inválido.');
        $processed = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $txid = (string) ($item['txid'] ?? '');
            if (! preg_match('/^[A-Za-z0-9]{26,35}$/D', $txid)) {
                continue;
            }
            $charge = GatewayCharge::where('gateway', 'efi')->where('reference', $txid)->first();
            if (! $charge) {
                continue;
            }
            if ($charge->status === 'paid' && $charge->provider_reference) {
                $processed[] = $txid;
                continue;
            }
            $authoritative = $this->efiGetCharge($txid);
            if (($authoritative['status'] ?? '') !== 'CONCLUIDA') {
                continue;
            }
            $remotePix = collect($authoritative['pix'] ?? [])->first(fn ($pix) => is_array($pix) && (($pix['txid'] ?? '') === $txid));
            $reference = (string) (($remotePix['endToEndId'] ?? '') ?: ($item['endToEndId'] ?? ''));
            abort_unless($reference !== '', 422, 'Efí retornou Pix sem endToEndId.');
            if (Payment::where('gateway', 'efi')->where('reference', $reference)->exists()) {
                $charge->update(['status' => 'paid', 'provider_reference' => $reference, 'paid_at' => $charge->paid_at ?? now()]);
                $processed[] = $txid;

                continue;
            }
            $amount = Money::parse((string) (($remotePix['valor'] ?? '') ?: ($authoritative['valor']['original'] ?? '0')));
            $invoice = $this->settleVerified($charge->invoice_id, 'efi', $reference, $amount, 'BRL');
            $charge->update(['status' => 'paid', 'provider_reference' => $reference, 'paid_at' => now()]);
            $processed[] = $invoice->id;
        }

        return ['processed' => $processed];
    }

    private function efiCheckout(Invoice $invoice): string
    {
        $existing = $this->efiCharge($invoice);
        if ($existing && $existing->status === 'active' && (! $existing->expires_at || $existing->expires_at->isFuture())) {
            return route('invoices.efi', $invoice);
        }
        if ($existing && $existing->status === 'active') {
            $existing->update(['status' => 'expired']);
        }

        $this->assertEfiConfig();
        $txid = 'LAGOS'.str_pad(base_convert((string) $invoice->id, 10, 36), 8, '0', STR_PAD_LEFT).strtoupper(substr(hash('sha256', $invoice->id.'|'.$invoice->total_minor), 0, 20));
        $expiration = (int) config('lagos.payments.efi.charge_expiration', 3600);
        $body = [
            'calendario' => ['expiracao' => $expiration],
            'valor' => ['original' => number_format($invoice->total_minor / 100, 2, '.', '')],
            'chave' => (string) config('lagos.payments.efi.pix_key'),
            'solicitacaoPagador' => 'Pagamento da fatura #'.$invoice->id.' — '.config('app.name'),
        ];
        try {
            $api = $this->efiApi();
            $response = $this->efiBody($api->pixCreateCharge(['txid' => $txid], $body));
            abort_unless(($response['txid'] ?? null) === $txid, 502, 'A Efí não criou uma cobrança Pix válida.');
            $location = $response['loc']['id'] ?? null;
            $qr = $location ? $this->efiBody($api->pixGenerateQRCode(['id' => $location])) : [];
            $pix = $qr['qrcode'] ?? $qr['pixCopiaECola'] ?? null;
            abort_unless(is_string($pix) && $pix !== '', 502, 'A Efí não retornou o código Pix da cobrança.');
        } catch (\Throwable $e) {
            abort(502, 'A Efí não criou a cobrança Pix: '.mb_substr($e->getMessage(), 0, 180));
        }
        GatewayCharge::create([
            'invoice_id' => $invoice->id,
            'gateway' => 'efi',
            'reference' => $txid,
            'status' => 'active',
            'amount_minor' => $invoice->total_minor,
            'currency' => 'BRL',
            'pix_copia_e_cola' => $pix,
            'location' => is_scalar($location) ? (string) $location : null,
            'expires_at' => now()->addSeconds($expiration),
        ]);

        return route('invoices.efi', $invoice);
    }

    private function efiGetCharge(string $txid): array
    {
        $this->assertEfiConfig();
        try {
            return $this->efiBody($this->efiApi()->pixDetailCharge(['txid' => $txid]));
        } catch (\Throwable $e) {
            abort(502, 'A Efí não consultou a cobrança: '.mb_substr($e->getMessage(), 0, 180));
        }
    }

    private function efiApi(): EfiPay
    {
        if (app()->bound(EfiPay::class)) {
            return app(EfiPay::class);
        }
        $certificate = (string) config('lagos.payments.efi.certificate');
        return new EfiPay([
            'clientId' => (string) config('lagos.payments.efi.client_id'),
            'clientSecret' => (string) config('lagos.payments.efi.client_secret'),
            'certificate' => $certificate,
            'pwdCertificate' => (string) config('lagos.payments.efi.certificate_password', ''),
            'sandbox' => config('lagos.payments.efi.environment') !== 'producao',
            'timeout' => 20,
            'headers' => ['x-skip-mtls-checking' => false],
        ]);
    }

    private function efiBody(mixed $response): array
    {
        $body = is_object($response) && isset($response->body) ? $response->body : $response;
        abort_unless(is_array($body), 502, 'Resposta inválida recebida da Efí.');

        return $body;
    }

    private function assertEfiConfig(): void
    {
        $required = ['client_id', 'client_secret', 'certificate', 'pix_key'];
        foreach ($required as $key) {
            abort_unless(filled(config('lagos.payments.efi.'.$key)), 503, 'Configure todos os dados de segurança da Efí antes de cobrar.');
        }
        if (! app()->runningUnitTests()) {
            abort_unless(is_file((string) config('lagos.payments.efi.certificate')) && is_readable((string) config('lagos.payments.efi.certificate')), 503, 'O certificado da Efí não foi encontrado ou não pode ser lido pelo servidor.');
        }
    }

    private function efiBaseUrl(): string
    {
        return config('lagos.payments.efi.environment') === 'producao' ? 'https://pix.api.efipay.com.br' : 'https://pix-h.api.efipay.com.br';
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
