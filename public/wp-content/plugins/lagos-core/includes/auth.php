<?php
/**
 * LagosPanel Core — Autenticação (login/cadastro front-end)
 */
if (!defined('ABSPATH')) exit;

/** Papel de cliente */
add_action('init', function () {
    if (!get_role('lagos_client')) {
        add_role('lagos_client', 'Cliente Lagos', ['read' => true]);
    }
});

/** Trata POSTs de login/cadastro */
add_action('template_redirect', function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['lagos_action'])) return;

    $action = sanitize_key($_POST['lagos_action']);

    // ---------- LOGIN ----------
    if ($action === 'login') {
        if (!isset($_POST['lagos_nonce']) || !wp_verify_nonce($_POST['lagos_nonce'], 'lagos_auth')) {
            wp_safe_redirect(home_url('/entrar/'));
            exit;
        }
        $email    = sanitize_email(wp_unslash($_POST['log'] ?? ''));
        $password = (string) ($_POST['pwd'] ?? '');
        $redirect = (string) ($_POST['redirect_to'] ?? '');

        if (!$email || !$password) {
            wp_safe_redirect(home_url('/entrar/?lagos_err=err_required'));
            exit;
        }
        // 2FA: se habilitado, redireciona para a etapa do código (exit interno)
        apply_filters('lagos_login_before_signon', null, $email, $password);

        $user = wp_signon([
            'user_login'    => $email,
            'user_password' => $password,
            'remember'      => !empty($_POST['rememberme']),
        ]);
        if (is_wp_error($user)) {
            // conta com e-mail não confirmado → mensagem específica com opção de reenvio
            if ($user->get_error_code() === 'lagos_unconfirmed') {
                wp_safe_redirect(home_url('/entrar/?lagos_err=err_confirm_email&resend=' . rawurlencode($email)));
                exit;
            }
            wp_safe_redirect(home_url('/entrar/?lagos_err=err_login'));
            exit;
        }
        // destino seguro (apenas caminhos internos)
        $to = lagos_panel_url('');
        if ($redirect && $redirect[0] === '/' && strpos($redirect, '//') !== 0) {
            $to = home_url($redirect);
        }
        wp_safe_redirect($to);
        exit;
    }

    // ---------- CADASTRO ----------
    if ($action === 'register') {
        if (!isset($_POST['lagos_nonce']) || !wp_verify_nonce($_POST['lagos_nonce'], 'lagos_auth')) {
            wp_safe_redirect(home_url('/registrar/'));
            exit;
        }
        $name     = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $email    = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['confirm'] ?? '');

        if (!$name || !$email || !$password || !$confirm) {
            wp_safe_redirect(home_url('/registrar/?lagos_err=err_required'));
            exit;
        }
        if (!is_email($email)) {
            wp_safe_redirect(home_url('/registrar/?lagos_err=err_email'));
            exit;
        }
        if (email_exists($email)) {
            wp_safe_redirect(home_url('/registrar/?lagos_err=err_exists'));
            exit;
        }
        if (strlen($password) < 6) {
            wp_safe_redirect(home_url('/registrar/?lagos_err=err_short'));
            exit;
        }
        if ($password !== $confirm) {
            wp_safe_redirect(home_url('/registrar/?lagos_err=err_match'));
            exit;
        }
        // LGPD: aceite obrigatório dos Termos e da Política de Privacidade
        if (empty($_POST['terms'])) {
            wp_safe_redirect(home_url('/registrar/?lagos_err=err_terms'));
            exit;
        }

        $uid = wp_insert_user([
            'user_login'   => $email,
            'user_email'   => $email,
            'user_pass'    => $password,
            'display_name' => $name,
            'first_name'   => $name,
            'role'         => 'lagos_client',
        ]);
        if (is_wp_error($uid)) {
            wp_safe_redirect(home_url('/registrar/?lagos_err=err_required'));
            exit;
        }
        update_user_meta($uid, 'lagos_balance', 0);
        update_user_meta($uid, '_lagos_terms_accepted', current_time('mysql') . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
        lagos_api_key($uid);
        lagos_audit('register', 'novo cadastro: ' . $email, $uid);
        if (function_exists('lagos_webhook_send')) lagos_webhook_send('client.created', ['user' => $uid, 'email' => $email]);

        // confirmação de e-mail ativada → conta fica pendente até clicar no link
        if (get_option('lagos_email_confirm', 1)) {
            $key = wp_generate_password(32, false, false);
            update_user_meta($uid, '_lagos_activation', $key);
            update_user_meta($uid, '_lagos_activation_time', time());
            if (function_exists('lagos_mail_activation')) lagos_mail_activation(get_userdata($uid), home_url('/entrar/?lagos_activate=' . $key . '&uid=' . $uid));
            wp_safe_redirect(home_url('/entrar/?lagos_msg=check_email'));
            exit;
        }

        if (function_exists('lagos_mail_welcome')) lagos_mail_welcome(get_userdata($uid));
        wp_signon(['user_login' => $email, 'user_password' => $password, 'remember' => true]);
        wp_safe_redirect(lagos_panel_url('?lagos_msg=msg_welcome'));
        exit;
    }
});

/** Contas com e-mail não confirmado não entram */
add_filter('authenticate', function ($user, $login, $password) {
    if (!is_object($user) || is_wp_error($user)) return $user;
    if (get_user_meta($user->ID, '_lagos_activation', true)) {
        return new WP_Error('lagos_unconfirmed', lagos_t('err_confirm_email'));
    }
    return $user;
}, 35, 3);

/** Ativação de conta (?lagos_activate=...&uid=...) e reenvio do e-mail */
add_action('template_redirect', function () {
    if (!is_page('entrar')) return;

    if (!empty($_GET['lagos_activate'])) {
        $key = preg_replace('/[^a-z0-9]/i', '', $_GET['lagos_activate']);
        $uid = absint($_GET['uid'] ?? 0);
        $u   = get_userdata($uid);
        $ok  = $u && get_user_meta($uid, '_lagos_activation', true) === $key;

        // expira em 24h
        if ($ok && (time() - (int) get_user_meta($uid, '_lagos_activation_time', true)) > DAY_IN_SECONDS) $ok = false;

        if ($ok) {
            delete_user_meta($uid, '_lagos_activation');
            delete_user_meta($uid, '_lagos_activation_time');
            lagos_audit('email_confirm', 'e-mail confirmado no cadastro', $uid);
            if (function_exists('lagos_mail_welcome')) lagos_mail_welcome($u);
            wp_safe_redirect(home_url('/entrar/?lagos_msg=activated_ok'));
        } else {
            wp_safe_redirect(home_url('/entrar/?lagos_err=err_activation'));
        }
        exit;
    }

    if (!empty($_GET['lagos_resend'])) {
        $u = get_user_by('email', sanitize_email($_GET['lagos_resend']));
        if ($u && get_user_meta($u->ID, '_lagos_activation', true)) {
            $key = wp_generate_password(32, false, false);
            update_user_meta($u->ID, '_lagos_activation', $key);
            update_user_meta($u->ID, '_lagos_activation_time', time());
            if (function_exists('lagos_mail_activation')) lagos_mail_activation($u, home_url('/entrar/?lagos_activate=' . $key . '&uid=' . $u->ID));
            wp_safe_redirect(home_url('/entrar/?lagos_msg=check_email'));
            exit;
        }
        wp_safe_redirect(home_url('/entrar/?lagos_err=err_activation'));
        exit;
    }
});

/** Esqueci minha senha / redefinição (fluxo próprio do painel) */
add_action('template_redirect', function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['lagos_action'])) return;
    $action = sanitize_key($_POST['lagos_action']);

    // ---- pedido de redefinição ----
    if ($action === 'forgot') {
        if (!isset($_POST['lagos_nonce']) || !wp_verify_nonce($_POST['lagos_nonce'], 'lagos_auth')) {
            wp_safe_redirect(home_url('/entrar/')); exit;
        }
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $u = $email ? get_user_by('email', $email) : false;
        if ($u) {
            $key = get_password_reset_key($u);
            if (!is_wp_error($key)) {
                lagos_audit('forgot_request', 'pedido de redefinição de senha', $u->ID);
                if (function_exists('lagos_mail_resetpass')) lagos_mail_resetpass($u, home_url('/entrar/?lagos_reset=' . rawurlencode($key) . '&login=' . rawurlencode($u->user_login)));
            }
        }
        // resposta idêntica com ou sem conta (evita enumeração de e-mails)
        wp_safe_redirect(home_url('/entrar/?lagos_msg=reset_sent'));
        exit;
    }

    // ---- definir a nova senha ----
    if ($action === 'resetpass') {
        if (!isset($_POST['lagos_nonce']) || !wp_verify_nonce($_POST['lagos_nonce'], 'lagos_auth')) {
            wp_safe_redirect(home_url('/entrar/')); exit;
        }
        $login = sanitize_user($_POST['login'] ?? '');
        $key   = (string) ($_POST['key'] ?? '');
        $pass  = (string) ($_POST['password'] ?? '');
        $conf  = (string) ($_POST['confirm'] ?? '');
        $u     = $login ? get_user_by('login', $login) : false;

        if (!$u || strlen($pass) < 6 || $pass !== $conf) {
            wp_safe_redirect(home_url('/entrar/?lagos_err=err_reset_invalid')); exit;
        }
        $check = check_password_reset_key($key, $login);
        if (is_wp_error($check)) {
            wp_safe_redirect(home_url('/entrar/?lagos_err=err_reset_invalid')); exit;
        }
        reset_password($u, $pass);
        lagos_audit('password_reset', 'senha redefinida via e-mail', $u->ID);
        wp_safe_redirect(home_url('/entrar/?lagos_msg=reset_ok'));
        exit;
    }
});

/** Redireciona usuários logados que acessam /entrar */
add_action('template_redirect', function () {
    if (!is_user_logged_in()) return;
    if (is_page('entrar') || is_page('registrar')) {
        wp_safe_redirect(lagos_panel_url(''));
        exit;
    }
});

/** Mensagem de logout (via redirect para /entrar) */
add_action('template_redirect', function () {
    if (is_page('entrar') && isset($_GET['loggedout']) && $_GET['loggedout'] === 'true') {
        // wp_logout_url adiciona ?loggedout=true — sinalizamos como flash
        if (empty($_GET['lagos_msg'])) {
            wp_safe_redirect(add_query_arg('lagos_msg', 'msg_logged_out', home_url('/entrar/')));
            exit;
        }
    }
});


/* ═══ Segurança de acesso: auditoria + alerta de novo IP ═══ */
add_action('wp_login', function ($login, $user) {
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $last = get_user_meta($user->ID, '_lagos_last_ip', true);

    lagos_audit('login_ok', 'acesso permitido', $user->ID);

    // avisa quando a conta for acessada de um IP diferente do habitual
    if ($last !== '' && $last !== $ip && in_array('lagos_client', (array) $user->roles, true) && get_option('lagos_alert_newip', 1)) {
        if (function_exists('lagos_mail_newlogin')) {
            lagos_mail_newlogin($user, $ip, date_i18n('d/m/Y H:i'), mb_substr($ua, 0, 120));
        }
    }
    update_user_meta($user->ID, '_lagos_last_ip', $ip);
    update_user_meta($user->ID, '_lagos_last_login', current_time('mysql'));
}, 5, 2);

add_action('wp_login_failed', function ($login) {
    lagos_audit('login_fail', 'tentativa falha de login: ' . $login);
});

add_action('wp_logout', function () {
    $u = wp_get_current_user();
    if ($u && $u->ID) lagos_audit('logout', 'sessão encerrada', $u->ID);
});
