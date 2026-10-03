<?php
/**
 * LagosPanel — Landing page
 */
if (!defined('ABSPATH')) exit;
get_header();
$icon = function ($n, $s = 22) {
    return function_exists('lagos_icon') ? lagos_icon($n, $s) : '';
};

// Produtos em destaque para a seção de preços
$featured = function_exists('lagos_get_products')
    ? lagos_get_products(['featured' => true, 'limit' => 3])
    : [];
if (!$featured) {
    $featured = function_exists('lagos_get_products') ? lagos_get_products(['limit' => 3]) : [];
}
$stat_icons = ['user', 'zap', 'ticket', 'api'];
?>

<!-- HERO -->
<section class="hero">
  <div class="wrap hero-inner">
    <span class="hero-badge"><?php lagos_e('hero_badge'); ?></span>
    <h1 class="hero-title"><?php lagos_e('hero_title'); ?></h1>
    <p class="hero-sub"><?php lagos_e('hero_sub'); ?></p>
    <div class="hero-cta">
      <a class="btn btn-primary btn-lg" href="<?php echo esc_url(home_url('/registrar/')); ?>"><?php lagos_e('hero_cta'); ?> <span class="arr">→</span></a>
      <a class="btn btn-ghost-light btn-lg" href="<?php echo esc_url(home_url('/loja/')); ?>"><?php lagos_e('hero_cta2'); ?></a>
    </div>
    <div class="hero-stats">
      <div class="hstat"><strong><?php lagos_e('stat1_v'); ?></strong><span><?php lagos_e('stat1_l'); ?></span></div>
      <div class="hstat"><strong><?php lagos_e('stat2_v'); ?></strong><span><?php lagos_e('stat2_l'); ?></span></div>
      <div class="hstat"><strong><?php lagos_e('stat3_v'); ?></strong><span><?php lagos_e('stat3_l'); ?></span></div>
      <div class="hstat"><strong><?php lagos_e('stat4_v'); ?></strong><span><?php lagos_e('stat4_l'); ?></span></div>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section class="section" id="features">
  <div class="wrap">
    <div class="section-head">
      <h2><?php lagos_e('features_title'); ?></h2>
      <p><?php lagos_e('features_sub'); ?></p>
    </div>
    <div class="features-grid">
      <div class="feature-card"><div class="feature-ic ic-blue"><?php echo $icon('card'); ?></div><h3><?php lagos_e('f1t'); ?></h3><p><?php lagos_e('f1d'); ?></p></div>
      <div class="feature-card"><div class="feature-ic ic-cyan"><?php echo $icon('zap'); ?></div><h3><?php lagos_e('f2t'); ?></h3><p><?php lagos_e('f2d'); ?></p></div>
      <div class="feature-card"><div class="feature-ic ic-violet"><?php echo $icon('ticket'); ?></div><h3><?php lagos_e('f3t'); ?></h3><p><?php lagos_e('f3d'); ?></p></div>
      <div class="feature-card"><div class="feature-ic ic-green"><?php echo $icon('globe'); ?></div><h3><?php lagos_e('f4t'); ?></h3><p><?php lagos_e('f4d'); ?></p></div>
      <div class="feature-card"><div class="feature-ic ic-orange"><?php echo $icon('shield'); ?></div><h3><?php lagos_e('f5t'); ?></h3><p><?php lagos_e('f5d'); ?></p></div>
      <div class="feature-card"><div class="feature-ic ic-pink"><?php echo $icon('api'); ?></div><h3><?php lagos_e('f6t'); ?></h3><p><?php lagos_e('f6d'); ?></p></div>
    </div>
  </div>
</section>

<!-- PREÇOS -->
<?php if ($featured) : ?>
<section class="section section-alt" id="precos">
  <div class="wrap">
    <div class="section-head">
      <h2><?php lagos_e('pricing_title'); ?></h2>
      <p><?php lagos_e('pricing_sub'); ?></p>
    </div>
    <div class="pricing-grid">
      <?php foreach ($featured as $p) :
          $price   = get_post_meta($p->ID, '_lagos_price', true);
          $cycle   = get_post_meta($p->ID, '_lagos_cycle', true);
          $feats   = array_slice(lagos_features($p->ID), 0, 5);
          $is_feat = get_post_meta($p->ID, '_lagos_featured', true);
      ?>
      <div class="price-card<?php echo $is_feat ? ' is-featured' : ''; ?>">
        <?php if ($is_feat) : ?><span class="ribbon"><?php lagos_e('featured_badge'); ?></span><?php endif; ?>
        <h3><?php echo esc_html($p->post_title); ?></h3>
        <p class="price-tag"><?php echo esc_html(get_the_excerpt($p)); ?></p>
        <div class="price-value">
          <small><?php lagos_e('from'); ?></small>
          <?php echo esc_html(lagos_money($price)); ?>
          <span class="price-cycle"><?php lagos_e($cycle === 'yearly' ? 'year' : ($cycle === 'one_time' ? 'one_time' : 'month')); ?></span>
        </div>
        <ul class="price-feats">
          <?php foreach ($feats as $f) : ?><li><?php echo $icon('check', 15); ?> <?php echo esc_html($f); ?></li><?php endforeach; ?>
        </ul>
        <a class="btn btn-primary btn-block" href="<?php echo esc_url(get_permalink($p)); ?>"><?php lagos_e('order_now'); ?></a>
      </div>
      <?php endforeach; ?>
    </div>
    <p class="center"><a class="btn btn-ghost btn-lg" href="<?php echo esc_url(home_url('/loja/')); ?>"><?php lagos_e('view_all'); ?> →</a></p>
  </div>
</section>
<?php endif; ?>

<!-- CTA FINAL -->
<section class="cta-band">
  <div class="wrap">
    <h2><?php lagos_e('cta_title'); ?></h2>
    <p><?php lagos_e('cta_sub'); ?></p>
    <a class="btn btn-light btn-lg" href="<?php echo esc_url(home_url('/registrar/')); ?>"><?php lagos_e('cta_btn'); ?> <span class="arr">→</span></a>
  </div>
</section>

<?php get_footer(); ?>
