<?php
/**
 * LagosPanel Core — Segurança: 2FA (TOTP) + Sessões ativas (v0.4)
 * Implementação TOTP pura (RFC 6238) sem dependências externas.
 */
if (!defined('ABSPATH')) exit;

/* =========================================================
   TOTP (RFC 6238) — base32 + hmac-sha1
   ========================================================= */
function lagos_b32_encode($data) {
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $out = ''; $bits = '';
    foreach (str_split($data) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    foreach (str_split($bits, 5) as $chunk) {
        if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0');
        $out .= $alpha[bindec($chunk)];
    }
    return $out;
}

function lagos_b32_decode($s) {
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $s));
    $bits = '';
    foreach (str_split($s) as $c) $bits .= str_pad(decbin(strpos($alpha, $c)), 5, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) $out .= chr(bindec($chunk));
    }
    return $out;
}

function lagos_totp_code($b32_secret, $slice = null) {
    if ($slice === null) $slice = floor(time() / 30);
    $key = lagos_b32_decode($b32_secret);
    if (strlen($key) < 10) return false;
    $counter = pack('N2', ($slice >> 32) & 0xFFFFFFFF, $slice & 0xFFFFFFFF);
    $hash = hash_hmac('sha1', $counter, $key, true);
    $off = ord($hash[19]) & 0x0F;
    $code = (unpack('N', substr($hash, $off, 4))[1] & 0x7FFFFFFF) % 1000000;
    return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
}

/** Verifica código com janela de tolerância (±1 passo de 30s) */
function lagos_totp_verify($b32_secret, $code) {
    $code = preg_replace('/\D/', '', (string) $code);
    if (strlen($code) !== 6) return false;
    foreach ([0, -1, 1] as $w) {
        if (hash_equals((string) lagos_totp_code($b32_secret, floor(time() / 30) + $w), $code)) return true;
    }
    return false;
}

function lagos_2fa_secret($uid) {
    $sec = get_user_meta($uid, '_lagos_2fa_secret', true);
    if (!$sec) {
        $sec = lagos_b32_encode(random_bytes(20));
        update_user_meta($uid, '_lagos_2fa_secret', $sec);
    }
    return $sec;
}

function lagos_2fa_enabled($uid) {
    return get_user_meta($uid, '_lagos_2fa_enabled', true) === '1';
}

/* =========================================================
   LOGIN — etapa 2FA
   ========================================================= */
add_filter('lagos_login_before_signon', function ($_ignored, $email, $password) {
    $user = wp_authenticate($email, $password);
    if (!is_wp_error($user) && lagos_2fa_enabled($user->ID)) {
        $token = wp_generate_password(32, false, false);
        set_transient('lagos_2fa_' . $token, $user->ID, 300);
        wp_safe_redirect(home_url('/entrar/?lagos_2fa=' . $token));
        exit;
    }
    return null;
}, 10, 3);

/* =========================================================
   HANDLERS (2FA ativar/confirmar/desativar, sessões)
   ========================================================= */
add_action('template_redirect', function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['lagos_action'])) return;
    $action = sanitize_key($_POST['lagos_action']);
    if (!in_array($action, ['login_2fa', 'twofa_start', 'twofa_confirm', 'twofa_disable', 'sessions_destroy'], true)) return;
    if (!isset($_POST['lagos_nonce']) || !wp_verify_nonce($_POST['lagos_nonce'], 'lagos_security')) return;

    // ---- etapa 2FA do login ----
    if ($action === 'login_2fa') {
        $token = sanitize_text_field($_POST['token'] ?? '');
        $code  = sanitize_text_field($_POST['code'] ?? '');
        $uid   = (int) get_transient('lagos_2fa_' . $token);
        if ($uid && lagos_totp_verify(get_user_meta($uid, '_lagos_2fa_secret', true), $code)) {
            delete_transient('lagos_2fa_' . $token);
            wp_set_current_user($uid);
            wp_set_auth_cookie($uid, true);
            wp_safe_redirect(lagos_panel_url(''));
            exit;
        }
        wp_safe_redirect(home_url('/entrar/?lagos_2fa=' . urlencode($token) . '&lagos_err=err_2fa'));
        exit;
    }

    if (!is_user_logged_in()) return;
    $uid = get_current_user_id();

    // ---- iniciar ativação ----
    if ($action === 'twofa_start') {
        lagos_2fa_secret($uid);
        update_user_meta($uid, '_lagos_2fa_pending', '1');
        wp_safe_redirect(add_query_arg('lagos_msg', 'tfa_started', lagos_panel_url('perfil')));
        exit;
    }

    // ---- confirmar com código ----
    if ($action === 'twofa_confirm') {
        $code = sanitize_text_field($_POST['code'] ?? '');
        if (lagos_totp_verify(get_user_meta($uid, '_lagos_2fa_secret', true), $code)) {
            update_user_meta($uid, '_lagos_2fa_enabled', '1');
            update_user_meta($uid, '_lagos_2fa_pending', '');
            lagos_audit('2fa_on', 'autenticação em dois fatores ativada', $uid);
            wp_safe_redirect(add_query_arg('lagos_msg', 'tfa_enabled', lagos_panel_url('perfil')));
        } else {
            wp_safe_redirect(add_query_arg('lagos_err', 'tfa_invalid_code', lagos_panel_url('perfil')));
        }
        exit;
    }

    // ---- desativar (exige senha) ----
    if ($action === 'twofa_disable') {
        $user = get_userdata($uid);
        $pass = (string) ($_POST['current'] ?? '');
        if ($pass && wp_check_password($pass, $user->user_pass, $uid)) {
            update_user_meta($uid, '_lagos_2fa_enabled', '');
            update_user_meta($uid, '_lagos_2fa_secret', '');
            update_user_meta($uid, '_lagos_2fa_pending', '');
            lagos_audit('2fa_off', 'autenticação em dois fatores desativada', $uid);
            wp_safe_redirect(add_query_arg('lagos_msg', 'tfa_disabled', lagos_panel_url('perfil')));
        } else {
            wp_safe_redirect(add_query_arg('lagos_err', 'wrong_pass', lagos_panel_url('perfil')));
        }
        exit;
    }

    // ---- encerrar outras sessões ----
    if ($action === 'sessions_destroy') {
        $manager = WP_Session_Tokens::get_instance($uid);
        $manager->destroy_others(wp_get_session_token());
        wp_safe_redirect(add_query_arg('lagos_msg', 'sessions_closed', lagos_panel_url('perfil')));
        exit;
    }
});

/* =========================================================
   UI — seção de segurança no perfil + passo 2FA no login
   ========================================================= */

/** Formulário de código 2FA (dentro do shortcode de login) */
function lagos_2fa_login_form() {
    $token = sanitize_text_field($_GET['lagos_2fa'] ?? '');
    if (!$token) return '';
    ob_start();
    ?>
    <form method="post" style="margin-top:18px">
        <input type="hidden" name="lagos_action" value="login_2fa">
        <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
        <?php wp_nonce_field('lagos_security', 'lagos_nonce'); ?>
        <div class="field">
            <label><?php lagos_e('tfa_code_label'); ?></label>
            <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" required autofocus autocomplete="one-time-code" style="text-align:center;font-size:1.4rem;letter-spacing:.4em;font-weight:700" placeholder="••••••">
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg"><?php lagos_e('btn_login'); ?></button>
    </form>
    <?php
    return ob_get_clean();
}

/** Card de segurança (2FA + sessões) — chamado no perfil */
function lagos_security_profile_section() {
    $uid = get_current_user_id();
    $enabled = lagos_2fa_enabled($uid);
    $pending = get_user_meta($uid, '_lagos_2fa_pending', true) === '1';
    $secret  = get_user_meta($uid, '_lagos_2fa_secret', true);
    $user    = wp_get_current_user();
    $otpauth = 'otpauth://totp/' . rawurlencode('LagosPanel:' . $user->user_email) . '?secret=' . $secret . '&issuer=LagosPanel';
    ?>
    <div class="card">
        <?php if (!$enabled && !$pending) : ?>
        <div class="tfa-row">
            <div>
                <h3 class="card-title" style="margin-bottom:4px"><?php echo lagos_icon('shield', 18); ?> <?php lagos_e('tfa'); ?></h3>
                <p class="text-muted" style="margin:0;font-size:.88rem"><?php lagos_e('tfa_sub'); ?></p>
            </div>
            <form method="post">
                <input type="hidden" name="lagos_action" value="twofa_start">
                <?php wp_nonce_field('lagos_security', 'lagos_nonce'); ?>
                <button type="submit" class="btn btn-primary btn-sm"><?php lagos_e('tfa_enable'); ?></button>
            </form>
        </div>

        <?php elseif ($pending && !$enabled) : ?>
        <h3 class="card-title"><?php echo lagos_icon('key', 18); ?> <?php lagos_e('tfa_setup'); ?></h3>
        <p class="text-muted" style="margin-top:0;font-size:.9rem"><?php lagos_e('tfa_step1'); ?></p>
        <div class="api-key-box mb-20"><code><?php echo esc_html(trim_chunk_split($secret, 4)); ?></code></div>
        <p style="margin:-6px 0 16px"><a class="btn btn-ghost btn-sm" href="<?php echo esc_attr($otpauth); ?>"><?php echo lagos_icon('send', 14); ?> <?php lagos_e('tfa_otpauth'); ?></a></p>
        <p class="text-muted" style="margin:0 0 10px;font-size:.9rem"><?php lagos_e('tfa_step2'); ?></p>
        <form method="post">
            <input type="hidden" name="lagos_action" value="twofa_confirm">
            <?php wp_nonce_field('lagos_security', 'lagos_nonce'); ?>
            <div class="flex-between">
                <input type="text" name="code" inputmode="numeric" maxlength="6" required placeholder="••••••" style="width:130px;text-align:center;font-size:1.15rem;letter-spacing:.3em;font-weight:700;padding:9px;border:1px solid var(--line);border-radius:10px">
                <button type="submit" class="btn btn-primary"><?php lagos_e('tfa_confirm'); ?></button>
            </div>
        </form>

        <?php else : ?>
        <div class="tfa-row">
            <div>
                <h3 class="card-title" style="margin-bottom:4px"><?php echo lagos_icon('shield', 18); ?> <?php lagos_e('tfa'); ?></h3>
                <p class="text-muted" style="margin:0;font-size:.88rem">
                    <span class="badge badge-active"><span class="badge-dot"></span><?php lagos_e('tfa_active'); ?></span>
                </p>
            </div>
            <form method="post" onsubmit="return confirm('<?php echo esc_attr(lagos_t('tfa_disable_confirm')); ?>')">
                <input type="hidden" name="lagos_action" value="twofa_disable">
                <?php wp_nonce_field('lagos_security', 'lagos_nonce'); ?>
                <div style="display:flex;gap:6px">
                    <input type="password" name="current" placeholder="<?php echo esc_attr(lagos_t('label_password')); ?>" required style="width:130px;padding:8px 10px;border:1px solid var(--line);border-radius:9px">
                    <button type="submit" class="btn btn-danger btn-sm"><?php lagos_e('tfa_disable'); ?></button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <?php lagos_sessions_card($uid); ?>
    <?php
}

function trim_chunk_split($s, $n) {
    return trim(chunk_split($s, $n, ' '));
}

function lagos_sessions_card($uid) {
    $manager = WP_Session_Tokens::get_instance($uid);
    $sessions = $manager->get_all();
    $cur = wp_get_session_token();
    ?>
    <div class="card">
        <h3 class="card-title"><?php echo lagos_icon('globe', 18); ?> <?php lagos_e('sessions'); ?></h3>
        <div class="kv-list">
            <?php foreach (array_slice($sessions, 0, 6) as $hash => $s) :
                $ua = $s['ua'] ?? '';
                $dev = strpos($ua, 'Mobile') !== false ? 'Mobile' : (strpos($ua, 'Windows') !== false ? 'Windows' : (strpos($ua, 'Mac') !== false ? 'macOS' : (strpos($ua, 'Linux') !== false ? 'Linux' : '—')));
            ?>
            <div>
                <dt><?php echo ($hash === $cur) ? '<span class="badge badge-active"><span class="badge-dot"></span>' . esc_html(lagos_t('sessions_current')) . '</span>' : esc_html($dev); ?></dt>
                <dd><?php echo esc_html(($s['ip'] ?? '—') . ' · ' . date_i18n('d/m/y H:i', $s['login'])); ?></dd>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($sessions) > 1) : ?>
        <form method="post" style="margin-top:14px">
            <input type="hidden" name="lagos_action" value="sessions_destroy">
            <?php wp_nonce_field('lagos_security', 'lagos_nonce'); ?>
            <button type="submit" class="btn btn-ghost btn-sm"><?php lagos_e('sessions_end_others'); ?></button>
        </form>
        <?php endif; ?>
    </div>
    <?php
}


/* ══════════════════════════════════════════════════════════
   RATE LIMIT DE LOGIN (produção) — 5 tentativas / 15 min
   ══════════════════════════════════════════════════════════ */
add_filter('authenticate', function ($user, $login, $password) {
    if (!$login) return $user;
    // o bloqueio tem precedência sobre o erro de senha do core
    $key = 'lagos_ll_' . md5(($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $login);
    if ((int) get_transient($key) >= 5) {
        return new WP_Error('lagos_locked', 'Muitas tentativas de login. Aguarde 15 minutos e tente novamente.');
    }
    return $user;
}, 30, 3);

add_action('wp_login_failed', function ($login) {
    if (!$login) return;
    $key = 'lagos_ll_' . md5(($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $login);
    set_transient($key, (int) get_transient($key) + 1, 15 * MINUTE_IN_SECONDS);
});

add_action('wp_login', function ($login, $user) {
    $key = 'lagos_ll_' . md5(($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $login);
    delete_transient($key);
}, 10, 2);
