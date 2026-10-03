<?php
/**
 * LagosPanel Core — Helpers
 */
if (!defined('ABSPATH')) exit;

/** Formata dinheiro (BRL) */
/* ═══ MULTI-MOEDA (v0.8) — base BRL, taxas configuráveis ═══ */
function lagos_currencies() {
    $c = get_option('lagos_currencies', null);
    if (!is_array($c) || empty($c['BRL'])) {
        $c = ['BRL' => 1.0, 'USD' => 5.35, 'EUR' => 5.80];
        update_option('lagos_currencies', $c);
    }
    return $c;
}
function lagos_currency_current() {
    $cur = strtoupper(trim($_COOKIE['lagos_currency'] ?? ''));
    $list = lagos_currencies();
    return isset($list[$cur]) ? $cur : 'BRL';
}
function lagos_currency_symbol($code) {
    $map = ['BRL' => 'R$ ', 'USD' => 'US$ ', 'EUR' => '€ ', 'GBP' => '£ ', 'ARS' => 'AR$ '];
    return $map[$code] ?? $code . ' ';
}
/** Converte um valor da moeda base (BRL) para a moeda informada */
function lagos_money_convert($v_brl, $to) {
    $list = lagos_currencies();
    $rate = max(0.0001, (float) ($list[$to] ?? 1));
    return ((float) $v_brl) / $rate;
}
/** Formata valor (armazenado em BRL) na moeda corrente ou informada */
function lagos_money($v, $currency = null) {
    $cur  = $currency ?: lagos_currency_current();
    $val  = lagos_money_convert($v, $cur);
    $sym  = lagos_currency_symbol($cur);
    if ($cur === 'BRL') return $sym . number_format($val, 2, ',', '.');
    return $sym . number_format($val, 2, '.', ',');
}
/** Troca de moeda via ?currency=XXX (cookie + redirect limpo) */
add_action('init', function () {
    if (!isset($_GET['currency'])) return;
    $cur = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $_GET['currency']), 0, 3));
    $list = lagos_currencies();
    if (!isset($list[$cur])) return;
    setcookie('lagos_currency', $cur, time() + 31536000, '/');
    $uri   = $_SERVER['REQUEST_URI'] ?? '/';
    $parts = parse_url($uri);
    $path  = $parts['path'] ?? '/';
    parse_str($parts['query'] ?? '', $q);
    unset($q['currency']);
    wp_safe_redirect(home_url($path . ($q ? '?' . http_build_query($q) : '')));
    exit;
});
/** Seletor de moeda (mesmo visual do seletor de idioma) */
function lagos_currency_switcher() {
    $cur = lagos_currency_current();
    $out = '<div class="lang-switch cur-switch" aria-label="Moeda">';
    foreach (lagos_currencies() as $code => $rate) {
        $active = $code === $cur ? ' is-active' : '';
        $label  = $code === 'BRL' ? 'R$' : ($code === 'EUR' ? '€' : ($code === 'USD' ? '$' : $code));
        $out .= '<a class="lang-btn' . $active . '" href="' . esc_url(add_query_arg('currency', $code)) . '" title="' . esc_attr($code) . '">' . $label . '</a>';
    }
    return $out . '</div>';
}

/** URL de páginas do painel */
function lagos_panel_url($path = '') {
    return home_url('/painel/' . ltrim($path, '/'));
}

/** Badge de status */
function lagos_status_badge($status) {
    return '<span class="badge badge-' . esc_attr($status) . '"><span class="badge-dot"></span>' . esc_html(lagos_t('status_' . $status)) . '</span>';
}

/** Recursos de um produto (um por linha) */
function lagos_features($product_id) {
    $raw = get_post_meta($product_id, '_lagos_features', true);
    if (!$raw) return [];
    return array_values(array_filter(array_map('trim', explode("\n", $raw))));
}

/** Produtos da loja */
function lagos_get_products($args = []) {
    $q = [
        'post_type' => 'lagos_product', 'numberposts' => isset($args['limit']) ? $args['limit'] : -1,
        'orderby' => 'menu_order title', 'order' => 'ASC',
    ];
    if (!empty($args['featured'])) {
        $q['meta_query'] = [['key' => '_lagos_featured', 'value' => '1']];
    }
    if (!empty($args['cat'])) {
        $q['tax_query'] = [['taxonomy' => 'lagos_cat', 'field' => 'slug', 'terms' => sanitize_title($args['cat'])]];
    }
    return get_posts($q);
}

/** Itens de um usuário */
function lagos_user_items($type, $uid, $limit = -1) {
    return get_posts([
        'post_type' => $type, 'numberposts' => $limit, 'post_status' => 'publish',
        'orderby' => 'date', 'order' => 'DESC',
        'meta_query' => [['key' => '_lagos_user', 'value' => $uid]],
    ]);
}

function lagos_user_services($uid, $limit = -1) { return lagos_user_items('lagos_service', $uid, $limit); }
function lagos_user_invoices($uid, $limit = -1) { return lagos_user_items('lagos_invoice', $uid, $limit); }
function lagos_user_tickets($uid, $limit = -1)  { return lagos_user_items('lagos_ticket', $uid, $limit); }

/** Contadores do dashboard */
function lagos_user_counts($uid) {
    $out = ['active' => 0, 'unpaid' => 0, 'unpaid_total' => 0, 'tickets' => 0];

    foreach (lagos_user_services($uid) as $s) {
        if (get_post_meta($s->ID, '_lagos_status', true) === 'active') $out['active']++;
    }
    foreach (lagos_user_invoices($uid) as $i) {
        $st = get_post_meta($i->ID, '_lagos_status', true);
        if ($st === 'unpaid' || $st === 'overdue') {
            $out['unpaid']++;
            $out['unpaid_total'] += (float) get_post_meta($i->ID, '_lagos_amount', true);
        }
    }
    foreach (lagos_user_tickets($uid) as $t) {
        $st = get_post_meta($t->ID, '_lagos_status', true);
        if ($st !== 'closed') $out['tickets']++;
    }
    return $out;
}

/** Saldo do cliente */
function lagos_balance($uid) {
    return (float) get_user_meta($uid, 'lagos_balance', true);
}

/** Mensagens flash via GET (?lagos_msg=chave / ?lagos_err=chave) */
function lagos_flash() {
    $out = '';
    if (!empty($_GET['lagos_msg'])) {
        $key = sanitize_key($_GET['lagos_msg']);
        $out .= '<div class="notice-ok">' . lagos_icon('check', 18) . ' ' . esc_html(lagos_t($key)) . '</div>';
    }
    if (!empty($_GET['lagos_err'])) {
        $key = sanitize_key($_GET['lagos_err']);
        $out .= '<div class="notice-err">' . lagos_icon('alert', 18) . ' ' . esc_html(lagos_t($key)) . '</div>';
    }
    return $out;
}

/** URL de pedido de produto (com nonce) */
function lagos_order_url($product_id) {
    return wp_nonce_url(add_query_arg('lagos_order', $product_id, get_permalink($product_id)), 'lagos_order_' . $product_id, 'lagos_nonce');
}

/** Data formatada */
function lagos_date($date) {
    if (!$date) return '—';
    return date_i18n('d/m/Y', strtotime($date));
}

/** QR Code "fake" (visual) gerado deterministicamente */
function lagos_fake_qr($seed, $size = 25) {
    $hash = md5('lagos' . $seed);
    $cells = [];
    for ($i = 0; $i < 256; $i++) $hash = md5($hash . $i);
    // matriz aleatória determinística
    mt_srand(crc32($seed));
    $grid = [];
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            $grid[$y][$x] = (mt_rand(0, 100) < 46) ? 1 : 0;
        }
    }
    // padrões de localização (finder patterns)
    $finder = function (&$grid, $ox, $oy) use ($size) {
        for ($y = 0; $y < 7; $y++) {
            for ($x = 0; $x < 7; $x++) {
                $border = ($x === 0 || $x === 6 || $y === 0 || $y === 6);
                $core   = ($x >= 2 && $x <= 4 && $y >= 2 && $y <= 4);
                $grid[$oy + $y][$ox + $x] = ($border || $core) ? 1 : 0;
            }
        }
    };
    $finder($grid, 0, 0);
    $finder($grid, $size - 7, 0);
    $finder($grid, 0, $size - 7);

    $rects = '';
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            if ($grid[$y][$x]) $rects .= '<rect x="' . $x . '" y="' . $y . '" width="1" height="1"/>';
        }
    }
    return '<svg viewBox="0 0 ' . $size . ' ' . $size . '" shape-rendering="crispEdges" xmlns="http://www.w3.org/2000/svg"><rect width="' . $size . '" height="' . $size . '" fill="#fff"/><g fill="#120B24">' . $rects . '</g></svg>';
}

/** Código Pix "fake" */
function lagos_fake_pix_code($invoice_id, $amount) {
    $amount = number_format((float) $amount, 2, '.', '');
    return sprintf(
        '00020126580014BR.GOV.BCB.PIX0136%s5204000053039865405%s5802BR5913LAGOSPANEL6009SAO PAULO62070503***6304%s',
        md5('lagos' . $invoice_id),
        $amount,
        strtoupper(substr(md5($invoice_id . $amount), 0, 4))
    );
}

/** Chave de API do cliente */
function lagos_api_key($uid, $regen = false) {
    $key = get_user_meta($uid, 'lagos_api_key', true);
    if (!$key || $regen) {
        $key = 'lagos_live_' . wp_generate_password(32, false, false);
        update_user_meta($uid, 'lagos_api_key', $key);
    }
    return $key;
}

/** Bloqueia wp-admin para clientes (admin-post.php fica liberado: é o endpoint
 *  de formulários do front-end e cada handler valida nonce/permissão própria) */
add_action('admin_init', function () {
    if (wp_doing_ajax()) return;
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'admin-post.php') return;
    if (!current_user_can('manage_options')) {
        wp_redirect(lagos_panel_url(''));
        exit;
    }
});

/* ══════════════════════════════════════════════════════════
   OPÇÕES CONFIGURÁVEIS DO PRODUTO (v0.10 — estilo WHMCS)
   Formato (uma por linha):  Nome | tipo | escolha=acréscimo;escolha=acréscimo
   Tipos: select · radio · slider · text (livre, sem preço)
   Ex.: Memória RAM (GB) | slider | 2=0;4=20;8=60
   ══════════════════════════════════════════════════════════ */
function lagos_product_options($pid) {
    $raw = (string) get_post_meta($pid, '_lagos_options', true);
    $opts = [];
    foreach (explode("\n", $raw) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $parts = array_map('trim', explode('|', $line));
        if (count($parts) < 2) continue;
        $name = $parts[0];
        $type = in_array($parts[1], ['select', 'radio', 'slider', 'text'], true) ? $parts[1] : 'select';
        $choices = [];
        if ($type !== 'text' && isset($parts[2])) {
            foreach (explode(';', $parts[2]) as $ch) {
                $ch = trim($ch);
                if ($ch === '' || strpos($ch, '=') === false) continue;
                [$label, $extra] = array_map('trim', explode('=', $ch, 2));
                $choices[$label] = (float) str_replace(',', '.', $extra);
            }
        }
        if ($name !== '' && ($type === 'text' || $choices)) $opts[] = ['name' => $name, 'type' => $type, 'choices' => $choices];
    }
    return $opts;
}

/** Acréscimo de preço + rótulos das escolhas (['Nome da opção' => 'escolha', ...]) */
function lagos_options_extra($pid, $sel) {
    $extra = 0.0;
    $labels = [];
    foreach (lagos_product_options($pid) as $o) {
        $v = trim((string) ($sel[$o['name']] ?? ''));
        if ($v === '') continue;
        if ($o['type'] === 'text') { $labels[] = $o['name'] . ': ' . $v; continue; }
        foreach ($o['choices'] as $label => $price) {
            if ($label === $v) { $extra += $price; $labels[] = $o['name'] . ': ' . $label; break; }
        }
    }
    return ['extra' => $extra, 'labels' => $labels];
}

/** Mantém apenas escolhas válidas */
function lagos_options_sanitize($pid, $sel) {
    $clean = [];
    foreach (lagos_product_options($pid) as $o) {
        $v = trim((string) ($sel[$o['name']] ?? ''));
        if ($o['type'] === 'text') { if ($v !== '') $clean[$o['name']] = sanitize_text_field($v); continue; }
        if (isset($o['choices'][$v])) $clean[$o['name']] = $v;
    }
    return $clean;
}
