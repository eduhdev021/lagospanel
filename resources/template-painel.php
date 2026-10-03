<?php
/**
 * LagosPanel — Área do cliente (shell com sidebar)
 * Template Name: Área do Cliente LagosPanel
 */
if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    wp_redirect(home_url('/entrar/?redirect_to=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '')));
    exit;
}

$user = wp_get_current_user();
$uri  = trailingslashit(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));

$nav_items = [
    ['/painel/',          'home',    'menu_dashboard'],
    ['/painel/servicos/', 'server',  'menu_services'],
    ['/painel/faturas/',  'invoice', 'menu_invoices'],
    ['/painel/suporte/',  'ticket',  'menu_support'],
    null, // divisor
    ['/loja/',            'store',   'menu_store'],
    ['/painel/perfil/',   'user',    'menu_profile'],
];
if (current_user_can('manage_options')) {
    $nav_items[] = null;
    $nav_items[] = ['/wp-admin/', 'settings', 'menu_admin'];
}

$initial = function_exists('lagos_money') ? strtoupper(mb_substr(trim($user->display_name), 0, 1)) : 'U';
$balance = function_exists('lagos_balance') ? lagos_money(lagos_balance($user->ID)) : '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class('lagos-panel-page'); ?>>
<?php wp_body_open(); ?>

<div class="panel-shell">

  <aside class="sidebar" id="lagosSidebar">
    <div class="sidebar-head"><?php echo lagospanel_brand(30, true); ?></div>

    <nav class="sidebar-nav">
      <?php foreach ($nav_items as $item) :
          if ($item === null) { echo '<div class="nav-divider"></div>'; continue; }
          [$path, $ic, $label] = $item;
          $active = ($uri === trailingslashit($path)) ? ' is-active' : '';
          $cls = ($path === '/wp-admin/') ? ' nav-external' : '';
      ?>
      <a class="nav-link<?php echo $active . $cls; ?>" href="<?php echo esc_url(home_url($path)); ?>">
        <?php echo function_exists('lagos_icon') ? lagos_icon($ic, 19) : ''; ?>
        <span><?php lagos_e($label); ?></span>
      </a>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-user">
      <div class="avatar" style="overflow:hidden;width:38px;height:38px;flex:none"><?php echo get_avatar(get_current_user_id(), 38, 'identicon', '', ['force_display' => true]); ?></div>
      <div class="sidebar-user-meta">
        <strong><?php echo esc_html($user->display_name); ?></strong>
        <span><?php echo esc_html($balance); ?></span>
      </div>
      <a class="icon-btn" href="<?php echo esc_url(wp_logout_url(home_url('/entrar/'))); ?>" title="<?php esc_attr_e('Sair'); ?>">
        <?php echo function_exists('lagos_icon') ? lagos_icon('logout', 17) : '→'; ?>
      </a>
    </div>
  </aside>

  <div class="panel-overlay" id="lagosOverlay"></div>

  <div class="panel-main">
    <header class="topbar">
      <button class="icon-btn menu-toggle" id="lagosMenuToggle" aria-label="Menu">
        <?php echo function_exists('lagos_icon') ? lagos_icon('menu', 20) : ''; ?>
      </button>
      <h1 class="topbar-title"><?php echo esc_html(get_the_title()); ?></h1>
      <div class="topbar-actions">
        <?php if (function_exists('lagos_icon')) : ?>
        <button class="icon-btn theme-toggle" data-theme-toggle aria-label="Tema claro/escuro" title="Tema claro/escuro"><?php echo lagos_icon('moon', 18); ?></button>
        <?php endif; ?>
        <?php if (function_exists('lagos_currency_switcher')) echo lagos_currency_switcher(); ?>
      <?php if (function_exists('lagos_lang_switcher')) echo lagos_lang_switcher(); ?>
        <a class="btn btn-ghost btn-sm" href="<?php echo esc_url(home_url('/')); ?>"><?php lagos_e('menu_public'); ?></a>
      </div>
    </header>

    <div class="panel-content">
      <?php if (function_exists('lagos_flash')) echo lagos_flash(); ?>
      <?php
      while (have_posts()) : the_post();
          the_content();
      endwhile;
      ?>
    </div>

    <footer class="panel-foot">
      <?php if (function_exists('lagos_panel_mode') && lagos_panel_mode() === 'demo') : lagos_e('demo_note'); ?> · <?php endif; ?>© <?php echo esc_html(date_i18n('Y')); ?> — <?php lagos_e('brand_tag'); ?>
    </footer>
  </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
