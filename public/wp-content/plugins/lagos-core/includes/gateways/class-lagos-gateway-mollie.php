<?php
/**
 * Gateway: Mollie — Payments API (iDEAL, cartão, Bancontact, PIX europeu...).
 * Docs: https://docs.mollie.com/reference/v2/payments-api
 */
if (!defined('ABSPATH')) exit;

class Lagos_Gateway_Mollie extends Lagos_Gateway {
    public $id = 'mollie';
    public $name = 'Mollie';
    public $desc = 'iDEAL, cartão e métodos europeus — o gateway padrão do Paymenter.';
    public $icon = 'link';
    public $methods = ['iDEAL', 'Cartão', 'Bancontact'];
    public $color = '#4453FF';

    public function fields() {
        return [
            ['api_key', 'API Key (live_ ou test_)', 'password', 'live_xxxxxxxxxxxx', 'my.mollie.com → Developers → API keys.'],
            ['currency', 'Moeda (3 letras)', 'text', 'BRL', 'Mollie aceita EUR, USD, BRL e outras.'],
        ];
    }

    public function pay($invoice, $amount, $cfg) {
        $key = $this->cfg($cfg, 'api_key');
        if (!$key) return ['error' => 'Configure a API Key da Mollie no admin (Pagamentos → Mollie).'];

        $cur = strtoupper($this->cfg($cfg, 'currency', 'BRL'));
        $r = lagos_gw_http('POST', 'https://api.mollie.com/v2/payments', [
            'bearer' => $key,
            'json'   => [
                'amount'      => ['currency' => $cur, 'value' => number_format((float) $amount, 2, '.', '')],
                'description' => 'LagosPanel — Fatura #' . lagos_invoice_ref($invoice->ID),
                'redirectUrl' => lagos_checkout_url($invoice->ID),
                'webhookUrl'  => lagos_gateway_webhook_url($this->id),
                'metadata'    => ['invoice_id' => $invoice->ID],
            ],
        ]);
        if (($r['code'] === 200 || $r['code'] === 201) && !empty($r['json']['_links']['checkout']['href'])) {
            return ['redirect' => $r['json']['_links']['checkout']['href']];
        }
        $msg = $r['json']['detail'] ?? ('HTTP ' . $r['code']);
        return ['error' => 'Mollie: ' . $msg];
    }

    public function verify_webhook($cfg, $body, $headers, $params) {
        $key = $this->cfg($cfg, 'api_key');
        if (!$key) return 0;
        $pid = $params['id'] ?? '';
        if (!$pid) return 0;
        // consulta o pagamento na API — nunca confia no corpo do webhook
        $r = lagos_gw_http('GET', 'https://api.mollie.com/v2/payments/' . rawurlencode($pid), ['bearer' => $key]);
        if (($r['code'] === 200) && (($r['json']['status'] ?? '') === 'paid')) {
            return (int) ($r['json']['metadata']['invoice_id'] ?? 0);
        }
        return 0;
    }
}
