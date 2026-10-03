<?php
/**
 * LagosPanel — Instalador (sem wp-cli) — IDEMPOTENTE
 * Uso: php scripts/install-wp.php
 */
error_reporting(E_ALL & ~E_DEPRECATED);

define('WP_INSTALLING', true); // pula wp_not_installed() em CLI
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['REQUEST_URI'] = '/';

require '/home/user/lagospanel/public/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once WP_PLUGIN_DIR . '/lagos-core/lagos-core.php';

$fresh = !is_blog_installed();
if ($fresh) {
    echo "→ Instalando WordPress...\n";
    $result = wp_install('LagosPanel', 'eduardo', 'dev@lagos.com.br', true, '', 'LagosPanel#2026');
    if (is_wp_error($result)) { echo "ERRO: " . $result->get_error_message() . "\n"; exit(1); }
    echo "✓ WordPress instalado (admin: eduardo / LagosPanel#2026)\n";
    // remove páginas padrão do WP
    foreach (get_posts(['post_type' => 'page', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids']) as $pid) {
        wp_delete_post($pid, true);
    }
} else {
    echo "→ WordPress já instalado.\n";
}

// Ajustes
update_option('blogdescription', '');
update_option('permalink_structure', '/%postname%/');
update_option('users_can_register', 0);
update_option('timezone_string', 'America/Manaus');
update_option('date_format', 'd/m/Y');

// Tema
echo "→ Ativando tema LagosPanel...\n";
switch_theme('lagospanel');

// Plugin
$plugin = 'lagos-core/lagos-core.php';
$active = (array) get_option('active_plugins', []);
if (!in_array($plugin, $active, true)) {
    echo "→ Ativando plugin LagosPanel Core...\n";
    $res = activate_plugin($plugin);
    if (is_wp_error($res)) { echo "ERRO: " . $res->get_error_message() . "\n"; exit(1); }
} else {
    echo "→ Plugin já ativo.\n";
}

// Garante páginas + dados (idempotente)
echo "→ Garantindo páginas e dados demo...\n";
lagos_register_post_types();
lagos_create_pages();
lagos_seed();
flush_rewrite_rules();

echo "\n════════════════════════════════════════\n";
echo " LAGOSPANEL PRONTO! 🚀\n";
echo "════════════════════════════════════════\n";
echo "Site......: http://localhost:8080/\n";
echo "Loja......: http://localhost:8080/loja/\n";
echo "Cliente...: http://localhost:8080/entrar/\n";
echo "Admin WP..: http://localhost:8080/wp-admin/\n";
echo "             usuário: eduardo | senha: LagosPanel#2026\n";
echo "Conta demo: cliente@lagos.com / cliente123\n\n";
echo "Páginas: " . implode(' ', wp_list_pluck(get_pages(['numberposts' => 20]), 'post_name')) . "\n";
echo "Produtos: " . count(get_posts(['post_type' => 'lagos_product', 'numberposts' => -1])) . "\n";
