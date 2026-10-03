<?php
/**
 * LagosPanel Core — Programa de Afiliados (v0.5)
 * Link de indicação → cookie 30 dias → comissão no pagamento da fatura.
 */
if (!defined('ABSPATH')) exit;

/** Código de afiliado do usuário */
function lagos_aff_code($uid) {
    $c = get_user_meta($uid, 'lagos_aff_code', true);
    if (!$c) {
        $c = 'LAG-' . strtoupper(wp_generate_password(6, false, false));
        update_user_meta($uid, 'lagos_aff_code', $c);
    }
    return $c;
}

function lagos_aff_link($uid) {
    return add_query_arg('ref', lagos_aff_code($uid), home_url('/registrar/'));
}

/** Taxa de comissão (padrão 10%) */
function lagos_aff_rate() {
    return max(0, min(50, (float) get_option('lagos_aff_rate', 10)));
}

/** Captura ?ref= no acesso → cookie 30 dias */
add_action('init', function () {
    if (empty($_GET['ref'])) return;
    $code = sanitize_text_field(wp_unslash($_GET['ref']));
    if (strlen($code) > 24 || !preg_match('/^[A-Za-z0-9\-]+$/', $code)) return;
    if (!headers_sent()) setcookie('lagos_ref', $code, time() + 30 * DAY_IN_SECONDS, '/');
});

/** No cadastro: vincula o indicador */
add_action('user_register', function ($uid) {
    if (empty($_COOKIE['lagos_ref'])) return;
    $code = sanitize_text_field($_COOKIE['lagos_ref']);
    $aff = get_users(['meta_key' => 'lagos_aff_code', 'meta_value' => $code, 'number' => 1, 'fields' => ['ID']]);
    if ($aff && (int) $aff[0]->ID !== (int) $uid) {
        update_user_meta($uid, 'lagos_ref_by', (int) $aff[0]->ID);
        if (!headers_sent()) setcookie('lagos_ref', '', time() - 3600, '/');
    }
});

/** Fatura paga → credita comissão ao indicador */
add_action('lagos_invoice_paid', function ($invoice_id) {
    if (get_post_meta($invoice_id, '_lagos_deposit', true) === '1') return; // recarga não gera comissão
    $uid = (int) get_post_meta($invoice_id, '_lagos_user', true);
    $ref_by = (int) get_user_meta($uid, 'lagos_ref_by', true);
    if (!$ref_by) return;

    $amount  = (float) get_post_meta($invoice_id, '_lagos_amount', true);
    $commission = round($amount * (lagos_aff_rate() / 100), 2);
    if ($commission <= 0) return;

    // credita na carteira
    update_user_meta($ref_by, 'lagos_balance', lagos_balance($ref_by) + $commission);

    // histórico
    $hist = get_user_meta($ref_by, 'lagos_aff_hist', true) ?: [];
    array_unshift($hist, [
        'time'       => current_time('mysql'),
        'amount'     => $commission,
        'invoice'    => $invoice_id,
        'ref_email'  => '', // privacidade do indicado
        'ref_name'   => '', 
    ]);
    update_user_meta($ref_by, 'lagos_aff_hist', array_slice($hist, 0, 100));

    if (function_exists('lagos_notify')) {
        $u = get_userdata($ref_by);
        if ($u) lagos_notify($u->user_email, 'Comissão de afiliado', 'Você ganhou uma comissão!',
            '<p>Olá, <strong>' . $u->display_name . '</strong>!</p>
             <p>Uma indicação sua gerou uma comissão de <strong>' . lagos_money($commission) . '</strong>, creditada na sua carteira.</p>',
            'Ver meus ganhos', lagos_panel_url('afiliados'));
    }
}, 20);

/** Página do afiliado na área do cliente */
add_shortcode('lagos_affiliates', function () {
    if (!is_user_logged_in()) return lagos_login_prompt();
    $uid   = get_current_user_id();
    $code  = lagos_aff_code($uid);
    $link  = lagos_aff_link($uid);
    $hist  = get_user_meta($uid, 'lagos_aff_hist', true) ?: [];
    $total = 0;
    foreach ($hist as $h) $total += (float) $h['amount'];
    $refs = count(get_users(['meta_key' => 'lagos_ref_by', 'meta_value' => $uid, 'fields' => ['ID']]));
    ob_start();
    ?>
    <div class="store-head">
      <h2><?php lagos_e('af_title'); ?></h2>
      <p><?php lagos_e('af_sub'); ?></p>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-ic ic-violet"><?php echo lagos_icon('user', 21); ?></div>
        <div><strong><?php echo (int) $refs; ?></strong><span><?php lagos_e('af_refs'); ?></span></div>
      </div>
      <div class="stat-card">
        <div class="stat-ic ic-green"><?php echo lagos_icon('wallet', 21); ?></div>
        <div><strong><?php echo esc_html(lagos_money($total)); ?></strong><span><?php lagos_e('af_earnings'); ?></span></div>
      </div>
      <div class="stat-card">
        <div class="stat-ic ic-orange"><?php echo lagos_icon('zap', 21); ?></div>
        <div><strong><?php echo esc_html(lagos_aff_rate() . '%'); ?></strong><span><?php lagos_e('af_rate'); ?></span></div>
      </div>
      <div class="stat-card">
        <div class="stat-ic ic-blue"><?php echo lagos_icon('clock', 21); ?></div>
        <div><strong>30d</strong><span><?php lagos_e('af_cookie'); ?></span></div>
      </div>
    </div>

    <div class="card">
      <h3 class="card-title"><?php echo lagos_icon('api', 18); ?> <?php lagos_e('af_link'); ?></h3>
      <p class="text-muted" style="margin-top:0;font-size:.9rem"><?php lagos_e('af_link_hint'); ?></p>
      <div class="api-key-box mb-20"><code><?php echo esc_html($link); ?></code></div>
      <button class="btn btn-ghost btn-sm" data-copy="<?php echo esc_attr($link); ?>" data-copied="<?php echo esc_attr(lagos_t('pix_copied')); ?>"><?php lagos_e('btn_copy'); ?></button>
    </div>

    <div class="ltable-wrap">
      <table class="ltable">
        <thead><tr>
          <th><?php lagos_e('col_date'); ?></th><th><?php lagos_e('af_commission'); ?></th><th><?php lagos_e('col_status'); ?></th>
        </tr></thead>
        <tbody>
        <?php if ($hist) : foreach (array_slice($hist, 0, 20) as $h) : ?>
          <tr>
            <td class="t-muted" data-label="<?php lagos_e('col_date'); ?>"><?php echo esc_html(mysql2date('d/m/Y H:i', $h['time'])); ?></td>
            <td class="t-money" data-label="<?php lagos_e('af_commission'); ?>"><?php echo esc_html(lagos_money($h['amount'])); ?></td>
            <td data-label="<?php lagos_e('col_status'); ?>"><span class="badge badge-paid"><span class="badge-dot"></span><?php lagos_e('af_credited'); ?></span></td>
          </tr>
        <?php endforeach; else : ?>
          <tr><td colspan="3" class="t-muted"><?php lagos_e('af_empty'); ?></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
    return ob_get_clean();
});

/** Campo de taxa no admin (Configurações) */
add_action('lagos_settings_extra', function () {
    ?>
    <h2 style="margin-top:32px">Programa de afiliados</h2>
    <table class="form-table" role="presentation">
        <tr>
            <th>Comissão (%)</th>
            <td>
                <input type="number" name="aff_rate" min="0" max="50" step="0.5" value="<?php echo esc_attr(get_option('lagos_aff_rate', 10)); ?>" style="width:90px">
                <span class="text-muted">percentual creditado na carteira do afiliado a cada fatura paga de indicação</span>
            </td>
        </tr>
    </table>
    <?php
});
add_action('lagos_settings_saved', function () {
    update_option('lagos_aff_rate', (float) ($_POST['aff_rate'] ?? 10));
});
