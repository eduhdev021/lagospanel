<?php
/**
 * LagosPanel — Remove dados de demonstração (para começar a operar de verdade)
 *
 * Uso (NA RAIZ do WordPress, no servidor de destino):
 *   php clean-demo.php           → mostra o que seria removido
 *   php clean-demo.php --yes     → executa
 *
 * Remove: faturas, serviços e tickets de demonstração, conta demo
 * (cliente@lagos.com), logs de e-mail/provisionamento e avaliações.
 * Mantém: produtos da loja, base de conhecimento, downloads, incidentes,
 * páginas, admin (eduardo) e todas as configurações.
 */
if (PHP_SAPI !== 'cli') exit("Somente CLI.\n");
if (!file_exists(__DIR__ . '/wp-load.php')) exit("Rode este script na raiz do WordPress (onde fica o wp-load.php).\n");
require __DIR__ . '/wp-load.php';
$executar = in_array('--yes', $argv, true);

$tipos = ['lagos_service' => 'Serviços', 'lagos_invoice' => 'Faturas', 'lagos_ticket' => 'Tickets'];
$demo  = get_user_by('email', 'cliente@lagos.com');

echo "=== LagosPanel — limpeza de dados de demonstração ===\n\n";
foreach ($tipos as $t => $label) {
    $ids = get_posts(['post_type' => $t, 'numberposts' => -1, 'post_status' => 'any', 'fields' => 'ids']);
    echo str_pad($label, 12) . count($ids) . " registro(s)\n";
}
echo str_pad('Conta demo', 12) . ($demo ? '1 (cliente@lagos.com)' : 'nenhuma') . "\n";
echo str_pad('Logs', 12) . "e-mail/provisionamento/avaliações\n\n";

if (!$executar) {
    echo "Para executar de verdade: php clean-demo.php --yes\n";
    exit(0);
}

require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ($tipos as $t => $label) {
    $ids = get_posts(['post_type' => $t, 'numberposts' => -1, 'post_status' => 'any', 'fields' => 'ids']);
    foreach ($ids as $id) wp_delete_post($id, true);
    echo "✓ $label removidos (" . count($ids) . ")\n";
}
if ($demo) {
    wp_delete_user($demo->ID);
    echo "✓ conta demo cliente@lagos.com removida\n";
}
delete_option('lagos_mail_log');
delete_option('lagos_module_log');
delete_option('lagos_last_cron');
echo "✓ logs limpos\n\n";
echo "Pronto! A loja, a KB, os downloads e as configurações foram preservados.\n";
