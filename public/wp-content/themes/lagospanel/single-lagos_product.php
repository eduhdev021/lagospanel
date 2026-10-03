<?php
/**
 * LagosPanel — Página de produto (loja)
 */
if (!defined('ABSPATH')) exit;
get_header();

the_post();
$p      = get_post();
$price  = get_post_meta($p->ID, '_lagos_price', true);
$cycle  = get_post_meta($p->ID, '_lagos_cycle', true);
$feats  = function_exists('lagos_features') ? lagos_features($p->ID) : [];
$terms  = get_the_terms($p->ID, 'lagos_cat');
$cat    = $terms && !is_wp_error($terms) ? $terms[0]->name : '';

if (is_user_logged_in() && function_exists('lagos_order_url')) {
    $order_url = lagos_order_url($p->ID);
} else {
    $order_url = home_url('/entrar/?redirect_to=' . rawurlencode(get_permalink($p)));
}
$opts = function_exists('lagos_product_options') ? lagos_product_options($p->ID) : [];
// moeda atual p/ preço dinâmico em JS
$cur_key  = function_exists('lagos_currency_current') ? lagos_currency_current() : 'BRL';
$curs     = function_exists('lagos_currencies') ? lagos_currencies() : ['BRL' => 1.0];
$cur_rate = (float) ($curs[$cur_key] ?? 1.0);
$cur_sym  = $cur_key === 'BRL' ? 'R$' : ($cur_key === 'USD' ? 'US$' : ($cur_key === 'EUR' ? '€' : $cur_key . ' '));
$cur_dec  = $cur_key === 'BRL' ? ',' : '.';
$cycle_key = $cycle === 'yearly' ? 'year' : ($cycle === 'one_time' ? 'one_time' : 'month');
?>

<div class="page-hero product-hero">
  <div class="wrap">
    <nav class="crumbs"><a href="<?php echo esc_url(home_url('/')); ?>"><?php lagos_e('nav_home'); ?></a> › <a href="<?php echo esc_url(home_url('/loja/')); ?>"><?php lagos_e('nav_store'); ?></a> › <span><?php echo esc_html($p->post_title); ?></span></nav>
    <div class="product-hero-grid">
      <div>
        <?php if ($cat) : ?><span class="chip chip-cat"><?php echo esc_html($cat); ?></span><?php endif; ?>
        <h1><?php echo esc_html($p->post_title); ?></h1>
        <p class="lead"><?php echo esc_html(get_the_excerpt($p)); ?></p>
      </div>
      <div class="price-card is-side">
        <div class="price-value">
          <small><?php lagos_e('from'); ?></small>
          <?php echo esc_html(lagos_money($price)); ?>
          <span class="price-cycle"><?php lagos_e($cycle_key); ?></span>
        </div>
        <?php if ($feats) : ?>
        <ul class="price-feats">
          <?php foreach ($feats as $f) : ?><li><?php echo function_exists('lagos_icon') ? lagos_icon('check', 15) : '•'; ?> <?php echo esc_html($f); ?></li><?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <?php if ($opts) : ?>
        </div><!-- fecha p/ reabrir abaixo com as opções -->
        <div class="price-feats-opt">
        <h3 class="opt-title"><?php lagos_e('opt_configure'); ?></h3>
        <form method="get" action="" id="lagos-optform" style="text-align:left;margin:0 0 6px">
          <input type="hidden" name="lagos_order" value="<?php echo esc_attr($p->ID); ?>" id="lagos-act">
          <input type="hidden" name="lagos_nonce" value="<?php echo esc_attr(wp_create_nonce('lagos_order_' . $p->ID)); ?>" id="lagos-nonce">
          <?php $oi = 0; foreach ($opts as $o) : $oi++; ?>
          <div class="opt-row" style="margin-bottom:12px">
            <label style="font-size:.82rem;color:#7C6BAE;font-weight:600;display:block;margin-bottom:5px"><?php echo esc_html($o['name']); ?></label>
            <?php if ($o['type'] === 'slider') :
                $labels = array_keys($o['choices']); ?>
              <div style="display:flex;align-items:center;gap:12px">
                <input type="range" min="0" max="<?php echo count($labels) - 1; ?>" step="1" value="0" class="lagos-slider" data-target="lagos-sel-<?php echo $oi; ?>" data-out="lagos-out-<?php echo $oi; ?>" style="flex:1;accent-color:#7C3AED">
                <output style="font-weight:700;color:#5B21B6;font-size:.9rem" id="lagos-out-<?php echo $oi; ?>"><?php echo esc_html($labels[0]); ?></output>
              </div>
              <select name="lagos_opt[<?php echo esc_attr($o['name']); ?>]" id="lagos-sel-<?php echo $oi; ?>" data-opt hidden>
                <?php foreach ($o['choices'] as $label => $extra) : ?><option value="<?php echo esc_attr($label); ?>" data-price="<?php echo esc_attr($extra); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?>
              </select>
            <?php elseif ($o['type'] === 'radio') : ?>
              <?php $ri = 0; foreach ($o['choices'] as $label => $extra) : $ri++; ?>
              <label style="display:flex;align-items:center;gap:8px;font-size:.88rem;padding:5px 0;cursor:pointer">
                <input type="radio" name="lagos_opt[<?php echo esc_attr($o['name']); ?>]" value="<?php echo esc_attr($label); ?>" data-price="<?php echo esc_attr($extra); ?>" data-opt<?php echo $ri === 1 ? ' checked' : ''; ?>> <?php echo esc_html($label); ?>
              </label>
              <?php endforeach; ?>
            <?php else : ?>
              <select name="lagos_opt[<?php echo esc_attr($o['name']); ?>]" data-opt style="width:100%;padding:9px 10px;border:1px solid #D8CFF2;border-radius:10px;background:#fff;font-size:.9rem">
                <?php foreach ($o['choices'] as $label => $extra) : ?><option value="<?php echo esc_attr($label); ?>" data-price="<?php echo esc_attr($extra); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?>
              </select>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
          <div class="opt-total" style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:#F7F5FC;border-radius:10px;margin:6px 0 12px">
            <span style="font-size:.82rem;color:#7C6BAE;font-weight:600"><?php lagos_e('opt_total_now'); ?></span>
            <strong id="lagos-total" style="font-size:1.15rem;color:#5B21B6" data-base="<?php echo esc_attr((float) $price); ?>" data-rate="<?php echo esc_attr($cur_rate); ?>" data-sym="<?php echo esc_attr($cur_sym); ?>" data-dec="<?php echo esc_attr($cur_dec); ?>"><?php echo esc_html(lagos_money($price)); ?></strong>
          </div>
          <button type="submit" class="btn btn-primary btn-block btn-lg"><?php lagos_e('order_now'); ?> <span class="arr">→</span></button>
          <?php if (function_exists('lagos_cart_add_url')) : ?>
          <button type="submit" data-mode="cart" data-pid="<?php echo esc_attr($p->ID); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('lagos_cart_' . $p->ID)); ?>" class="btn btn-ghost btn-block" style="margin-top:8px"><?php echo function_exists('lagos_icon') ? lagos_icon('cart', 15) : ''; ?> <?php lagos_e('add_to_cart'); ?></button>
          <?php endif; ?>
        </form>
        <?php else : ?>
        <a class="btn btn-primary btn-block btn-lg" href="<?php echo esc_url($order_url); ?>"><?php lagos_e('order_now'); ?> <span class="arr">→</span></a>
        <?php if (function_exists('lagos_cart_add_url')) : ?>
        <a class="btn btn-ghost btn-block" style="margin-top:8px" href="<?php echo esc_url(lagos_cart_add_url($p->ID)); ?>"><?php echo function_exists('lagos_icon') ? lagos_icon('cart', 15) : ''; ?> <?php lagos_e('add_to_cart'); ?></a>
        <?php endif; ?>
        <?php endif; ?>
        <p class="price-note"><?php lagos_e('terms'); ?></p>
      </div>
    </div>
  </div>
</div>

<?php if (trim($p->post_content)) : ?>
<section class="section">
  <div class="wrap narrow">
    <div class="post-body"><?php echo apply_filters('the_content', $p->post_content); ?></div>
  </div>
</section>
<?php endif; ?>

<script>
(function(){
  var total = document.getElementById('lagos-total');
  if (!total) return;
  var base = parseFloat(total.dataset.base), rate = parseFloat(total.dataset.rate) || 1,
      sym = total.dataset.sym, dec = total.dataset.dec;
  function fmt(v){ var n = v / rate, s = n.toFixed(dec === ',' ? 2 : 2); return sym + ' ' + (dec === ',' ? s.replace('.', ',') : s); }
  function calc(){
    var extra = 0;
    document.querySelectorAll('[data-opt]').forEach(function(el){
      if (el.tagName === 'SELECT') { var o = el.options[el.selectedIndex]; if (o) extra += parseFloat(o.dataset.price || 0); }
      else if (el.type === 'radio' && el.checked) extra += parseFloat(el.dataset.price || 0);
    });
    total.textContent = fmt(base + extra);
  }
  document.querySelectorAll('[data-opt]').forEach(function(el){
    el.addEventListener('change', calc);
    if (el.tagName === 'SELECT') el.addEventListener('input', calc);
  });
  document.querySelectorAll('.lagos-slider').forEach(function(sl){
    sl.addEventListener('input', function(){
      var sel = document.getElementById(sl.dataset.target);
      if (sel) { sel.selectedIndex = parseInt(sl.value, 10); calc(); }
      var out = document.getElementById(sl.dataset.out);
      if (sel && out) out.textContent = sel.options[sel.selectedIndex].text;
    });
  });
  calc();
  document.querySelectorAll('[data-mode]').forEach(function(b){
    b.addEventListener('click', function(){
      var act = document.getElementById('lagos-act');
      act.name = 'lagos_cart_add'; act.value = b.dataset.pid;
      document.getElementById('lagos-nonce').value = b.dataset.nonce;
    });
  });
})();
</script>
<?php get_footer(); ?>
