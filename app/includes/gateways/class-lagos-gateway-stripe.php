<?php
/**
 * Gateway: Stripe — Checkout Session (cartão, Apple Pay, Google Pay).
 * Docs: https://docs.stripe.com/api/checkout/sessions
 */
if (!defined('ABSPATH')) exit;

class Lagos_Gateway_Stripe extends Lagos_Gateway {
    public $id = 'stripe';
    public $name = 'Stripe';
    public $desc = 'Cartões de crédito, Apple Pay e Google Pay via Checkout.';
    public $icon = 'card';
    public $methods = ['Cartão', 'Apple Pay', 'Google Pay'];
    public $color = '#635BFF';

    public function fields() {
        return [
            ['secret_key', 'Secret Key', 'password', 'sk_live_...', 'Chave secreta (sk_...) do dashboard.stripe.com → Developers → API keys.'],
            ['webhook_secret', 'Webhook Signing Secret', 'password', 'whsec_...', 'whsec_... do endpoint do webhook (confirmação automática).'],
            ['currency', 'Moeda (minúsculo)', 'text', 'brl', 'Ex.: brl, usd, eur.'],
        ];
    }

    public function pay($invoice, $amount, $cfg) {
        $sk = $this->cfg($cfg, 'secret_key');
        if (!$sk) return ['error' => 'Configure a Secret Key da Stripe no admin (Pagamentos → Stripe).'];

        $cur = strtolower($this->cfg($cfg, 'currency', 'brl'));
        $back = lagos_checkout_url($invoice->ID);

        $r = lagos_gw_http('POST', 'https://api.stripe.com/v1/checkout/sessions', [
            'bearer' => $sk,
            'form'   => [
                'mode' => 'payment',
                'success_url' => $back . '&stripe_ok=1',
                'cancel_url'  => $back,
                'client_reference_id' => $invoice->ID,
                'metadata[invoice_id]' => $invoice->ID,
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => $cur,
                'line_items[0][price_data][unit_amount]' => (int) round($amount * 100),
                'line_items[0][price_data][product_data][name]' => 'LagosPanel — Fatura #' . lagos_invoice_ref($invoice->ID),
            ],
        ]);
        if ($r['code'] === 200 && !empty($r['json']['url'])) {
            return ['redirect' => $r['json']['url']];
        }
        $msg = $r['json']['error']['message'] ?? ('HTTP ' . $r['code']);
        return ['error' => 'Stripe: ' . $msg];
    }

    public function verify_webhook($cfg, $body, $headers, $params) {
        $whsec = $this->cfg($cfg, 'webhook_secret');
        if (!$whsec) return 0;
        $sigHeader = $headers['stripe_signature'] ?? '';
        if (!$sigHeader) return 0;
        $t = $v1 = '';
        foreach (explode(',', $sigHeader) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part)), 2, '');
            if ($k === 't') $t = $v;
            if ($k === 'v1') $v1 = $v;
        }
        if (!$t || !$v1) return 0;
        $expected = hash_hmac('sha256', $t . '.' . $body, $whsec);
        if (!hash_equals($expected, $v1)) return 0;

        $event = json_decode($body, true);
        $type  = $event['type'] ?? '';
        if (!in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded', 'invoice.payment_succeeded'], true)) return 0;
        $obj = $event['data']['object'] ?? [];
        $iid = (int) ($obj['metadata']['invoice_id'] ?? ($obj['client_reference_id'] ?? 0));
        return $iid ?: 0;
    }
}
