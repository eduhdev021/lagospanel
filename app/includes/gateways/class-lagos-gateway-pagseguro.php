<?php
/**
 * Gateway: PagSeguro — Pix via Orders API (QR na tela).
 * Docs: https://dev.pagbank.uol.com.br/reference/orders
 */
if (!defined('ABSPATH')) exit;

class Lagos_Gateway_PagSeguro extends Lagos_Gateway {
    public $id = 'pagseguro';
    public $name = 'PagSeguro';
    public $desc = 'Pix com QR Code na tela — bandeira PagBank/UOL.';
    public $icon = 'pix';
    public $methods = ['Pix'];
    public $only_currencies = ['BRL']; // Pix é sempre em reais
    public $color = '#00A85A';

    public function fields() {
        return [
            ['token', 'Token (Bearer)', 'password', '', 'Devolve o token em dev.pagbank.uol.com.br → Credenciais. Sandbox e produção usam tokens diferentes.'],
            ['env', 'Ambiente', 'select', ['sandbox' => 'Sandbox (teste)', 'production' => 'Produção'], 'Endpoints e tokens são distintos em cada ambiente.'],
        ];
    }

    private function base($cfg) {
        return $this->cfg($cfg, 'env', 'sandbox') === 'production'
            ? 'https://api.pagseguro.com'
            : 'https://api.sandbox.pagseguro.com';
    }

    public function pay($invoice, $amount, $cfg) {
        $token = $this->cfg($cfg, 'token');
        if (!$token) return ['error' => 'Configure o Token do PagSeguro no admin (Pagamentos → PagSeguro).'];

        $uid  = (int) get_post_meta($invoice->ID, '_lagos_user', true);
        $user = get_userdata($uid);
        $cents = (int) round($amount * 100);

        $r = lagos_gw_http('POST', $this->base($cfg) . '/orders', [
            'bearer' => $token,
            'json'   => [
                'reference_id' => 'LAGOS-' . $invoice->ID,
                'customer'     => [
                    'name'  => $user ? $user->display_name : 'Cliente Lagos',
                    'email' => $user ? $user->user_email : '',
                ],
                'items' => [[
                    'name'     => 'LagosPanel — Fatura #' . lagos_invoice_ref($invoice->ID),
                    'quantity' => 1,
                    'amount'   => ['value' => $cents],
                ]],
                'qr_codes' => [['amount' => ['value' => $cents]]],
                'notification_urls' => [lagos_gateway_webhook_url($this->id)],
            ],
        ]);
        if (!in_array($r['code'], [200, 201], true)) {
            $msg = $r['json']['error_messages'][0]['message'] ?? ('HTTP ' . $r['code']);
            return ['error' => 'PagSeguro: ' . $msg];
        }
        $qr  = $r['json']['qr_codes'][0] ?? [];
        $txt = $qr['text'] ?? '';
        if (!$txt) return ['error' => 'PagSeguro: resposta sem QR Code.'];

        // baixa a imagem do QR (PNG) para embutir
        $b64 = '';
        foreach (($qr['links'] ?? []) as $l) {
            if (strpos($l['href'] ?? '', '/qr') !== false) {
                $img = lagos_gw_http('GET', $l['href'], ['bearer' => $token]);
                if ($img['code'] === 200 && $img['body']) $b64 = base64_encode($img['body']);
                break;
            }
        }
        return ['qr' => ['code' => $txt, 'b64' => $b64]];
    }

    public function verify_webhook($cfg, $body, $headers, $params) {
        $token = $this->cfg($cfg, 'token');
        if (!$token) return 0;

        // extrai reference_id (LAGOS-n) e id do pedido recursivamente
        $ref = $oid = '';
        $walk = function ($d) use (&$walk, &$ref, &$oid) {
            if (!is_array($d)) return;
            foreach ($d as $k => $v) {
                if ($k === 'reference_id' && is_string($v) && preg_match('/^LAGOS-(\d+)$/', $v, $m)) $ref = $m[1];
                if ($k === 'id' && is_string($v) && strpos($v, 'or_') === 0) $oid = $v;
                if (is_array($v)) $walk($v);
            }
        };
        $walk(json_decode($body, true));
        if (!$ref || !$oid) return 0;

        // confirma consultando a API — nunca confia no corpo do webhook
        $r = lagos_gw_http('GET', $this->base($cfg) . '/orders/' . rawurlencode($oid), ['bearer' => $token]);
        if ($r['code'] === 200 && in_array(($r['json']['status'] ?? ''), ['PAID', 'PAID_OVER'], true)) {
            return (int) $ref;
        }
        return 0;
    }
}
