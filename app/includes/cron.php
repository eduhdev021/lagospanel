<?php
/**
 * LagosPanel Core — Automação diária (estilo WHMCS) — v0.6
 *
 * Roda via WP-Cron (evento diário 'lagos_daily_event'):
 *   1. Gera faturas de RENOVAÇÃO para serviços ativos (X dias antes do vencimento)
 *   2. Marca faturas VENCIDAS (overdue)
 *   3. SUSPENDE serviços com fatura vencida há mais de Y dias (+ ação no módulo)
 *
 * Configuração em Configurações: lagos_renew_days (padrão 5), lagos_suspend_days (padrão 3).
 * Em produção, agende no crontab do servidor:
 *   wp-config.php: define('DISABLE_WP_CRON', true);
 *   crontab: 0 3 * * * php /caminho/wp-cron.php   (ou curl -s https://site/wp-cron.php)
 */
if (!defined('ABSPATH')) exit;

/* Agenda o evento diário (idempotente) */
add_action('init', function () {
    if (!wp_next_scheduled('lagos_daily_event')) {
        wp_schedule_event(strtotime('tomorrow 03:00'), 'daily', 'lagos_daily_event');
    }
});

function lagos_renew_days()    { return max(1, (int) get_option('lagos_renew_days', 5)); }
function lagos_suspend_days()  { return max(1, (int) get_option('lagos_suspend_days', 3)); }

/** Rotina completa — retorna resumo do que fez */
function lagos_daily_maintenance() {
    $report = ['renewals' => 0, 'overdue' => 0, 'suspended' => 0, 'details' => []];
    $today  = current_time('Y-m-d');
    $limit  = date('Y-m-d', strtotime('+' . lagos_renew_days() . ' days'));

    // ── 1. Faturas de renovação ──
    foreach (get_posts(['post_type' => 'lagos_service', 'numberposts' => -1, 'post_status' => 'any']) as $svc) {
        if (get_post_meta($svc->ID, '_lagos_status', true) !== 'active') continue;
        $due = get_post_meta($svc->ID, '_lagos_next_due', true);
        if (!$due || $due > $limit || $due < $today) continue; // só vence nos próximos N dias

        // já existe fatura aberta vinculada a este serviço? não duplica
        $existing = get_posts([
            'post_type'      => 'lagos_invoice', 'numberposts' => 1, 'post_status' => 'any',
            'meta_key' => '_lagos_service', 'meta_value' => $svc->ID,
            'fields'         => 'ids',
        ]);
        foreach ($existing as $eid) {
            if (in_array(get_post_meta($eid, '_lagos_status', true), ['unpaid', 'overdue'], true)) {
                continue 2;
            }
        }

        $uid    = (int) get_post_meta($svc->ID, '_lagos_user', true);
        $amount = (float) get_post_meta($svc->ID, '_lagos_price', true);
        if ($uid && $amount > 0) {
            $iid = wp_insert_post([
                'post_type'   => 'lagos_invoice',
                'post_status' => 'publish',
                'post_title'  => 'Renovação — ' . $svc->post_title,
            ]);
            update_post_meta($iid, '_lagos_user', $uid);
            update_post_meta($iid, '_lagos_amount', $amount);
            update_post_meta($iid, '_lagos_status', 'unpaid');
            update_post_meta($iid, '_lagos_due', $due);
            update_post_meta($iid, '_lagos_service', $svc->ID);
            update_post_meta($iid, '_lagos_items', $svc->post_title . ' | 1 | ' . number_format($amount, 2, ',', '.'));
            if (function_exists('lagos_mail_invoice')) lagos_mail_invoice($iid);
            $report['renewals']++;
            $report['details'][] = "renovação: fatura #{$iid} para serviço #{$svc->ID} (vence {$due})";
        }
    }

    // ── 2. Overdue ──
    foreach (get_posts(['post_type' => 'lagos_invoice', 'numberposts' => -1, 'post_status' => 'any']) as $inv) {
        if (get_post_meta($inv->ID, '_lagos_status', true) !== 'unpaid') continue;
        $due = get_post_meta($inv->ID, '_lagos_due', true);
        if ($due && $due < $today) {
            update_post_meta($inv->ID, '_lagos_status', 'overdue');
            $report['overdue']++;
            $report['details'][] = "overdue: fatura #{$inv->ID} (vencia {$due})";
        }
    }

    // ── 3. Suspensão automática ──
    $suspLimit = date('Y-m-d', strtotime('-' . lagos_suspend_days() . ' days'));
    foreach (get_posts(['post_type' => 'lagos_invoice', 'numberposts' => -1, 'post_status' => 'any']) as $inv) {
        if (!in_array(get_post_meta($inv->ID, '_lagos_status', true), ['unpaid', 'overdue'], true)) continue;
        $due = get_post_meta($inv->ID, '_lagos_due', true);
        if (!$due || $due > $suspLimit) continue;

        $sids = [];
        $single = (int) get_post_meta($inv->ID, '_lagos_service', true);
        if ($single) $sids[] = $single;
        $multi = get_post_meta($inv->ID, '_lagos_services', true);
        if ($multi) $sids = array_merge($sids, array_map('intval', explode(',', $multi)));

        foreach (array_unique(array_filter($sids)) as $sid) {
            if (get_post_meta($sid, '_lagos_status', true) !== 'active') continue;
            update_post_meta($sid, '_lagos_status', 'suspended');

            // executa a ação de suspensão no módulo (Pterodactyl/cPanel/aaPanel/...)
            $product = get_post((int) get_post_meta($sid, '_lagos_product', true));
            $module  = $product ? get_post_meta($product->ID, '_lagos_module', true) : '';
            if ($module && function_exists('lagos_module_run')) {
                lagos_module_run('suspend', $module, $sid);
            }
            $report['suspended']++;
            $report['details'][] = "suspenso: serviço #{$sid} (fatura #{$inv->ID} vencia {$due})";
        }
    }

    update_option('lagos_last_cron', ['at' => current_time('mysql')] + $report);
    lagos_audit('cron', "rotina diária: {$report['renewals']} renovação(ões), {$report['overdue']} vencida(s), {$report['suspended']} suspensão(ões)");
    return $report;
}
add_action('lagos_daily_event', 'lagos_daily_maintenance');

/** Câmbio automático diário (BCE/Frankfurter) — pode ser desligado nas configurações */
add_action('lagos_daily_event', function () {
    if (!get_option('lagos_rates_auto', 1)) return;
    $r = lagos_gw_http('GET', 'https://api.frankfurter.app/latest?base=BRL', ['timeout' => 15]);
    if ($r['code'] === 200 && !empty($r['json']['rates'])) {
        $cur = lagos_currencies();
        foreach ($cur as $code => $rate) {
            if ($code === 'BRL') continue;
            $v = (float) ($r['json']['rates'][$code] ?? 0);
            if ($v > 0) $cur[$code] = round(1 / $v, 4);
        }
        update_option('lagos_currencies', $cur);
    update_option('lagos_rates_auto', isset($_POST['lagos_rates_auto']) ? 1 : 0);
        lagos_audit('cron', 'câmbio atualizado automaticamente (referência BCE)');
    }
}, 20);

/* ── Admin: executar a rotina agora (teste/manual) ── */
add_action('admin_post_lagos_cron_run', function () {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');
    check_admin_referer('lagos_cron_run');
    lagos_daily_maintenance();
    wp_safe_redirect(admin_url('admin.php?page=lagospanel&cron=1'));
    exit;
});

/* ══════════════════════════════════════════════════════════
   Configurações (Configurações → Automação & Modo)
   ══════════════════════════════════════════════════════════ */
add_action('lagos_settings_extra', function () {
    $mode = function_exists('lagos_panel_mode') ? lagos_panel_mode() : 'demo';
    ?>
    <h2 style="margin-top:32px">Modo do painel & Automação</h2>
    <table class="form-table" role="presentation">
        <tr>
            <th>Modo</th>
            <td>
                <label><input type="radio" name="lagos_mode" value="demo" <?php checked($mode, 'demo'); ?>> Demonstração (dados de exemplo, Pix simulado)</label><br>
                <label><input type="radio" name="lagos_mode" value="production" <?php checked($mode, 'production'); ?>> <strong>Produção</strong> (esconde gateways de demonstração; exige credenciais reais)</label>
                <p class="description">Em produção, o gateway "Pix (demonstração)" é removido do checkout automaticamente.</p>
            </td>
        </tr>
        <tr>
            <th>Confirmação de e-mail</th>
            <td><label><input type="checkbox" name="lagos_email_confirm" <?php checked(get_option('lagos_email_confirm', 1), 1); ?>> Exigir confirmação do e-mail no cadastro (conta fica pendente até clicar no link)</label></td>
        </tr>
        <tr>
            <th>Alerta de novo acesso</th>
            <td><label><input type="checkbox" name="lagos_alert_newip" <?php checked(get_option('lagos_alert_newip', 1), 1); ?>> Enviar e-mail quando a conta for acessada de um IP diferente do habitual</label></td>
        </tr>
        <tr>
            <th>Gerar fatura de renovação</th>
            <td><input type="number" name="lagos_renew_days" min="1" max="30" value="<?php echo esc_attr(max(1, (int) get_option('lagos_renew_days', 5))); ?>" style="width:70px"> dias antes do vencimento do serviço</td>
        </tr>
        <tr>
            <th>Suspender por inadimplência</th>
            <td><input type="number" name="lagos_suspend_days" min="1" max="60" value="<?php echo esc_attr(max(1, (int) get_option('lagos_suspend_days', 3))); ?>" style="width:70px"> dias após a fatura vencer (suspende também no painel remoto)</td>
        </tr>
    </table>
    <h2 style="margin-top:32px">Moedas & Câmbio</h2>
    <p class="description">Valores são armazenados na moeda base (BRL) e exibidos/cobrados na moeda escolhida pelo cliente. Taxa = quantos BRL vale 1 unidade da moeda.</p>
    <table class="form-table" role="presentation">
        <tr>
            <th>Moeda base</th>
            <td><strong>BRL</strong> (Real) — taxa fixa 1,00</td>
        </tr>
        <?php foreach (lagos_currencies() as $code => $rate) : if ($code === 'BRL') continue; ?>
        <tr>
            <th><label><?php echo esc_html($code); ?></label></th>
            <td>1 <?php echo esc_html($code); ?> = <input type="number" name="cur_rate[<?php echo esc_attr($code); ?>]" value="<?php echo esc_attr($rate); ?>" min="0.0001" step="0.0001" style="width:110px"> BRL</td>
        </tr>
        <?php endforeach; ?>
        <tr>
            <th>Nova moeda</th>
            <td>
                <input type="text" name="cur_new_code" placeholder="Ex.: GBP" maxlength="3" style="width:80px;text-transform:uppercase"> =
                <input type="number" name="cur_new_rate" placeholder="0,00" min="0.0001" step="0.0001" style="width:110px"> BRL
                <p class="description">Código ISO de 3 letras (USD, EUR, GBP...).</p>
            </td>
        </tr>
        <tr>
            <th>Câmbio automático</th>
            <td>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=lagos_rates_refresh'), 'lagos_rates_refresh')); ?>" class="button">Atualizar taxas agora</a>
                <label style="margin-left:14px"><input type="checkbox" name="lagos_rates_auto" <?php checked(get_option('lagos_rates_auto', 1), 1); ?>> Atualizar automaticamente todos os dias (junto com a rotina diária)</label>
                <p class="description">Busca as cotações de referência do Banco Central Europeu (Frankfurter) para as moedas já configuradas.</p>
            </td>
        </tr>
    </table>
    <?php
});

/** Atualiza câmbio pela API Frankfurter (Banco Central Europeu) */
add_action('admin_post_lagos_rates_refresh', function () {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');
    check_admin_referer('lagos_rates_refresh');
    $ok = false;
    $r = lagos_gw_http('GET', 'https://api.frankfurter.app/latest?base=BRL', ['timeout' => 15]);
    if ($r['code'] === 200 && !empty($r['json']['rates'])) {
        $cur = lagos_currencies();
        foreach ($cur as $code => $rate) {
            if ($code === 'BRL') continue;
            $v = (float) ($r['json']['rates'][$code] ?? 0);
            if ($v > 0) { $cur[$code] = round(1 / $v, 4); $ok = true; }
        }
        update_option('lagos_currencies', $cur);
    update_option('lagos_rates_auto', isset($_POST['lagos_rates_auto']) ? 1 : 0);
        lagos_audit('cron', 'câmbio atualizado via Frankfurter/ECB');
    }
    wp_safe_redirect(admin_url('admin.php?page=lagos-settings&rates=' . ($ok ? 'ok' : 'fail')));
    exit;
});

add_action('lagos_settings_saved', function () {
    $mode = ($_POST['lagos_mode'] ?? 'demo') === 'production' ? 'production' : 'demo';
    update_option('lagos_mode', $mode);
    update_option('lagos_email_confirm', isset($_POST['lagos_email_confirm']) ? 1 : 0);
    update_option('lagos_alert_newip', isset($_POST['lagos_alert_newip']) ? 1 : 0);

    // moedas
    $cur = ['BRL' => 1.0];
    foreach ((array) ($_POST['cur_rate'] ?? []) as $code => $rate) {
        $code = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $code), 0, 3));
        $rate = (float) $rate;
        if (preg_match('/^[A-Z]{3}$/', $code) && $rate > 0 && $code !== 'BRL') $cur[$code] = round($rate, 4);
    }
    $new_code = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $_POST['cur_new_code'] ?? ''), 0, 3));
    $new_rate = (float) ($_POST['cur_new_rate'] ?? 0);
    if (preg_match('/^[A-Z]{3}$/', $new_code) && $new_rate > 0 && $new_code !== 'BRL' && !isset($cur[$new_code])) {
        $cur[$new_code] = round($new_rate, 4);
    }
    update_option('lagos_currencies', $cur);
    update_option('lagos_rates_auto', isset($_POST['lagos_rates_auto']) ? 1 : 0);
    update_option('lagos_renew_days', max(1, min(30, (int) ($_POST['lagos_renew_days'] ?? 5))));
    update_option('lagos_suspend_days', max(1, min(60, (int) ($_POST['lagos_suspend_days'] ?? 3))));

    // indo para produção → desliga o gateway de demonstração
    if ($mode === 'production' && function_exists('lagos_gateways_config')) {
        $cfg = lagos_gateways_config();
        if (!empty($cfg['pix']['enabled'])) {
            $cfg['pix']['enabled'] = 0;
            update_option('lagos_gateways_config', $cfg);
        }
    }
});
