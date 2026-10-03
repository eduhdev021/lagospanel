<?php
/**
 * Gateway: Manual — transferência bancária / boleto registrado.
 * O cliente recebe as instruções e avisa; a equipe confirma no admin.
 */
if (!defined('ABSPATH')) exit;

class Lagos_Gateway_Manual extends Lagos_Gateway {
    public $id = 'manual';
    public $name = 'Transferência Bancária';
    public $desc = 'Instruções bancárias exibidas ao cliente; confirmação manual pela equipe.';
    public $icon = 'bank';
    public $methods = ['TED', 'DOC', 'PIX manual'];
    public $color = '#5D6E8C';
    public $has_sandbox = false;

    public function fields() {
        return [
            ['instructions', 'Instruções de pagamento', 'textarea', "Banco: ...\nAgência: ...\nConta: ...\nCNPJ: ...", 'Texto exibido ao cliente no checkout (aceita quebras de linha e HTML simples).'],
        ];
    }

    public function pay($invoice, $amount, $cfg) {
        $txt = $this->cfg($cfg, 'instructions');
        if (!$txt) return ['error' => 'Configure as instruções bancárias no admin.'];
        $html = '<div style="line-height:1.8;white-space:pre-line">' . esc_html($txt) . '</div>';
        return ['instructions' => $html];
    }
}
