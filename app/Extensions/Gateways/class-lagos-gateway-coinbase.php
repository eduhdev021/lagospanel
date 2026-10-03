<?php
/**
 * Gateway: Coinbase Commerce — criptomoedas (BTC, ETH, USDC...).
 * Docs: https://docs.cdp.coinbase.com/commerce/docs
 */
if (!defined('ABSPATH')) exit;

class Lagos_Gateway_Coinbase extends Lagos_Gateway {
    public $id = 'coinbase';
    public $name = 'Coinbase Commerce';
    public $desc = 'Bitcoin, Ethereum, USDC e outras criptomoedas.';
    public $icon = 'crypto';
    public $methods = ['BTC', 'ETH', 'USDC'];
    public $color = '#0052FF';

    public function fields() {
        return [
            ['api_key', 'API Key', 'password', '', 'commerce.coinbase.com → Settings → API keys.'],
            ['webhook_secret', 'Webhook Shared Secret', 'password', '', 'Chave compartilhada do webhook (verificação HMAC).'],
            ['currency', 'Moeda (3 letras)', 'text', 'BRL', 'Preço local da cobrança; conversão automática para cripto.'],
        ];
    }

    public function pay($invoice, $amount, $cfg) {
        $key = $this->cfg($cfg, 'api_key');
        if (!$key) return ['error' => 'Configure a API Key da Coinbase Commerce no admin (Pagamentos → Coinbase).'];

        $cur  = strtoupper($this->cfg($cfg, 'currency', 'BRL'));
        $back = lagos_checkout_url($invoice->ID);
        $r = lagos_gw_http('POST', 'https://api.commerce.coinbase.com/charges', [
            'headers' => ['X-CC-Api-Key' => $key, 'X-CC-Version' => '2018-03-22'],
            'json'    => [
                'name'        => 'LagosPanel — Fatura #' . lagos_invoice_ref($invoice->ID),
                'description' => 'Hospedagem e serviços Lagos',
                'pricing_type' => 'fixed_price',
                'local_price'  => ['amount' => number_format((float) $amount, 2, '.', ''), 'currency' => $cur],
                'metadata'     => ['invoice_id' => $invoice->ID],
                'redirect_url' => $back,
                'cancel_url'   => $back,
            ],
        ]);
        if (($r['code'] === 200 || $r['code'] === 201) && !empty($r['json']['data']['hosted_url'])) {
            return ['redirect' => $r['json']['data']['hosted_url']];
        }
        return ['error' => 'Coinbase Commerce: não foi possível criar a cobrança (HTTP ' . $r['code'] . ').'];
    }

    public function verify_webhook($cfg, $body, $headers, $params) {
        $secret = $this->cfg($cfg, 'webhook_secret');
        if (!$secret) return 0;
        $sig = $headers['x-cc-webhook-signature'] ?? '';
        if (!$sig) return 0;
        if (!hash_equals(hash_hmac('sha256', $body, $secret), $sig)) return 0;

        $event = json_decode($body, true);
        if (($event['event']['type'] ?? '') !== 'charge:confirmed') return 0;
        return (int) ($event['event']['data']['metadata']['invoice_id'] ?? 0);
    }
}
