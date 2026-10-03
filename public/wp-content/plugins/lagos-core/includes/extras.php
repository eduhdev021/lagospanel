<?php
/**
 * LagosPanel Core — Extras (v0.5)
 * Busca de domínios (RDAP), Downloads, Status da rede, Avaliação de tickets.
 */
if (!defined('ABSPATH')) exit;

/* =========================================================
   BUSCA DE DOMÍNIOS (RDAP — sem API key)
   ========================================================= */
function lagos_domain_check($domain) {
    $domain = strtolower(trim($domain));
    if (!preg_match('/^[a-z0-9à-ú][a-z0-9à-ú\-.]{1,60}\.[a-z.]{2,10}$/', $domain)) return 'invalid';

    $cached = get_transient('lagos_dom_' . md5($domain));
    if ($cached) return $cached;

    $tld = implode('.', array_slice(explode('.', $domain), -2));
    // RDAP direto por registro (mais rápido)
    $endpoints = [
        'com.br' => 'https://rdap.registro.br/domain/',
        'net.br' => 'https://rdap.registro.br/domain/',
        'com'    => 'https://rdap.verisign.com/com/v1/domain/',
        'net'    => 'https://rdap.verisign.com/net/v1/domain/',
    ];
    $url = ($endpoints[$tld] ?? 'https://rdap.org/domain/') . rawurlencode($domain);

    $status = 'unknown';
    for ($try = 1; $try <= 2; $try++) {
        $code = lagos_rdap_code($url);
        if ($code === 404) { $status = 'available'; break; }
        if ($code === 200) { $status = 'taken'; break; }
    }
    // cacheia apenas respostas conclusivas (10 min) para não martelar o RDAP
    if ($status !== 'unknown') set_transient('lagos_dom_' . md5($domain), $status, 600);
    return $status;
}

/**
 * Código HTTP de uma consulta RDAP via curl nativo.
 * Alguns servidores RDAP (ex.: Verisign) encerram a conexão sem marcador de fim,
 * o que faz o curl reportar erro 56 mesmo APÓS entregar a resposta completa —
 * aqui o código HTTP recebido prevalece sobre esse erro pós-resposta.
 */
function lagos_rdap_code($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_USERAGENT      => 'LagosPanel/0.5 (+https://lagossolucoes.com.br)',
            CURLOPT_HTTPHEADER     => ['Accept: application/rdap+json, application/json'],
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($code > 0) return $code;
        return false;
    }
    // fallback sem ext/curl
    $resp = wp_remote_get($url, ['timeout' => 10, 'redirection' => 3]);
    if (is_wp_error($resp)) return false;
    return (int) wp_remote_retrieve_response_code($resp);
}

add_shortcode('lagos_domains', function () {
    $query = isset($_GET['domain']) ? sanitize_text_field(wp_unslash($_GET['domain'])) : '';
    $base  = preg_replace('/\.[a-z.]+$/', '', strtolower($query));
    $tlds  = ['com', 'com.br', 'net', 'app'];
    $results = [];
    $searched = false;

    if ($query !== '' && $base !== '' && preg_match('/^[a-z0-9à-ú\-.]{2,60}$/', $base)) {
        $searched = true;
        foreach ($tlds as $tld) {
            $results[$tld] = lagos_domain_check($base . '.' . $tld);
        }
    }
    ob_start();
    ?>
    <div class="page-hero" style="padding:44px 0">
      <div class="wrap">
        <h1 style="font-size:1.7rem"><?php lagos_e('dom_title'); ?></h1>
        <p style="color:#C4B0F5;margin:6px 0 22px"><?php lagos_e('dom_sub'); ?></p>
        <form method="get" class="dom-check">
          <div class="flex-between" style="background:#fff;padding:8px;border-radius:14px;max-width:560px">
            <input type="text" name="domain" value="<?php echo esc_attr($query); ?>" placeholder="<?php echo esc_attr(lagos_t('dom_ph')); ?>" style="flex:1;border:none;outline:none;padding:8px 12px;font-size:1rem;min-width:0">
            <button type="submit" class="btn btn-primary"><?php echo lagos_icon('send', 15); ?> <?php lagos_e('dom_btn'); ?></button>
          </div>
        </form>
      </div>
    </div>

    <div class="wrap page-content" style="max-width:760px">
      <?php if ($searched) : ?>
        <div class="card">
          <h3 class="card-title"><?php echo lagos_icon('globe', 18); ?> <?php echo esc_html($base); ?></h3>
          <?php foreach ($results as $tld => $status) :
              $name = $base . '.' . $tld;
              if ($status === 'available') {
                  $badge = '<span class="badge badge-active"><span class="badge-dot"></span>' . esc_html(lagos_t('dom_available')) . '</span>';
                  $action = '<a class="btn btn-primary btn-sm" href="' . esc_url(home_url('/loja/dominio-com/')) . '">' . esc_html(lagos_t('dom_register')) . '</a>';
              } elseif ($status === 'taken') {
                  $badge = '<span class="badge badge-cancelled"><span class="badge-dot"></span>' . esc_html(lagos_t('dom_taken')) . '</span>';
                  $action = '<a class="btn btn-ghost btn-sm" href="' . esc_url(home_url('/loja/')) . '">' . esc_html(lagos_t('dom_transfer')) . '</a>';
              } else {
                  $badge = '<span class="badge badge-pending"><span class="badge-dot"></span>' . esc_html(lagos_t('dom_unknown')) . '</span>';
                  $action = '';
              }
          ?>
          <div class="dom-result" style="padding:13px 0;border-bottom:1px dashed var(--line)">
            <span class="dom-name"><?php echo esc_html($name); ?></span>
            <span style="display:flex;gap:10px;align-items:center;flex-wrap:wrap"><?php echo $badge . $action; ?></span>
          </div>
          <?php endforeach; ?>
          <p class="text-muted" style="font-size:.8rem;margin:14px 0 0"><?php lagos_e('dom_disclaimer'); ?></p>
        </div>
      <?php else : ?>
        <div class="card center">
          <p class="text-muted" style="margin-top:0"><?php lagos_e('dom_hint'); ?></p>
        </div>
      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
});

/* =========================================================
   DOWNLOADS
   ========================================================= */
add_shortcode('lagos_downloads', function () {
    $items = get_posts(['post_type' => 'lagos_download', 'numberposts' => -1, 'post_status' => 'publish', 'orderby' => 'menu_order title', 'order' => 'ASC']);
    ob_start();
    ?>
    <div class="store-head">
      <h2><?php lagos_e('dl_title'); ?></h2>
      <p><?php lagos_e('dl_sub'); ?></p>
    </div>
    <div class="store-grid">
      <?php if ($items) : foreach ($items as $d) :
          $file = get_post_meta($d->ID, '_lagos_file', true);
      ?>
      <div class="product-card">
        <div class="p-chip-row"><span class="chip chip-cat" style="background:#F1EAFE;color:#7C3AED;border:none"><?php echo esc_html(strtoupper(pathinfo(wp_parse_url($file, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'file')); ?></span></div>
        <h3><?php echo esc_html($d->post_title); ?></h3>
        <p class="p-tag"><?php echo esc_html(get_the_excerpt($d)); ?></p>
        <a class="btn btn-primary btn-block" href="<?php echo esc_url($file); ?>" download><?php echo lagos_icon('book', 15); ?> <?php lagos_e('dl_download'); ?></a>
      </div>
      <?php endforeach; else : ?>
        <p class="text-muted"><?php lagos_e('no_data'); ?></p>
      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
});

/* =========================================================
   STATUS DA REDE
   ========================================================= */
add_shortcode('lagos_status', function () {
    $modules = function_exists('lagos_modules') ? lagos_modules() : [];
    $active  = get_posts(['post_type' => 'lagos_incident', 'numberposts' => 3, 'post_status' => 'publish',
        'meta_query' => [['key' => '_lagos_inc_status', 'value' => ['investigating', 'monitoring'], 'compare' => 'IN']]]);
    $history = get_posts(['post_type' => 'lagos_incident', 'numberposts' => 6, 'post_status' => 'publish',
        'meta_query' => [['key' => '_lagos_inc_status', 'value' => 'resolved']]]);
    $degraded = [];
    foreach ($active as $a) {
        $comp = get_post_meta($a->ID, '_lagos_inc_component', true);
        if ($comp) $degraded[$comp] = true;
    }
    $types = function_exists('lagos_module_types') ? lagos_module_types() : [];
    ob_start();
    ?>
    <div class="store-head">
      <h2><?php lagos_e('net_title'); ?></h2>
      <p><?php lagos_e('net_sub'); ?></p>
    </div>

    <?php if (!$active) : ?>
    <div class="card" style="border-color:#BBF0CC;background:#F4FCF7">
      <div class="flex-between">
        <div style="display:flex;align-items:center;gap:12px">
          <span class="stat-ic ic-green"><?php echo lagos_icon('check', 22); ?></span>
          <div>
            <strong style="font-size:1.05rem"><?php lagos_e('net_all_ok'); ?></strong>
            <div class="text-muted" style="font-size:.85rem"><?php lagos_e('net_all_ok_sub'); ?></div>
          </div>
        </div>
        <span class="badge badge-active"><span class="badge-dot"></span><?php lagos_e('net_operational'); ?></span>
      </div>
    </div>
    <?php else : ?>
      <?php foreach ($active as $a) : lagos_incident_card($a, true); endforeach; ?>
    <?php endif; ?>

    <h3 style="font-size:1.02rem;margin:28px 0 14px"><?php lagos_e('net_components'); ?></h3>
    <div class="ltable-wrap">
      <table class="ltable">
        <thead><tr><th><?php lagos_e('net_component'); ?></th><th><?php lagos_e('col_status'); ?></th></tr></thead>
        <tbody>
        <?php if ($modules) : foreach ($modules as $id => $m) : $down = isset($degraded[$id]); ?>
          <tr>
            <td class="t-strong" data-label="<?php lagos_e('net_component'); ?>"><?php echo lagos_icon($types[$m['type']]['icon'] ?? 'server', 16); ?> <?php echo esc_html($m['name']); ?></td>
            <td data-label="<?php lagos_e('col_status'); ?>">
              <?php echo $down
                ? '<span class="badge badge-suspended"><span class="badge-dot"></span>' . esc_html(lagos_t('net_degraded')) . '</span>'
                : '<span class="badge badge-active"><span class="badge-dot"></span>' . esc_html(lagos_t('net_operational')) . '</span>'; ?>
            </td>
          </tr>
        <?php endforeach; else : ?>
          <tr><td colspan="2" class="t-muted"><?php lagos_e('no_data'); ?></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($history) : ?>
    <h3 style="font-size:1.02rem;margin:28px 0 14px"><?php lagos_e('net_history'); ?></h3>
    <?php foreach ($history as $h) lagos_incident_card($h, false); ?>
    <?php endif; ?>
    <?php
    return ob_get_clean();
});

function lagos_incident_card($incident, $active) {
    $st  = get_post_meta($incident->ID, '_lagos_inc_status', true);
    $map = [
        'investigating' => ['badge-suspended', 'net_investigating'],
        'monitoring'    => ['badge-pending', 'net_monitoring'],
        'resolved'      => ['badge-active', 'net_resolved'],
    ];
    [$cls, $key] = $map[$st] ?? ['badge-cancelled', 'status_cancelled'];
    ?>
    <div class="card" style="margin-bottom:12px">
      <div class="flex-between">
        <div>
          <strong><?php echo esc_html($incident->post_title); ?></strong>
          <div class="post-body" style="font-size:.9rem;color:var(--muted);margin-top:4px"><?php echo esc_html(wp_trim_words($incident->post_content, 30, '…')); ?></div>
          <div class="text-muted" style="font-size:.78rem;margin-top:6px"><?php echo esc_html(get_the_date('d/m/Y H:i', $incident)); ?></div>
        </div>
        <span class="badge <?php echo esc_attr($cls); ?>"><span class="badge-dot"></span><?php lagos_e($key); ?></span>
      </div>
    </div>
    <?php
}

/* =========================================================
   AVALIAÇÃO DE TICKETS (1 a 5 estrelas)
   ========================================================= */
add_action('template_redirect', function () {
    if (empty($_GET['rate_ticket'])) return;
    if (!is_user_logged_in()) return;
    $tid  = absint($_GET['rate_ticket']);
    $stars = isset($_GET['stars']) ? absint($_GET['stars']) : 0;
    if ($stars < 1 || $stars > 5) return;
    check_admin_referer('lagos_rate_' . $tid, 'lagos_nonce');

    $t = get_post($tid);
    if (!$t || $t->post_type !== 'lagos_ticket' || (int) get_post_meta($tid, '_lagos_user', true) !== get_current_user_id()) return;
    if (get_post_meta($tid, '_lagos_status', true) !== 'closed') return;
    if (get_post_meta($tid, '_lagos_rating', true)) return;

    update_post_meta($tid, '_lagos_rating', $stars);
    wp_safe_redirect(add_query_arg('lagos_msg', 'rated_ok', lagos_panel_url('suporte')));
    exit;
});

/** Estrelas no ticket fechado + média exibida */
function lagos_ticket_rating_html($ticket_id) {
    $rating = (int) get_post_meta($ticket_id, '_lagos_rating', true);
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        $stars .= '<span style="color:' . ($i <= $rating ? '#F59E0B' : '#D7DEEB') . '">&#9733;</span>';
    }
    if ($rating) return '<span title="' . esc_attr($rating . '/5') . '" style="letter-spacing:2px">' . $stars . '</span>';

    // form de avaliação (ticket fechado sem nota)
    $url = wp_nonce_url(add_query_arg(['rate_ticket' => $ticket_id, 'stars' => '__STAR__'], lagos_panel_url('suporte')), 'lagos_rate_' . $ticket_id, 'lagos_nonce');
    $out = '<span class="text-muted" style="font-size:.82rem">' . esc_html(lagos_t('rate_ask')) . '</span> ';
    for ($i = 5; $i >= 1; $i--) {
        $out .= '<a href="' . esc_url(str_replace('__STAR__', $i, $url)) . '" style="color:#F59E0B;font-size:1.05rem;text-decoration:none" title="' . esc_attr($i) . '">&#9733;</a>';
    }
    return $out;
}

/** Média de satisfação para o admin */
function lagos_ticket_rating_avg() {
    global $wpdb;
    $ids = $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_lagos_rating' AND meta_value > 0");
    if (!$ids) return null;
    $total = 0; $n = 0;
    foreach ($ids as $id) { $total += (int) get_post_meta($id, '_lagos_rating', true); $n++; }
    return $n ? round($total / $n, 1) : null;
}
