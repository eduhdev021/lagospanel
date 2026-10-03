<?php
/**
 * LagosPanel Core — Carrinho de compras + Cupons (v0.2)
 */
if (!defined('ABSPATH')) exit;

/** Sessão PHP para o carrinho */
add_action('init', function () {
    if (!headers_sent() && !session_id() && !wp_doing_cron() && !wp_doing_ajax()) {
        session_start();
    }
});

function lagos_cart_get() {
    if (!isset($_SESSION['lagos_cart'])) {
        $_SESSION['lagos_cart'] = ['items' => [], 'coupon' => ''];
    }
    return $_SESSION['lagos_cart'];
}

function lagos_cart_count() {
    $c = lagos_cart_get();
    return array_sum(array_map('intval', $c['items']));
}

/** Linhas do carrinho: [product, qty, unit, subtotal] */
function lagos_cart_lines() {
    $cart  = lagos_cart_get();
    $lines = [];
    foreach ($cart['items'] as $key => $it) {
        if (is_array($it)) { $pid = (int) ($it['pid'] ?? 0); $qty = (int) ($it['qty'] ?? 1); $sel = (array) ($it['opts'] ?? []); }
        else { $pid = (int) $key; $qty = (int) $it; $sel = []; }
        $p = get_post($pid);
        if (!$p || $p->post_type !== 'lagos_product') continue;
        $unit  = (float) get_post_meta($pid, '_lagos_price', true);
        $label = $p->post_title;
        if ($sel && function_exists('lagos_options_extra')) {
            $x = lagos_options_extra($pid, $sel);
            $unit += $x['extra'];
            if ($x['labels']) $label .= ' (' . implode(', ', $x['labels']) . ')';
        }
        $lines[] = ['key' => (string) $key, 'product' => $p, 'qty' => $qty, 'unit' => $unit, 'subtotal' => $unit * $qty, 'opts' => $sel, 'label' => $label];
    }
    return $lines;
}

/** Valida cupom pelo código (título) */
function lagos_coupon_validate($code) {
    $code = strtoupper(trim((string) $code));
    if (!$code) return null;
    $found = get_posts([
        'post_type' => 'lagos_coupon', 'post_status' => 'publish', 'numberposts' => 1,
        'name' => sanitize_title($code),
    ]);
    if (!$found) return null;
    $c = $found[0];
    if (get_post_meta($c->ID, '_lagos_active', true) !== '1') return null;
    return $c;
}

/** Totais com desconto */
function lagos_cart_totals() {
    $cart     = lagos_cart_get();
    $lines    = lagos_cart_lines();
    $subtotal = 0;
    foreach ($lines as $l) $subtotal += $l['subtotal'];

    $discount = 0;
    $coupon   = null;
    if ($cart['coupon']) {
        $coupon = lagos_coupon_validate($cart['coupon']);
        if ($coupon) {
            $v   = (float) get_post_meta($coupon->ID, '_lagos_value', true);
            $typ = get_post_meta($coupon->ID, '_lagos_type', true);
            $discount = ($typ === 'percent') ? $subtotal * ($v / 100) : $v;
            $discount = min($discount, $subtotal);
        }
    }
    return [
        'lines' => $lines, 'subtotal' => $subtotal, 'discount' => $discount,
        'total' => max(0, $subtotal - $discount), 'coupon' => $coupon,
    ];
}

function lagos_cart_add_url($pid) {
    return wp_nonce_url(add_query_arg('lagos_cart_add', $pid, home_url('/carrinho/')), 'lagos_cart_' . $pid, 'lagos_nonce');
}

function lagos_cart_remove_url($pid) {
    $pid = (string) $pid; // aceita id puro ou chave com opções (ex.: 34-a1b2c3d4)
    return wp_nonce_url(add_query_arg('lagos_cart_remove', $pid, home_url('/carrinho/')), 'lagos_cart_' . $pid, 'lagos_nonce');
}

/* =========================================================
   HANDLERS
   ========================================================= */

// GET: adicionar / remover item
add_action('template_redirect', function () {
    if (isset($_GET['lagos_cart_add'])) {
        $pid = absint($_GET['lagos_cart_add']);
        check_admin_referer('lagos_cart_' . $pid, 'lagos_nonce');
        $p = get_post($pid);
        if ($p && $p->post_type === 'lagos_product') {
            $sel = [];
            if (function_exists('lagos_options_sanitize') && !empty($_GET['lagos_opt'])) {
                $sel = lagos_options_sanitize($pid, (array) wp_unslash($_GET['lagos_opt']));
            }
            $key  = $sel ? $pid . '-' . substr(md5((string) wp_json_encode($sel)), 0, 8) : (string) $pid;
            $cart = lagos_cart_get();
            $cur  = isset($cart['items'][$key]) ? (is_array($cart['items'][$key]) ? (int) $cart['items'][$key]['qty'] : (int) $cart['items'][$key]) : 0;
            $cart['items'][$key] = ['pid' => $pid, 'qty' => min(10, $cur + 1), 'opts' => $sel];
            $_SESSION['lagos_cart'] = $cart;
        }
        wp_safe_redirect(home_url('/carrinho/'));
        exit;
    }
    if (isset($_GET['lagos_cart_remove'])) {
        $key = preg_replace('/[^0-9a-zA-Z-]/', '', (string) $_GET['lagos_cart_remove']);
        check_admin_referer('lagos_cart_' . $key, 'lagos_nonce');
        $cart = lagos_cart_get();
        unset($cart['items'][$key]);
        $_SESSION['lagos_cart'] = $cart;
        wp_safe_redirect(home_url('/carrinho/'));
        exit;
    }
});

// POST: atualizar qtd / cupom / checkout
add_action('template_redirect', function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['lagos_action'])) return;
    $action = sanitize_key($_POST['lagos_action']);

    if (!in_array($action, ['cart_update', 'cart_coupon', 'cart_checkout'], true)) return;
    if (!isset($_POST['lagos_nonce']) || !wp_verify_nonce($_POST['lagos_nonce'], 'lagos_cart')) return;

    $cart = lagos_cart_get();

    // ---- atualizar quantidades ----
    if ($action === 'cart_update') {
        foreach ((array) ($_POST['qty'] ?? []) as $pid => $q) {
            $key = preg_replace('/[^0-9a-zA-Z-]/', '', (string) $pid);
            $q   = (int) $q;
            if ($q <= 0) { unset($cart['items'][$key]); continue; }
            if (isset($cart['items'][$key]) && is_array($cart['items'][$key])) $cart['items'][$key]['qty'] = min(10, $q);
            elseif (preg_match('/^-?\d+$/', $key)) $cart['items'][$key] = min(10, $q);
        }
        $_SESSION['lagos_cart'] = $cart;
        wp_safe_redirect(add_query_arg('lagos_msg', 'cart_updated', home_url('/carrinho/')));
        exit;
    }

    // ---- aplicar cupom ----
    if ($action === 'cart_coupon') {
        $code = strtoupper(sanitize_text_field(wp_unslash($_POST['coupon'] ?? '')));
        if ($code === '') {
            $cart['coupon'] = '';
            $_SESSION['lagos_cart'] = $cart;
            wp_safe_redirect(add_query_arg('lagos_msg', 'cart_updated', home_url('/carrinho/')));
            exit;
        }
        if (lagos_coupon_validate($code)) {
            $cart['coupon'] = $code;
            $_SESSION['lagos_cart'] = $cart;
            wp_safe_redirect(add_query_arg('lagos_msg', 'coupon_ok', home_url('/carrinho/')));
        } else {
            wp_safe_redirect(add_query_arg('lagos_err', 'coupon_invalid', home_url('/carrinho/')));
        }
        exit;
    }

    // ---- checkout: cria serviços + UMA fatura com todos os itens ----
    if ($action === 'cart_checkout') {
        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/entrar/?redirect_to=' . rawurlencode('/carrinho/')));
            exit;
        }
        $uid    = get_current_user_id();
        $totals = lagos_cart_totals();
        if (!$totals['lines']) {
            wp_safe_redirect(home_url('/carrinho/'));
            exit;
        }

        $first_sid = 0;
        $all_sids  = [];
        $items     = [];

        foreach ($totals['lines'] as $l) {
            $cycle = get_post_meta($l['product']->ID, '_lagos_cycle', true) ?: 'monthly';
            for ($i = 0; $i < $l['qty']; $i++) {
                $sid = wp_insert_post(['post_type' => 'lagos_service', 'post_status' => 'publish', 'post_title' => ($l['label'] ?? $l['product']->post_title)]);
                if (!$sid || is_wp_error($sid)) continue;
                update_post_meta($sid, '_lagos_user', $uid);
                update_post_meta($sid, '_lagos_product', $l['product']->ID);
                update_post_meta($sid, '_lagos_status', 'pending');
                update_post_meta($sid, '_lagos_price', $l['unit']);
                update_post_meta($sid, '_lagos_cycle', $cycle);
                update_post_meta($sid, '_lagos_next_due', date('Y-m-d', strtotime('+' . ($cycle === 'yearly' ? '1 year' : '30 days'))));
                if (!empty($l['opts'])) update_post_meta($sid, '_lagos_opts', wp_json_encode($l['opts'], JSON_UNESCAPED_UNICODE));
                $all_sids[] = $sid;
                if (!$first_sid) $first_sid = $sid;
            }
            $items[] = ($l['label'] ?? ($l['label'] ?? $l['product']->post_title)) . ' | ' . $l['qty'] . ' | ' . number_format($l['subtotal'], 2, ',', '.');
        }

        if ($totals['discount'] > 0 && $totals['coupon']) {
            $items[] = 'Cupom ' . $totals['coupon']->post_title . ' | 1 | -' . number_format($totals['discount'], 2, ',', '.');
        }

        $iid = wp_insert_post([
            'post_type'   => 'lagos_invoice',
            'post_status' => 'publish',
            'post_title'  => 'FAT-' . lagos_invoice_ref($first_sid),
        ]);
        update_post_meta($iid, '_lagos_user', $uid);
        update_post_meta($iid, '_lagos_amount', $totals['total']);
        update_post_meta($iid, '_lagos_status', 'unpaid');
        update_post_meta($iid, '_lagos_due', date('Y-m-d', strtotime('+5 days')));
        update_post_meta($iid, '_lagos_items', implode("\n", $items));
        update_post_meta($iid, '_lagos_services', implode(',', $all_sids));
        if ($totals['coupon']) {
            update_post_meta($iid, '_lagos_coupon', $totals['coupon']->post_title);
            update_post_meta($iid, '_lagos_discount', $totals['discount']);
        }
        if (function_exists('lagos_mail_invoice')) lagos_mail_invoice($iid);

        // limpa carrinho
        $_SESSION['lagos_cart'] = ['items' => [], 'coupon' => ''];

        wp_safe_redirect(lagos_panel_url('faturas') . '?lagos_msg=checkout_success');
        exit;
    }
});

/* =========================================================
   SHORTCODE — página do carrinho
   ========================================================= */
add_shortcode('lagos_cart', function () {
    $t = lagos_cart_totals();
    ob_start();
    ?>
    <div class="store-head">
      <h2><?php lagos_e('cart_title'); ?> </h2>
      <p><?php lagos_e('cart_sub'); ?></p>
    </div>

    <?php echo lagos_flash(); ?>

    <?php if (!$t['lines']) : ?>
      <div class="card center">
        <p class="text-muted" style="font-size:1.05rem"><?php lagos_e('cart_empty'); ?></p>
        <a class="btn btn-primary" href="<?php echo esc_url(home_url('/loja/')); ?>"><?php lagos_e('cart_continue'); ?> →</a>
      </div>
    <?php else : ?>

      <form method="post">
        <input type="hidden" name="lagos_action" value="cart_update">
        <?php wp_nonce_field('lagos_cart', 'lagos_nonce'); ?>
        <div class="ltable-wrap">
          <table class="ltable">
            <thead><tr>
              <th><?php lagos_e('col_item'); ?></th>
              <th><?php lagos_e('col_price'); ?></th>
              <th style="width:110px"><?php lagos_e('col_qty'); ?></th>
              <th><?php lagos_e('col_subtotal'); ?></th>
              <th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($t['lines'] as $l) : $pid = $l['product']->ID; $ckey = $l['key'] ?? (string) $pid; ?>
              <tr>
                <td class="t-strong"><a href="<?php echo esc_url(get_permalink($pid)); ?>"><?php echo esc_html(($l['label'] ?? $l['product']->post_title)); ?></a></td>
                <td class="t-muted"><?php echo esc_html(lagos_money($l['unit'])); ?></td>
                <td><input type="number" name="qty[<?php echo esc_attr($ckey); ?>]" value="<?php echo esc_attr($l['qty']); ?>" min="0" max="10" style="width:74px;padding:7px 10px;border:1px solid var(--line);border-radius:8px"></td>
                <td class="t-money"><?php echo esc_html(lagos_money($l['subtotal'])); ?></td>
                <td><a class="icon-btn" style="color:#DC2626" href="<?php echo esc_url(lagos_cart_remove_url($ckey)); ?>" title="<?php echo esc_attr(lagos_t('remove')); ?>"><?php echo lagos_icon('x', 15); ?></a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p style="margin-top:14px"><button type="submit" class="btn btn-ghost btn-sm"><?php lagos_e('update'); ?></button></p>
      </form>

      <div class="card" style="margin-top:18px">
        <div class="flex-between">
          <form method="post" style="display:flex;gap:8px;flex:1;max-width:420px">
            <input type="hidden" name="lagos_action" value="cart_coupon">
            <?php wp_nonce_field('lagos_cart', 'lagos_nonce'); ?>
            <input type="text" name="coupon" placeholder="<?php echo esc_attr(lagos_t('coupon_ph')); ?>" value="<?php echo esc_attr($t['coupon'] ? $t['coupon']->post_title : ''); ?>" style="flex:1;padding:10px 14px;border:1px solid var(--line);border-radius:10px">
            <button type="submit" class="btn btn-ghost"><?php lagos_e('coupon_apply'); ?></button>
          </form>
          <div style="text-align:right">
            <div class="text-muted" style="font-size:.85rem"><?php lagos_e('col_total'); ?></div>
            <div style="font-size:1.6rem;font-weight:800;letter-spacing:-.02em"><?php echo esc_html(lagos_money($t['total'])); ?></div>
            <?php if ($t['discount'] > 0) : ?>
              <div style="color:#16A34A;font-weight:600;font-size:.85rem"><?php lagos_e('discount'); ?>: −<?php echo esc_html(lagos_money($t['discount'])); ?></div>
            <?php endif; ?>
          </div>
        </div>
        <form method="post" style="margin-top:18px">
          <input type="hidden" name="lagos_action" value="cart_checkout">
          <?php wp_nonce_field('lagos_cart', 'lagos_nonce'); ?>
          <?php if (is_user_logged_in()) : ?>
            <button type="submit" class="btn btn-primary btn-lg"><?php lagos_e('checkout'); ?> <span class="arr">→</span></button>
          <?php else : ?>
            <a class="btn btn-primary btn-lg" href="<?php echo esc_url(home_url('/entrar/?redirect_to=/carrinho/')); ?>"><?php lagos_e('login_to_checkout'); ?> →</a>
          <?php endif; ?>
          <a class="btn btn-ghost btn-lg" href="<?php echo esc_url(home_url('/loja/')); ?>"><?php lagos_e('cart_continue'); ?></a>
        </form>
      </div>

    <?php endif;
    return ob_get_clean();
});
