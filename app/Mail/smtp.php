<?php
/**
 * LagosPanel Core — SMTP + E-mails transacionais (v0.4)
 * Configuração em LagosPanel → Configurações. Log das últimas 50 mensagens.
 */
if (!defined('ABSPATH')) exit;

/* =========================================================
   CONFIGURAR PHPMAILER COM SMTP
   ========================================================= */
add_action('phpmailer_init', function ($phpmailer) {
    $host = get_option('lagos_smtp_host', '');
    if (!$host) return; // sem SMTP configurado → mail() padrão do PHP

    $phpmailer->isSMTP();
    $phpmailer->Host       = $host;
    $phpmailer->Port       = (int) get_option('lagos_smtp_port', 587);
    $phpmailer->SMTPAuth   = (bool) get_option('lagos_smtp_user', '');
    $phpmailer->Username   = get_option('lagos_smtp_user', '');
    $phpmailer->Password   = get_option('lagos_smtp_pass', '');
    $sec = get_option('lagos_smtp_sec', 'tls');
    if ($sec === 'tls')  { $phpmailer->SMTPSecure = 'tls'; $phpmailer->SMTPAutoTLS = true; }
    if ($sec === 'ssl')  { $phpmailer->SMTPSecure = 'ssl'; }
    if ($sec === 'none') { $phpmailer->SMTPSecure = false; $phpmailer->SMTPAutoTLS = false; $phpmailer->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]; }
    $phpmailer->From       = get_option('lagos_mail_from', get_option('admin_email'));
    $phpmailer->FromName   = get_option('lagos_mail_from_name', 'LagosPanel');
});

add_action('wp_mail_from', function ($from) {
    $f = get_option('lagos_mail_from', '');
    return $f ?: $from;
});
add_action('wp_mail_from_name', function ($name) {
    $f = get_option('lagos_mail_from_name', '');
    return $f ?: $name;
});

/* =========================================================
   LOG DE E-MAILS
   ========================================================= */
function lagos_mail_log($to, $subject, $ok, $error = '') {
    $log = get_option('lagos_mail_log', []);
    array_unshift($log, ['time' => current_time('mysql'), 'to' => $to, 'subject' => $subject, 'ok' => (bool) $ok, 'error' => $error]);
    update_option('lagos_mail_log', array_slice($log, 0, 50));
}

add_action('wp_mail_succeeded', function ($mail_data) {
    lagos_mail_log(implode(', ', (array) ($mail_data['to'] ?? [])), (string) ($mail_data['subject'] ?? ''), true);
});
add_action('wp_mail_failed', function ($error) {
    $d = $error->get_error_data() ?? [];
    lagos_mail_log(implode(', ', (array) ($d['to'] ?? [])), (string) ($d['subject'] ?? ''), false, $error->get_error_message());
});

/* =========================================================
   TEMPLATE + ENVIO
   ========================================================= */
function lagos_mail_body($title, $content_html, $button = null, $button_url = '') {
    $home = home_url('/');
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<body style="margin:0;padding:24px;background:#F4F7FC;font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#12203A">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #E3E9F4">
    <tr>
      <td style="background:linear-gradient(135deg,#7C3AED,#C040E0);padding:28px 32px;text-align:center">
        <span style="font-size:1.35rem;font-weight:800;color:#fff;letter-spacing:-.02em">Lagos<span style="font-weight:500">Panel</span></span>
      </td>
    </tr>
    <tr>
      <td style="padding:32px">
        <h1 style="margin:0 0 14px;font-size:1.25rem;line-height:1.3;color:#12203A"><?php echo esc_html($title); ?></h1>
        <div style="font-size:.95rem;line-height:1.65;color:#2A3B58"><?php echo $content_html; ?></div>
        <?php if ($button) : ?>
        <p style="margin:26px 0 6px">
          <a href="<?php echo esc_url($button_url); ?>" style="display:inline-block;background:linear-gradient(135deg,#7C3AED,#C040E0);color:#fff;font-weight:700;text-decoration:none;padding:12px 26px;border-radius:10px"><?php echo esc_html($button); ?></a>
        </p>
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <td style="padding:18px 32px;background:#FAF8FE;border-top:1px solid #E3E9F4;font-size:.78rem;color:#5D6E8C;text-align:center">
        LagosPanel — um produto da Lagos Soluções · <a href="<?php echo esc_url($home); ?>" style="color:#7C3AED"><?php echo esc_html(parse_url($home, PHP_URL_HOST)); ?></a>
      </td>
    </tr>
  </table>
</body>
</html>
    <?php
    return ob_get_clean();
}

function lagos_notify($to, $subject, $title, $content_html, $button = null, $button_url = '') {
    if (!$to) return false;
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    return wp_mail($to, '[LagosPanel] ' . $subject, lagos_mail_body($title, $content_html, $button, $button_url), $headers);
}

/* =========================================================
   E-MAILS TRANSACIONAIS
   ========================================================= */

/** Boas-vindas (cadastro) */
function lagos_mail_welcome($user) {
    lagos_notify(
        $user->user_email,
        'Bem-vindo(a) à LagosPanel!',
        'Bem-vindo(a), ' . $user->display_name . '!',
        '<p>Sua conta na <strong>LagosPanel</strong> foi criada com sucesso.</p>
         <p>A partir de agora você pode contratar serviços, gerenciar faturas e abrir tickets de suporte — tudo em um só lugar.</p>',
        'Acessar meu painel',
        lagos_panel_url('')
    );
}

/** Confirmação de e-mail (cadastro) */
function lagos_mail_activation($user, $url) {
    lagos_notify(
        $user->user_email,
        'Confirme seu e-mail',
        'Quase lá, ' . $user->display_name . '!',
        '<p>Para ativar sua conta na <strong>LagosPanel</strong> e começar a usar nossos serviços, confirme seu endereço de e-mail.</p>
         <p style="font-size:.85rem;color:#5D6E8C">Este link expira em 24 horas. Se você não solicitou este cadastro, ignore esta mensagem.</p>',
        'Confirmar meu e-mail',
        $url
    );
}

/** Nova entrada na conta (segurança) */
function lagos_mail_newlogin($user, $ip, $when, $device) {
    lagos_notify(
        $user->user_email,
        'Nova entrada na sua conta',
        'Nova entrada detectada',
        '<p>Olá, <strong>' . $user->display_name . '</strong>.</p>
         <p>Sua conta LagosPanel acaba de ser acessada de um novo endereço IP:</p>
         <p style="background:#F8FAFF;border:1px solid #E3E9F4;border-radius:10px;padding:12px 16px">
           <strong>IP:</strong> ' . esc_html($ip) . '<br>
           <strong>Data:</strong> ' . esc_html($when) . '<br>
           <strong>Dispositivo:</strong> ' . esc_html($device) . '
         </p>
         <p>Se foi você, pode ignorar este aviso. <strong>Se não foi você, altere sua senha imediatamente</strong> e ative a verificação em duas etapas no seu perfil.</p>',
        'Revisar segurança',
        lagos_panel_url('perfil')
    );
}

/** Redefinição de senha */
function lagos_mail_resetpass($user, $url) {
    lagos_notify(
        $user->user_email,
        'Redefinição de senha',
        'Pedido de redefinição de senha',
        '<p>Olá, <strong>' . $user->display_name . '</strong>.</p>
         <p>Recebemos um pedido para redefinir a senha da sua conta LagosPanel. Clique no botão abaixo para escolher uma nova senha:</p>
         <p style="font-size:.85rem;color:#5D6E8C">O link expira em 24 horas. Se não foi você quem pediu, ignore esta mensagem — sua senha continua a mesma.</p>',
        'Definir nova senha',
        $url
    );
}

/** Fatura gerada */
function lagos_mail_invoice($invoice_id) {
    $uid    = (int) get_post_meta($invoice_id, '_lagos_user', true);
    $user   = get_userdata($uid);
    if (!$user) return;
    $amount = lagos_money(get_post_meta($invoice_id, '_lagos_amount', true));
    $ref    = lagos_invoice_ref($invoice_id);
    lagos_notify(
        $user->user_email,
        'Nova fatura #' . $ref,
        'Nova fatura gerada',
        '<p>Olá, <strong>' . $user->display_name . '</strong>!</p>
         <p>Uma nova fatura no valor de <strong>' . $amount . '</strong> foi gerada e está disponível para pagamento via Pix.</p>
         <p>Vencimento: ' . lagos_date(get_post_meta($invoice_id, '_lagos_due', true)) . '</p>',
        'Pagar agora',
        lagos_panel_url('faturas')
    );
}

/** Pagamento confirmado */
function lagos_mail_paid($invoice_id) {
    $uid    = (int) get_post_meta($invoice_id, '_lagos_user', true);
    $user   = get_userdata($uid);
    if (!$user) return;
    $amount = lagos_money(get_post_meta($invoice_id, '_lagos_amount', true));
    $ref    = lagos_invoice_ref($invoice_id);
    lagos_notify(
        $user->user_email,
        'Pagamento confirmado — fatura #' . $ref,
        'Pagamento confirmado',
        '<p>Olá, <strong>' . $user->display_name . '</strong>!</p>
         <p>Recebemos o pagamento de <strong>' . $amount . '</strong> referente à fatura <strong>#' . $ref . '</strong>.</p>
         <p>Seus serviços já estão ativos. Obrigado por confiar na Lagos!</p>',
        'Ver meus serviços',
        lagos_panel_url('servicos')
    );
}

/** Recarga de saldo confirmada */
function lagos_mail_deposit($invoice_id, $amount) {
    $uid  = (int) get_post_meta($invoice_id, '_lagos_user', true);
    $user = get_userdata($uid);
    if (!$user) return;
    lagos_notify(
        $user->user_email,
        'Saldo adicionado',
        'Saldo adicionado com sucesso',
        '<p>Olá, <strong>' . $user->display_name . '</strong>!</p>
         <p>Sua recarga de <strong>' . lagos_money($amount) . '</strong> foi confirmada e já está disponível na sua carteira.</p>',
        'Ver meu perfil',
        lagos_panel_url('perfil')
    );
}

/** Ticket criado (confirmação p/ cliente) */
function lagos_mail_ticket($ticket_id, $user) {
    $t = get_post($ticket_id);
    if (!$t) return;
    lagos_notify(
        $user->user_email,
        '[LAGOS-#' . $t->ID . '] ' . $t->post_title,
        'Recebemos seu ticket',
        '<p>Olá, <strong>' . $user->display_name . '</strong>!</p>
         <p>Registramos seu ticket <strong>"' . $t->post_title . '"</strong> e nossa equipe responderá o mais breve possível.</p>',
        'Acompanhar ticket',
        lagos_panel_url('suporte')
    );
}

/** Equipe respondeu → avisa o cliente */
add_action('comment_post', function ($comment_id) {
    $comment = get_comment($comment_id);
    if (!$comment || $comment->comment_approved != 1) return;
    $post = get_post($comment->comment_post_ID);
    if (!$post || $post->post_type !== 'lagos_ticket' || !user_can($comment->user_id, 'manage_options')) return;
    $uid  = (int) get_post_meta($post->ID, '_lagos_user', true);
    $user = get_userdata($uid);
    if (!$user) return;
    lagos_notify(
        $user->user_email,
        'Re: [LAGOS-#' . $post->ID . '] ' . $post->post_title,
        'Sua equipe respondeu!',
        '<p>Olá, <strong>' . $user->display_name . '</strong>!</p>
         <p>Há uma nova resposta da equipe Lagos no ticket <strong>"' . $post->post_title . '"</strong>.</p>',
        'Ver a resposta',
        lagos_panel_url('suporte')
    );
}, 20);

/* =========================================================
   CAMPOS DE SMTP NA PÁGINA DE CONFIGURAÇÕES
   ========================================================= */
add_action('lagos_settings_extra', function () {
    $f = [
        'lagos_smtp_host'     => get_option('lagos_smtp_host', ''),
        'lagos_smtp_port'     => get_option('lagos_smtp_port', 587),
        'lagos_smtp_sec'      => get_option('lagos_smtp_sec', 'tls'),
        'lagos_smtp_user'     => get_option('lagos_smtp_user', ''),
        'lagos_smtp_pass'     => get_option('lagos_smtp_pass', ''),
        'lagos_mail_from'     => get_option('lagos_mail_from', ''),
        'lagos_mail_from_name'=> get_option('lagos_mail_from_name', 'LagosPanel'),
    ];
    ?>
    <h2 style="margin-top:36px">SMTP — Envio de e-mails</h2>
    <p class="text-muted">Configure um servidor SMTP (ex.: Mailgun, SendGrid, Brevo, Gmail). Sem preencher, o sistema usa o mail() do servidor.</p>
    <table class="form-table" role="presentation">
        <tr><th>Servidor SMTP</th><td><input type="text" name="smtp_host" class="regular-text" value="<?php echo esc_attr($f['lagos_smtp_host']); ?>" placeholder="smtp.brevo.com"></td></tr>
        <tr><th>Porta</th><td><input type="number" name="smtp_port" value="<?php echo esc_attr($f['lagos_smtp_port']); ?>" style="width:90px"> <span class="text-muted">587 (TLS) · 465 (SSL)</span></td></tr>
        <tr><th>Segurança</th><td>
            <select name="smtp_sec">
                <?php foreach (['tls' => 'TLS (recomendado)', 'ssl' => 'SSL', 'none' => 'Nenhuma'] as $k => $l) : ?>
                    <option value="<?php echo esc_attr($k); ?>" <?php selected($f['lagos_smtp_sec'], $k); ?>><?php echo esc_html($l); ?></option>
                <?php endforeach; ?>
            </select>
        </td></tr>
        <tr><th>Usuário</th><td><input type="text" name="smtp_user" class="regular-text" value="<?php echo esc_attr($f['lagos_smtp_user']); ?>"></td></tr>
        <tr><th>Senha</th><td><input type="password" name="smtp_pass" class="regular-text" value="<?php echo esc_attr($f['lagos_smtp_pass']); ?>" autocomplete="new-password"></td></tr>
        <tr><th>E-mail remetente</th><td><input type="email" name="mail_from" class="regular-text" value="<?php echo esc_attr($f['lagos_mail_from']); ?>" placeholder="noreply@seudominio.com"></td></tr>
        <tr><th>Nome remetente</th><td><input type="text" name="mail_from_name" class="regular-text" value="<?php echo esc_attr($f['lagos_mail_from_name']); ?>"></td></tr>
    </table>
    <p>
        <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=lagos_smtp_test'), 'lagos_smtp_test')); ?>">Enviar e-mail de teste</a>
    </p>

    <h2 style="margin-top:32px">Log de e-mails (últimas 10)</h2>
    <table class="widefat striped" style="max-width:900px">
        <thead><tr><th style="width:150px">Data</th><th style="width:220px">Destinatário</th><th>Assunto</th><th style="width:60px">Status</th></tr></thead>
        <tbody>
        <?php $log = array_slice((array) get_option('lagos_mail_log', []), 0, 10); ?>
        <?php if ($log) : foreach ($log as $l) : ?>
            <tr>
                <td><?php echo esc_html(mysql2date('d/m/y H:i', $l['time'])); ?></td>
                <td><?php echo esc_html($l['to']); ?></td>
                <td><?php echo esc_html($l['subject']); ?></td>
                <td><?php echo $l['ok'] ? '&#10003;' : '&#10007;'; ?></td>
            </tr>
        <?php endforeach; else : ?>
            <tr><td colspan="4">Nenhum e-mail enviado ainda.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php
});

/** Salva campos de SMTP junto com as configurações */
add_action('lagos_settings_saved', function () {
    update_option('lagos_smtp_host', sanitize_text_field(wp_unslash($_POST['smtp_host'] ?? '')));
    update_option('lagos_smtp_port', (int) ($_POST['smtp_port'] ?? 587));
    update_option('lagos_smtp_sec', in_array($_POST['smtp_sec'] ?? '', ['tls', 'ssl', 'none'], true) ? $_POST['smtp_sec'] : 'tls');
    update_option('lagos_smtp_user', sanitize_text_field(wp_unslash($_POST['smtp_user'] ?? '')));
    update_option('lagos_smtp_pass', (string) wp_unslash($_POST['smtp_pass'] ?? ''));
    update_option('lagos_mail_from', sanitize_email(wp_unslash($_POST['mail_from'] ?? '')));
    update_option('lagos_mail_from_name', sanitize_text_field(wp_unslash($_POST['mail_from_name'] ?? 'LagosPanel')));
});

/** E-mail de teste */
add_action('admin_post_lagos_smtp_test', function () {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');
    check_admin_referer('lagos_smtp_test');
    $to = wp_get_current_user()->user_email;
    $ok = lagos_notify($to, 'E-mail de teste', 'SMTP funcionando!',
        '<p>Se você recebeu esta mensagem, o envio via SMTP está configurado corretamente.</p>');
    wp_redirect(add_query_arg('lagos_mailtest', $ok ? 'ok' : 'fail', admin_url('admin.php?page=lagos-settings')));
    exit;
});
