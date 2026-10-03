<?php
/**
 * LagosPanel — Post simples (novidades/anúncios)
 */
if (!defined('ABSPATH')) exit;
get_header();
?>
<div class="wrap page-content narrow">
  <article class="post-single">
    <div class="post-meta"><?php echo esc_html(get_the_date()); ?></div>
    <h1><?php the_title(); ?></h1>
    <div class="post-body"><?php the_content(); ?></div>
  </article>
</div>
<?php get_footer(); ?>
