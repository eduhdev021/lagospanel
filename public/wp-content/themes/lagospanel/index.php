<?php
/**
 * LagosPanel — Fallback (blog e arquivos)
 */
if (!defined('ABSPATH')) exit;
get_header();
?>
<div class="page-hero">
  <div class="wrap">
    <h1><?php
    if (is_home()) lagos_e('announcements');
    elseif (is_search()) printf('Busca: %s', esc_html(get_search_query()));
    else the_archive_title();
    ?></h1>
  </div>
</div>
<div class="wrap page-content narrow">
  <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
    <article class="post-single" style="padding:0 0 40px">
      <div class="post-meta"><?php echo esc_html(get_the_date()); ?></div>
      <h2 style="margin-top:6px"><a href="<?php the_permalink(); ?>" style="color:inherit"><?php the_title(); ?></a></h2>
      <div class="post-body"><?php the_excerpt(); ?></div>
    </article>
  <?php endwhile; else : ?>
    <p class="text-muted"><?php lagos_e('no_data'); ?></p>
  <?php endif; ?>
</div>
<?php get_footer(); ?>
