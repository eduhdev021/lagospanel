<?php
/**
 * Plugin Name: LagosPanel Core
 * Description: Núcleo do LagosPanel — o painel de billing da companhia Lagos. Clientes, serviços, faturas (Pix), tickets de suporte, loja e multi-idioma PT/EN.
 * Version: 0.10.0
 *
 * © 2026 Lagos Soluções — Propriedade exclusiva. Todos os direitos reservados.
 * Author: Lagos Soluções
 * Author URI: https://lagos.com.br
 * License: GPL-2.0-or-later
 * Text Domain: lagos-core
 */

if (!defined('ABSPATH')) exit;

define('LAGOS_CORE_VERSION', '0.10.0');
define('LAGOS_CORE_DIR', plugin_dir_path(__FILE__));
define('LAGOS_CORE_URL', plugin_dir_url(__FILE__));

require_once LAGOS_CORE_DIR . 'Support/i18n.php';
require_once LAGOS_CORE_DIR . 'Support/icons.php';
require_once LAGOS_CORE_DIR . 'Setup/post-types.php';
require_once LAGOS_CORE_DIR . 'Support/helpers.php';
require_once LAGOS_CORE_DIR . 'Security/auth.php';
require_once LAGOS_CORE_DIR . 'Services/shortcodes.php';
require_once LAGOS_CORE_DIR . 'Services/cart.php';
require_once LAGOS_CORE_DIR . 'Http/rest-api.php';
require_once LAGOS_CORE_DIR . 'Extensions/Modules/modules.php';
require_once LAGOS_CORE_DIR . 'Security/security.php';
require_once LAGOS_CORE_DIR . 'Mail/smtp.php';
require_once LAGOS_CORE_DIR . 'Services/affiliates.php';
require_once LAGOS_CORE_DIR . 'Support/extras.php';
require_once LAGOS_CORE_DIR . 'Extensions/Gateways/gateways.php';
require_once LAGOS_CORE_DIR . 'Console/cron.php';
require_once LAGOS_CORE_DIR . 'Admin/audit.php';
require_once LAGOS_CORE_DIR . 'Admin/admin-shell.php';
require_once LAGOS_CORE_DIR . 'Support/whitelabel.php';
require_once LAGOS_CORE_DIR . 'Admin/reports.php';
require_once LAGOS_CORE_DIR . 'Services/webhooks.php';
require_once LAGOS_CORE_DIR . 'Mail/tickets-email.php';
require_once LAGOS_CORE_DIR . 'Admin/admin.php';
require_once LAGOS_CORE_DIR . 'Setup/seed.php';

/** Cria as páginas do painel na ativação */
function lagos_create_pages() {
    $pages = [
        // slug => [título, shortcode, template, pai]
        'loja'            => ['Loja', '[lagos_store]', '', 0],
        'carrinho'        => ['Carrinho', '[lagos_cart]', '', 0],
        'base-de-conhecimento' => ['Base de conhecimento', '[lagos_kb]', '', 0],
        'verificar-dominio' => ['Buscar domínio', '[lagos_domains]', '', 0],
        'downloads'         => ['Downloads', '[lagos_downloads]', '', 0],
        'status'            => ['Status da rede', '[lagos_status]', '', 0],
        'afiliados'         => ['Afiliados', '[lagos_affiliates]', 'template-painel.php', 'painel'],
        'pagamento'       => ['Pagamento', '[lagos_checkout]', '', 0],
        'entrar'          => ['Entrar', '[lagos_login]', '', 0],
        'registrar'       => ['Criar conta', '[lagos_register]', '', 0],
        'painel'          => ['Painel', '[lagos_dashboard]', 'template-painel.php', 0],
        'servicos'        => ['Serviços', '[lagos_services]', 'template-painel.php', 'painel'],
        'faturas'         => ['Faturas', '[lagos_invoices]', 'template-painel.php', 'painel'],
        'suporte'         => ['Suporte', '[lagos_support]', 'template-painel.php', 'painel'],
        'perfil'          => ['Perfil', '[lagos_profile]', 'template-painel.php', 'painel'],
    ];

    foreach ($pages as $slug => [$title, $shortcode, $template, $parent]) {
        // filhos exigem caminho completo no get_page_by_path
        $lookup = ($parent !== 0) ? ($parent . '/' . $slug) : $slug;
        if (get_page_by_path($lookup)) continue;

        $parent_id = 0;
        if ($parent !== 0) {
            $parent_page = get_page_by_path($parent);
            $parent_id = $parent_page ? $parent_page->ID : 0;
        }

        $pid = wp_insert_post([
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_content' => $shortcode,
            'post_parent'  => $parent_id,
        ]);
        if ($pid && !is_wp_error($pid) && $template) {
            update_post_meta($pid, '_wp_page_template', $template);
        }
    }

    lagos_create_legal_pages();
}

/** Páginas legais (LGPD) — criadas uma única vez, editáveis no admin */
function lagos_create_legal_pages() {
    $pages = [
        'termos-de-uso' => ['Termos de Uso', 'termos'],
        'politica-de-privacidade' => ['Política de Privacidade', 'privacidade'],
    ];
    foreach ($pages as $slug => [$title, $which]) {
        if (get_page_by_path($slug)) continue;

        if ($which === 'termos') {
            $content = file_get_contents(LAGOS_CORE_DIR . 'Setup/legal-termos.html');
        } else {
            $content = file_get_contents(LAGOS_CORE_DIR . 'Setup/legal-privacidade.html');
        }
        wp_insert_post([
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_content' => $content,
        ]);
    }
}

/** Ativação: CPTs → páginas → rewrites → seed */
function lagos_activate() {
    lagos_register_post_types();
    lagos_create_pages();
    flush_rewrite_rules();
    lagos_seed();
}
register_activation_hook(__FILE__, 'lagos_activate');

/** Flush de rewrites ao desativar */
function lagos_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'lagos_deactivate');
