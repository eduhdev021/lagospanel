<?php
/**
 * LagosPanel — funções do tema
 */
if (!defined('ABSPATH')) exit;

/** Suportes do tema */
function lagospanel_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'caption', 'style', 'script']);
}
add_action('after_setup_theme', 'lagospanel_setup');

/** Fallback caso o plugin LagosPanel Core esteja desativado */
if (!function_exists('lagos_t')) {
    function lagos_t($key, $default = '') { return $default !== '' ? $default : $key; }
    function lagos_e($key, $default = '') { echo lagos_t($key, $default); }
}

/** Assets */
add_action('wp_enqueue_scripts', function () {
    $v = defined('LAGOS_CORE_VERSION') ? LAGOS_CORE_VERSION : '0.1.0';
    wp_enqueue_style('lagos-panel', get_template_directory_uri() . '/assets/panel.css', [], $v);
    wp_enqueue_script('lagos-panel', get_template_directory_uri() . '/assets/panel.js', [], $v, true);
});

/** Favicon (logo oficial do painel) */
add_action('wp_head', function () {
    echo '<link rel="icon" type="image/png" href="' . esc_url(get_template_directory_uri()) . '/assets/img/favicon.png">' . "\n";
    echo '<link rel="apple-touch-icon" href="' . esc_url(get_template_directory_uri()) . '/assets/img/logo-painel.png">' . "\n";
    echo '<link rel="manifest" href="' . esc_url(get_template_directory_uri()) . '/manifest.json">' . "\n";
    echo '<meta name="theme-color" content="#7C3AED">' . "\n";
});

/** Separador do título */
add_filter('document_title_separator', function () { return '—'; });

/** Esconde a barra de administração de clientes */
add_action('init', function () {
    if (!current_user_can('manage_options')) show_admin_bar(false);
});

/** Aviso no admin se o plugin core estiver desativado */
add_action('admin_notices', function () {
    if (!function_exists('lagos_register_post_types')) {
        echo '<div class="notice notice-error"><p><strong>LagosPanel:</strong> o tema requer o plugin <b>LagosPanel Core</b> ativo para funcionar corretamente.</p></div>';
    }
});

/** Modo escuro (cookie lagos_theme) */
add_filter('body_class', function ($classes) {
    if (($_COOKIE['lagos_theme'] ?? '') === 'dark') $classes[] = 'theme-dark';
    return $classes;
});

/** Logo do painel (oficial — quadrada) — $white usa a versão branca p/ fundos escuros */
function lagospanel_logo($size = 34, $white = false) {
    $src = get_template_directory_uri() . '/assets/img/logo-painel' . ($white ? '-branca' : '') . '.png';
    return '<img class="logo-img" src="' . esc_url($src) . '" alt="LagosPanel" style="height:' . intval($size) . 'px;width:auto" loading="lazy">';
}

/** Logo da Lagos Soluções (companhia — para fundos escuros) */
function lagospanel_company_logo($height = 46) {
    $src = get_template_directory_uri() . '/assets/img/logo-lagos-solucoes.png';
    return '<img class="footer-logo" src="' . esc_url($src) . '" alt="Lagos Soluções — Companhia Lagos" style="height:' . intval($height) . 'px;width:auto" loading="lazy">';
}

function lagospanel_brand($size = 34, $white = false) {
    return '<a class="brand" href="' . esc_url(home_url('/')) . '">' . lagospanel_logo($size, $white) . '<span class="brand-name">Lagos<b>Panel</b></span></a>';
}
