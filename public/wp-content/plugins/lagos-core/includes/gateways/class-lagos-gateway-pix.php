<?php
/**
 * Gateway: Pix (Demonstração) — QR local, sem provedor externo.
 * Sempre simulado: ideal para demonstrações e testes do fluxo de fatura.
 */
if (!defined('ABSPATH')) exit;

class Lagos_Gateway_PixDemo extends Lagos_Gateway {
    public $id = 'pix';
    public $name = 'Pix';
    public $desc = 'Pagamento instantâneo via QR Code (demonstração).';
    public $icon = 'pix';
    public $methods = ['Pix'];
    public $color = '#32BCAD';
    public $has_sandbox = false;
    public $is_virtual = true;
    public $only_currencies = ['BRL']; // Pix é sempre em reais

    public function pay($invoice, $amount, $cfg) {
        return ['qr' => [
            'code' => lagos_fake_pix_code($invoice->ID, $amount),
            'svg'  => lagos_fake_qr($invoice->ID),
        ]];
    }
}
