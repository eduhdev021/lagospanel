<?php
/**
 * LagosPanel Core — Logs de Auditoria — v0.7
 *
 * Registra eventos sensíveis do painel: logins (e falhas), alterações de senha,
 * 2FA, pagamentos, gateways, provisionamentos, cron, confirmações de e-mail...
 *
 * lagos_audit('evento', 'detalhe', $user_id) — guarda no máximo 500 entradas:
 * data/hora · usuário · evento · detalhe · IP · navegador
 */
if (!defined('ABSPATH')) exit;

function lagos_audit($event, $detail = '', $user_id = null) {
    $uid = $user_id !== null ? (int) $user_id : get_current_user_id();
    $u   = $uid ? get_userdata($uid) : null;
    $log = get_option('lagos_audit_log', []);
    if (!is_array($log)) $log = [];
    array_unshift($log, [
        'time'   => current_time('mysql'),
        'uid'    => $uid,
        'user'   => $u ? $u->user_login : ($uid ? "#{$uid}" : 'sistema'),
        'event'  => $event,
        'detail' => is_string($detail) ? mb_substr($detail, 0, 300) : '',
        'ip'     => $_SERVER['REMOTE_ADDR'] ?? '',
        'ua'     => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 160),
    ]);
    update_option('lagos_audit_log', array_slice($log, 0, 500));
}

/* ── ADMIN: visualizador ── */
add_action('admin_menu', function () {
    add_submenu_page('lagospanel', 'Logs de Auditoria', 'Auditoria', 'manage_options', 'lagos-audit', 'lagos_admin_audit_page');
}, 11);

function lagos_admin_audit_page() {
    $log = get_option('lagos_audit_log', []);
    if (isset($_GET['cleared'])) : ?>
        <div class="notice notice-success is-dismissible"><p>Logs de auditoria limpos.</p></div>
    <?php endif; ?>
    <div class="wrap">
      <h1 class="wp-heading-inline">Logs de Auditoria</h1>
      <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=lagos_audit_clear'), 'lagos_audit_clear')); ?>" class="page-title-action" onclick="return confirm('Limpar todos os registros de auditoria?')">Limpar</a>
      <p class="description">Últimos 500 eventos: acessos, pagamentos, mudanças de segurança e ações administrativas.
        <a href="<?php echo esc_url(function_exists('lagos_export_url') ? lagos_export_url('audit') : '#'); ?>" class="button button-small" style="margin-left:8px">Exportar CSV</a></p>

      <table class="wp-list-table widefat fixed striped" cellspacing="0" style="margin-top:12px">
        <thead><tr>
          <th style="width:15%">Data/hora</th><th style="width:14%">Usuário</th>
          <th style="width:16%">Evento</th><th>Detalhe</th>
          <th style="width:13%">IP</th>
        </tr></thead>
        <tbody>
        <?php if (!$log) : ?>
          <tr><td colspan="5" style="color:#8a8f98">Nenhum evento registrado ainda.</td></tr>
        <?php else : foreach ($log as $e) :
            $colors = [
                'login_ok' => '#0a7c33', 'login_fail' => '#b32d2e', 'logout' => '#5D6E8C',
                'password_change' => '#b32d2e', 'password_reset' => '#b32d2e', 'forgot_request' => '#B45309',
                '2fa_on' => '#0a7c33', '2fa_off' => '#b32d2e', 'session_revoke' => '#B45309',
                'payment' => '#0a7c33', 'manual_confirm' => '#0a7c33', 'webhook' => '#7C3AED',
                'register' => '#7C3AED', 'email_confirm' => '#0a7c33', 'api_key' => '#B45309',
                'gateway_config' => '#B45309', 'module_run' => '#7C3AED', 'cron' => '#5D6E8C',
            ];
            $c = $colors[$e['event']] ?? '#12203A';
        ?>
          <tr>
            <td><?php echo esc_html($e['time']); ?></td>
            <td><?php echo esc_html($e['user']); ?></td>
            <td><strong style="color:<?php echo $c; ?>"><?php echo esc_html($e['event']); ?></strong></td>
            <td><?php echo esc_html($e['detail'] ?: '—'); ?></td>
            <td><?php echo esc_html($e['ip'] ?: '—'); ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php
}

add_action('admin_post_lagos_audit_clear', function () {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');
    check_admin_referer('lagos_audit_clear');
    update_option('lagos_audit_log', []);
    lagos_audit('cron', 'Logs de auditoria limpos por um administrador.');
    wp_safe_redirect(admin_url('admin.php?page=lagos-audit&cleared=1'));
    exit;
});
