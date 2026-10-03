<?php
/**
 * Gateway: PayPal — Orders v2 com OAuth2 (carteira PayPal, cartão).
 * Docs: https://developer.paypal.com/api/orders/v2
 */
if (!defined('ABSPATH')) exit;

class Lagos_Gateway_PayPal extends Lagos_Gateway {
    public $id = 'paypal';
    public $name = 'PayPal';
    public $desc = 'Saldo PayPal e cartões — cobertura internacional.';
    public $icon = 'wallet';
    public $methods = ['PayPal', 'Cartão'];
    public $color = '#0070BA';

    public function fields() {
        return [
            ['client_id', 'Client ID', 'text', '', 'developer.paypal.com → Apps & Credentials.'],
            ['secret', 'Secret', 'password', '', 'Secret do mesmo app.'],
            ['env', 'Ambiente', 'select', ['sandbox' => 'Sandbox (teste)', 'live' => 'Produção (live)'], 'Credenciais sandbox são diferentes das de produção.'],
            ['webhook_id', 'Webhook ID', 'text', '', 'ID do webhook (para validar notificações automáticas).'],
            ['currency', 'Moeda (3 letras)', 'text', 'BRL', 'Ex.: BRL, USD, EUR.'],
        ];
    }

    private function base($cfg) {
        return $this->cfg($cfg, 'env', 'sandbox') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    public function pay($invoice, $amount, $cfg) {
        $cid = $this->cfg($cfg, 'client_id');
        $sec = $this->cfg($cfg, 'secret');
        if (!$cid || !$sec) return ['error' => 'Configure Client ID e Secret do PayPal no admin (Pagamentos → PayPal).'];

        // 1) token OAuth2
        $t = lagos_gw_http('POST', $this->base($cfg) . '/v1/oauth2/token', [
            'basic' => $cid . ':' . $sec,
            'form'  => ['grant_type' => 'client_credentials'],
        ]);
        if (empty($t['json']['access_token'])) return ['error' => 'PayPal: falha na autenticação (HTTP ' . $t['code'] . '). Confira Client ID/Secret.'];
        $token = $t['json']['access_token'];

        // 2) cria a ordem
        $cur  = strtoupper($this->cfg($cfg, 'currency', 'BRL'));
        $back = lagos_checkout_url($invoice->ID);
        $r = lagos_gw_http('POST', $this->base($cfg) . '/v2/checkout/orders', [
            'bearer' => $token,
            'json'   => [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'amount'      => ['currency_code' => $cur, 'value' => number_format((float) $amount, 2, '.', '')],
                    'custom_id'   => 'LAGOS-' . $invoice->ID,
                    'description' => 'LagosPanel — Fatura #' . lagos_invoice_ref($invoice->ID),
                ]],
                'application_context' => [
                    'brand_name'  => 'LagosPanel',
                    'user_action' => 'PAY_NOW',
                    'return_url'  => $back,
                    'cancel_url'  => $back,
                ],
            ],
        ]);
        if (in_array($r['code'], [200, 201], true)) {
            foreach (($r['json']['links'] ?? []) as $l) {
                if (($l['rel'] ?? '') === 'approve') return ['redirect' => $l['href']];
            }
        }
        return ['error' => 'PayPal: não foi possível criar a ordem (HTTP ' . $r['code'] . ').'];
    }

    public function verify_webhook($cfg, $body, $headers, $params) {
        $cid = $this->cfg($cfg, 'client_id');
        $sec = $this->cfg($cfg, 'secret');
        $wid = $this->cfg($cfg, 'webhook_id');
        if (!$cid || !$sec || !$wid) return 0;

        $t = lagos_gw_http('POST', $this->base($cfg) . '/v1/oauth2/token', [
            'basic' => $cid . ':' . $sec,
            'form'  => ['grant_type' => 'client_credentials'],
        ]);
        if (empty($t['json']['access_token'])) return 0;

        $h = function ($k) use ($headers) { return $headers[$k] ?? ($headers[strtolower($k)] ?? ''); };
        $v = lagos_gw_http('POST', $this->base($cfg) . '/v1/notifications/verify-webhook-signature', [
            'bearer' => $t['json']['access_token'],
            'json'   => [
                'auth_algo'         => $h('paypal-auth-algo'),
                'cert_url'          => $h('paypal-cert-url'),
                'transmission_id'   => $h('paypal-transmission-id'),
                'transmission_sig'  => $h('paypal-transmission-sig'),
                'transmission_time' => $h('paypal-transmission-time'),
                'webhook_id'        => $wid,
                'webhook_event'     => json_decode($body, true),
            ],
        ]);
        if (($v['json']['verification_status'] ?? '') !== 'SUCCESS') return 0;

        $event = json_decode($body, true);
        if (!in_array($event['event_type'] ?? '', ['CHECKOUT.ORDER.APPROVED', 'PAYMENT.CAPTURE.COMPLETED'], true)) return 0;
        $res = $event['resource'] ?? [];
        $ref = $res['custom_id'] ?? ($res['purchase_units'][0]['custom_id'] ?? '');
        if (preg_match('/^LAGOS-(\d+)$/', (string) $ref, $m)) return (int) $m[1];
        return 0;
    }
}
