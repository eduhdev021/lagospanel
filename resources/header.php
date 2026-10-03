<?php if (!defined('ABSPATH')) exit; ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class('site'); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
  <div class="wrap header-inner">
    <?php $lagos_dark = (($_COOKIE['lagos_theme'] ?? '') === 'dark'); echo lagospanel_brand(32, $lagos_dark); ?>
    <nav class="site-nav">
      <a href="<?php echo esc_url(home_url('/')); ?>"><?php lagos_e('nav_home'); ?></a>
      <a href="<?php echo esc_url(home_url('/loja/')); ?>"><?php lagos_e('nav_store'); ?></a>
      <a href="<?php echo esc_url(home_url('/#features')); ?>"><?php lagos_e('nav_features'); ?></a>
      <a href="<?php echo esc_url(home_url('/#precos')); ?>"><?php lagos_e('nav_pricing'); ?></a>
    </nav>
    <div class="header-actions">
      <?php if (function_exists('lagos_currency_switcher')) echo lagos_currency_switcher(); ?>
      <?php if (function_exists('lagos_lang_switcher')) echo lagos_lang_switcher(); ?>
      <?php if (function_exists('lagos_icon')) : ?>
      <button class="icon-btn theme-toggle" data-theme-toggle aria-label="Tema claro/escuro" title="Tema claro/escuro"><?php echo lagos_icon('moon', 18); ?></button>
      <?php endif; ?>
      <?php if (function_exists('lagos_cart_count') && ($cart_count = lagos_cart_count()) > 0) : ?>
      <a class="cart-pill" href="<?php echo esc_url(home_url('/carrinho/')); ?>" title="<?php echo esc_attr(lagos_t('cart_title')); ?>">
        <?php echo function_exists('lagos_icon') ? lagos_icon('cart', 16) : ''; ?>
        <span class="cart-badge"><?php echo (int) $cart_count; ?></span>
      </a>
      <?php endif; ?>
      <?php if (is_user_logged_in()) : ?>
        <a class="btn btn-ghost btn-sm" href="<?php echo esc_url(function_exists('lagos_panel_url') ? lagos_panel_url('') : home_url('/painel/')); ?>"><?php lagos_e('menu_dashboard'); ?></a>
      <?php else : ?>
        <a class="btn btn-ghost btn-sm" href="<?php echo esc_url(home_url('/entrar/')); ?>"><?php lagos_e('nav_login'); ?></a>
        <a class="btn btn-primary btn-sm" href="<?php echo esc_url(home_url('/registrar/')); ?>"><?php lagos_e('nav_register'); ?></a>
      <?php endif; ?>
    </div>
  </div>
</header>

<main class="site-main">
