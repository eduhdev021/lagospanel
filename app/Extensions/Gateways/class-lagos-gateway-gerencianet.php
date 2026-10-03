<?php
/**
 * Gateway: Gerencianet — Pix via API de cobranças (cob + QR).
 * Docs: https://dev.gerencianet.com.br/docs/api-pix
 *
 * Produção exige certificado .p12 (mTLS) — informe o caminho no servidor.
 * Homologação (sandbox): https://api-pix-h.gerencianet.com.br
 */
if (!defined('ABSPATH')) exit;

class Lagos_Gateway_Gerencianet extends Lagos_Gateway {
    public $id = 'gerencianet';
    public $name = 'Gerencianet';
    public $desc = 'Pix cobrança com QR (API Pix oficial) — certificado próprio.';
    public $icon = 'pix';
    public $methods = ['Pix'];
    public $only_currencies = ['BRL']; // Pix é sempre em reais
    public $color = '#F36E21';

    public function fields() {
        return [
            ['client_id', 'Client ID', 'text', '', 'dev.gerencianet.com.br → Aplicações → Pix.'],
            ['client_secret', 'Client Secret', 'password', '', 'Do mesmo aplicativo Pix.'],
            ['pix_key', 'Chave Pix', 'text', '', 'Chave cadastrada na conta Gerencianet (recebedor).'],
            ['cert_path', 'Certificado .p12 (caminho no servidor)', 'text', '/caminho/producao.p12', 'Obrigatório em produção (mTLS). Em homologação use o certificado de dev.'],
            ['cert_pass', 'Senha do certificado', 'password', '', 'Se houver.'],
            ['env', 'Ambiente', 'select', ['sandbox' => 'Homologação (teste)', 'production' => 'Produção'], 'Credenciais de homologação são distintas das de produção.'],
        ];
    }

    private function api($cfg) {
        return $this->cfg($cfg, 'env', 'sandbox') === 'production'
            ? 'https://api.gerencianet.com.br'
            : 'https://api-h.gerencianet.com.br';
    }
    private function pix($cfg) {
        return $this->cfg($cfg, 'env', 'sandbox') === 'production'
            ? 'https://api-pix.gerencianet.com.br'
            : 'https://api-pix-h.gerencianet.com.br';
    }
    /** txid com 26–35 caracteres (regra do Banco Central) */
    private function txid($iid) {
        return 'LAGOS' . str_pad((string) $iid, 25, '0', STR_PAD_LEFT); // 30 chars
    }

    private function cert_args($cfg) {
        $p = $this->cfg($cfg, 'cert_path');
        return $p ? ['cert_p12' => $p, 'cert_pass' => $this->cfg($cfg, 'cert_pass')] : [];
    }

    private function token($cfg) {
        $cid = $this->cfg($cfg, 'client_id');
        $sec = $this->cfg($cfg, 'client_secret');
        if (!$cid || !$sec) return null;
        $r = lagos_gw_http('POST', $this->api($cfg) . '/oauth/token', array_merge([
            'basic' => $cid . ':' . $sec,
            'form'  => ['grant_type' => 'client_credentials'],
        ], $this->cert_args($cfg)));
        return $r['json']['access_token'] ?? null;
    }

    public function pay($invoice, $amount, $cfg) {
        $key = $this->cfg($cfg, 'pix_key');
        if (!$key) return ['error' => 'Configure a Chave Pix da Gerencianet no admin (Pagamentos → Gerencianet).'];
        $token = $this->token($cfg);
        if (!$token) return ['error' => 'Gerencianet: falha na autenticação. Confira Client ID/Secret e o certificado.'];

        // 1) cria a cobrança
        $r = lagos_gw_http('POST', $this->pix($cfg) . '/v2/cob', array_merge([
            'bearer' => $token,
            'json'   => [
                'calendario' => ['expiracao' => 3600],
                'valor'      => ['original' => number_format((float) $amount, 2, '.', '')],
                'chave'      => $key,
                'solicitacaoPagador' => 'LagosPanel — Fatura #' . lagos_invoice_ref($invoice->ID),
                'txid'       => $this->txid($invoice->ID),
            ],
        ], $this->cert_args($cfg)));
        $locId = $r['json']['loc']['id'] ?? 0;
        if (!in_array($r['code'], [200, 201], true) || !$locId) {
            $msg = $r['json']['mensagem'] ?? ('HTTP ' . $r['code']);
            return ['error' => 'Gerencianet: ' . $msg];
        }

        // 2) gera o QR (copia-e-cola + imagem)
        $q = lagos_gw_http('GET', $this->pix($cfg) . '/v2/loc/' . (int) $locId . '/qr', array_merge([
            'bearer' => $token,
        ], $this->cert_args($cfg)));
        $code = $q['json']['pixCopiaECola'] ?? '';
        if (!$code) return ['error' => 'Gerencianet: QR não gerado (HTTP ' . $q['code'] . ').'];
        return ['qr' => ['code' => $code, 'b64' => $q['json']['imagemQrcode'] ?? '']];
    }

    public function verify_webhook($cfg, $body, $headers, $params) {
        $token = $this->token($cfg);
        if (!$token) return 0;
        $txid = $params['pix'][0]['txid'] ?? (json_decode($body, true)['pix'][0]['txid'] ?? '');
        if (!preg_match('/^LAGOS0*(\d+)$/', (string) $txid, $m)) return 0;

        // confirma consultando a cobrança — nunca confia no corpo do webhook
        $r = lagos_gw_http('GET', $this->pix($cfg) . '/v2/cob/' . rawurlencode($txid), array_merge([
            'bearer' => $token,
        ], $this->cert_args($cfg)));
        if ($r['code'] === 200 && ($r['json']['status'] ?? '') === 'CONCLUIDA') {
            return (int) $m[1];
        }
        return 0;
    }
}
