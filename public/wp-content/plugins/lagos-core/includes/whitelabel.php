<?php
/**
 * LagosPanel Core — White-label (v0.8)
 * Remove as referências visíveis ao WordPress da interface: meta generator,
 * links RSD/feeds do <head>, marca da tela de login, rodapé do admin e
 * remetente dos e-mails. A base WordPress continua como engine interna
 * (como Laravel é para o Paymenter) — o produto visível é 100% Lagos.
 *
 * © 2026 Lagos Soluções — Todos os direitos reservados.
 */
if (!defined('ABSPATH')) exit;

/* ── <head>: remove rastros do WP ── */
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'wp_shortlink_wp_head');
remove_action('wp_head', 'feed_links', 2);
remove_action('wp_head', 'feed_links_extra', 3);
add_filter('the_generator', '__return_empty_string');
remove_action('wp_head', 'rest_output_link_wp_head');
remove_action('wp_head', 'wp_oembed_add_discovery_links');
remove_action('wp_head', 'wp_resource_hints', 2);

/* ── Redirecionamentos: header sem marca do WP ── */
add_filter('x_redirect_by', function () {
    return 'LagosPanel';
});

/* ── XML-RPC / pingback desligados (segurança + superfície WP a menos) ── */
add_filter('xmlrpc_enabled', '__return_false');
add_filter('wp_headers', function ($headers) {
    unset($headers['X-Pingback']);
    return $headers;
});

/* ── Avisos de atualização do motor: sem o nome WordPress ── */
foreach (['update_nag', 'update_footer', 'core_upgrade_preamble'] as $f) {
    add_filter($f, function ($text) {
        return is_string($text) ? str_ireplace('WordPress', 'LagosPanel Core', $text) : $text;
    });
}

/* ── E-mails: remetente LagosPanel (não "WordPress") ── */
add_filter('wp_mail_from_name', function () {
    return 'LagosPanel';
});
add_filter('wp_mail_from', function ($from) {
    $host = parse_url(home_url(), PHP_URL_HOST) ?: '';
    $host = preg_replace('/^www\./', '', $host);
    if (!strpos($host, '.')) $host = 'lagossolucoes.com.br'; // localhost/IP → domínio válido
    return 'lagospanel@' . $host;
});

/* ── Tela de login (equipe): marca Lagos ── */
add_filter('login_title', function () {
    return 'LagosPanel — Lagos Soluções';
});
add_filter('login_headertext', function () {
    return 'LagosPanel';
});
add_filter('login_headerurl', function () {
    return home_url('/');
});
add_action('login_head', function () {
    $logo = get_theme_file_uri('assets/img/logo-painel-branca.png');
    echo '<style>
      .login h1 a{width:190px!important;height:60px!important;background:url(' . esc_url($logo) . ') center/contain no-repeat!important}
      body.login{background:radial-gradient(900px 500px at 20% -10%,rgba(124,58,237,.35),transparent 60%),radial-gradient(700px 400px at 90% 0%,rgba(192,64,224,.25),transparent 55%),#120B24!important}
      .login form{border-radius:14px;border:1px solid #E3E9F4;box-shadow:0 18px 50px rgba(18,11,36,.22)}
      .login .button-primary{background:linear-gradient(135deg,#7C3AED,#C040E0)!important;border:none!important;border-radius:10px!important;box-shadow:0 8px 20px rgba(124,58,237,.35)!important;text-shadow:none!important}
      .login label{color:#3A2A5E!important}
      .login #backtoblog a,.login #nav a{color:#8F7BC8!important}
      .login .message{border-left-color:#7C3AED!important}
    </style>';
});

/* ── Admin: rodapé Lagos Soluções (sem "WordPress") ── */
add_filter('admin_footer_text', function () {
    return '<span style="color:#7C3AED;font-weight:600">LagosPanel</span> — © ' . date_i18n('Y') . ' Lagos Soluções · Todos os direitos reservados';
});
add_filter('update_footer', function () {
    return 'v' . (defined('LAGOS_CORE_VERSION') ? LAGOS_CORE_VERSION : '');
}, 11);

/* ── Barra de admin: logo WordPress e menu wp.org removidos ── */
add_action('admin_bar_menu', function ($bar) {
    $bar->remove_node('wp-logo'); // logo + dropdown (About WordPress, WordPress.org, docs...)
}, 999);

/* ── Título das páginas admin: "— LagosPanel" em vez de "— WordPress" ── */
add_filter('admin_title', function ($admin_title, $title) {
    if (!$title) return 'LagosPanel — Admin';
    return stripos($title, 'LagosPanel') !== false ? $title : $title . ' — LagosPanel';
}, 10, 2);

/* ── /wp-admin/ leva direto ao dashboard LagosPanel (staff nunca vê o dashboard WP) ── */
add_action('load-index.php', function () {
    if (wp_doing_ajax()) return;
    wp_safe_redirect(admin_url('admin.php?page=lagospanel'));
    exit;
});
