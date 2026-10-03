<?php
/**
 * LagosPanel — Página genérica (loja, entrar, registrar, etc.)
 */
if (!defined('ABSPATH')) exit;
get_header();
?>
<div class="page-hero">
  <div class="wrap">
    <h1><?php the_title(); ?></h1>
  </div>
</div>
<div class="wrap page-content">
  <?php
  while (have_posts()) : the_post();
      the_content();
  endwhile;
  ?>
</div>
<?php get_footer(); ?>
