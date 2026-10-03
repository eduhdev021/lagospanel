<?php
/**
 * Gateway: Mercado Pago — Pix (QR real via API) e Checkout Pro (redirect).
 * Docs: https://www.mercadopago.com.br/developers/pt/reference
 */
if (!defined('ABSPATH')) exit;

class Lagos_Gateway_MercadoPago extends Lagos_Gateway {
    public $id = 'mercadopago';
    public $name = 'Mercado Pago';
    public $desc = 'Pix, cartão e boleto — o favorito do Brasil (Checkout Pro / API Pix).';
    public $icon = 'pix';
    public $methods = ['Pix', 'Cartão', 'Boleto'];
    public $color = '#009EE3';

    public function fields() {
        return [
            ['access_token', 'Access Token (produção)', 'password', 'APP_USR-...', 'Gerado em developers.mercadopago.com.br → Suas integrações → Credenciais.'],
            ['method', 'Modalidade', 'select', ['pix' => 'Pix (QR Code na tela)', 'checkout' => 'Checkout Pro (página do Mercado Pago)'], 'O Pix mostra o QR aqui mesmo; o Checkout Pro redireciona.'],
            ['currency', 'Moeda (3 letras)', 'text', 'BRL', 'Ex.: BRL, ARS, MXN.'],
        ];
    }

    public function pay($invoice, $amount, $cfg) {
        $token = $this->cfg($cfg, 'access_token');
        if (!$token) return ['error' => 'Configure o Access Token do Mercado Pago no admin (Pagamentos → Mercado Pago).'];

        $cur     = strtoupper($this->cfg($cfg, 'currency', 'BRL'));
        $ref     = lagos_invoice_ref($invoice->ID);
        $webhook = lagos_gateway_webhook_url($this->id);
        $back    = lagos_checkout_url($invoice->ID);

        if ($this->cfg($cfg, 'method', 'pix') === 'checkout') {
            $r = lagos_gw_http('POST', 'https://api.mercadopago.com/checkout/preferences', [
                'bearer' => $token,
                'json'   => [
                    'items' => [[
                        'title'       => 'LagosPanel — Fatura #' . $ref,
                        'quantity'    => 1,
                        'currency_id' => $cur,
                        'unit_price'  => (float) $amount,
                    ]],
                    'external_reference' => 'LAGOS-' . $invoice->ID,
                    'back_urls'  => ['success' => $back, 'pending' => $back, 'failure' => $back],
                    'notification_url' => $webhook,
                    'auto_return' => 'approved',
                ],
            ]);
            if (($r['code'] === 200 || $r['code'] === 201) && !empty($r['json']['init_point'])) {
                return ['redirect' => $r['json']['init_point']];
            }
            return ['error' => 'Mercado Pago recusou a requisição (HTTP ' . $r['code'] . '). Verifique as credenciais.'];
        }

        // Pix direto (QR na tela)
        $r = lagos_gw_http('POST', 'https://api.mercadopago.com/v1/payments', [
            'bearer' => $token,
            'json'   => [
                'transaction_amount' => (float) $amount,
                'description'        => 'LagosPanel — Fatura #' . $ref,
                'payment_method_id'  => 'pix',
                'external_reference' => 'LAGOS-' . $invoice->ID,
                'notification_url'   => $webhook,
            ],
        ]);
        if (($r['code'] === 200 || $r['code'] === 201) && !empty($r['json']['point_of_interaction']['transaction_data']['qr_code'])) {
            $td = $r['json']['point_of_interaction']['transaction_data'];
            return ['qr' => ['code' => $td['qr_code'], 'b64' => $td['qr_code_base64']]];
        }
        return ['error' => 'Mercado Pago recusou o Pix (HTTP ' . $r['code'] . '). Verifique as credenciais.'];
    }

    public function verify_webhook($cfg, $body, $headers, $params) {
        $token = $this->cfg($cfg, 'access_token');
        if (!$token) return 0;
        $data = $params['data']['id'] ?? ($params['id'] ?? '');
        if (!$data) return 0;
        // confirma consultando a API (não confiamos no corpo do webhook)
        $r = lagos_gw_http('GET', 'https://api.mercadopago.com/v1/payments/' . rawurlencode($data), ['bearer' => $token]);
        if (($r['code'] === 200) && (($r['json']['status'] ?? '') === 'approved')) {
            $ref = $r['json']['external_reference'] ?? '';
            if (preg_match('/^LAGOS-(\d+)$/', $ref, $m)) return (int) $m[1];
        }
        return 0;
    }
}
