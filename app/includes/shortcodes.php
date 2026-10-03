<?php
/**
 * LagosPanel Core — Shortcodes da área do cliente e da loja
 */
if (!defined('ABSPATH')) exit;

function lagos_login_prompt() {
    return '<div class="card"><p>' . esc_html(lagos_t('must_login')) . '</p><a class="btn btn-primary" href="' . esc_url(home_url('/entrar/')) . '">' . esc_html(lagos_t('btn_login')) . '</a></div>';
}

/* =========================================================
   LOGIN / CADASTRO
   ========================================================= */
add_shortcode('lagos_login', function () {
    if (is_user_logged_in()) return '';

    // ── Esqueci minha senha (form de e-mail) ──
    if (!empty($_GET['lagos_forgot'])) {
        ob_start(); ?>
    <div class="auth-wrap">
      <div class="auth-card">
        <div class="auth-brand"><?php echo lagospanel_logo_link(); ?></div>
        <h2 class="auth-title"><?php lagos_e('forgot_title'); ?></h2>
        <p class="auth-sub"><?php lagos_e('forgot_sub'); ?></p>
        <?php echo lagos_flash(); ?>
        <form method="post">
          <input type="hidden" name="lagos_action" value="forgot">
          <?php wp_nonce_field('lagos_auth', 'lagos_nonce'); ?>
          <div class="field">
            <label><?php lagos_e('label_email'); ?></label>
            <input type="email" name="email" required autofocus autocomplete="email">
          </div>
          <button type="submit" class="btn btn-primary btn-block btn-lg"><?php lagos_e('forgot_btn'); ?></button>
        </form>
        <p class="auth-alt"><a href="<?php echo esc_url(home_url('/entrar/')); ?>"><?php lagos_e('back_login'); ?></a></p>
      </div>
    </div>
        <?php return ob_get_clean();
    }

    // ── Definir nova senha (link do e-mail) ──
    if (!empty($_GET['lagos_reset'])) {
        $rkey = sanitize_text_field($_GET['lagos_reset']);
        $rlogin = sanitize_user($_GET['login'] ?? '');
        ob_start(); ?>
    <div class="auth-wrap">
      <div class="auth-card">
        <div class="auth-brand"><?php echo lagospanel_logo_link(); ?></div>
        <h2 class="auth-title"><?php lagos_e('reset_title'); ?></h2>
        <p class="auth-sub"><?php lagos_e('reset_sub'); ?></p>
        <?php echo lagos_flash(); ?>
        <form method="post">
          <input type="hidden" name="lagos_action" value="resetpass">
          <input type="hidden" name="key" value="<?php echo esc_attr($rkey); ?>">
          <input type="hidden" name="login" value="<?php echo esc_attr($rlogin); ?>">
          <?php wp_nonce_field('lagos_auth', 'lagos_nonce'); ?>
          <div class="form-grid">
            <div class="field">
              <label><?php lagos_e('label_password'); ?></label>
              <input type="password" name="password" required minlength="6" autocomplete="new-password" autofocus>
            </div>
            <div class="field">
              <label><?php lagos_e('label_confirm'); ?></label>
              <input type="password" name="confirm" required minlength="6" autocomplete="new-password">
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-block btn-lg"><?php lagos_e('reset_btn'); ?></button>
        </form>
        <p class="auth-alt"><a href="<?php echo esc_url(home_url('/entrar/')); ?>"><?php lagos_e('back_login'); ?></a></p>
      </div>
    </div>
        <?php return ob_get_clean();
    }

    // Etapa 2: código 2FA
    if (!empty($_GET['lagos_2fa']) && function_exists('lagos_2fa_login_form')) {
        ob_start(); ?>
        <div class="auth-wrap">
          <div class="auth-card">
            <div class="auth-brand"><?php echo lagospanel_logo_link(); ?></div>
            <h2 class="auth-title"><?php lagos_e('tfa_title'); ?></h2>
            <p class="auth-sub"><?php lagos_e('tfa_sub2'); ?></p>
            <?php echo lagos_flash(); ?>
            <?php echo lagos_2fa_login_form(); ?>
            <p class="auth-alt"><a href="<?php echo esc_url(home_url('/entrar/')); ?>"><?php lagos_e('back_login'); ?></a></p>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }

    ob_start();
    ?>
    <div class="auth-wrap">
      <div class="auth-card">
        <div class="auth-brand"><?php echo lagospanel_logo_link(); ?></div>
        <h2 class="auth-title"><?php lagos_e('login_title'); ?></h2>
        <p class="auth-sub"><?php lagos_e('login_sub'); ?></p>
        <?php echo lagos_flash(); ?>
        <?php if (!empty($_GET['resend']) && is_email($_GET['resend'])) : ?>
          <p style="font-size:.85rem"><a class="text-muted" href="<?php echo esc_url(home_url('/entrar/?lagos_resend=' . rawurlencode($_GET['resend']))); ?>">Não recebi o e-mail — reenviar confirmação</a></p>
        <?php endif; ?>
        <form method="post">
          <input type="hidden" name="lagos_action" value="login">
          <?php wp_nonce_field('lagos_auth', 'lagos_nonce'); ?>
          <input type="hidden" name="redirect_to" value="<?php echo esc_attr($_GET['redirect_to'] ?? ''); ?>">
          <div class="field">
            <label><?php lagos_e('label_email'); ?></label>
            <input type="email" name="log" required autofocus autocomplete="email">
          </div>
          <div class="field">
            <label><?php lagos_e('label_password'); ?></label>
            <input type="password" name="pwd" required autocomplete="current-password">
          </div>
          <div class="flex-between mb-20">
            <label class="check-line"><input type="checkbox" name="rememberme" value="1"> <?php lagos_e('remember'); ?></label>
            <a href="<?php echo esc_url(home_url('/entrar/?lagos_forgot=1')); ?>" class="text-muted"><?php lagos_e('forgot'); ?></a>
          </div>
          <button type="submit" class="btn btn-primary btn-block btn-lg"><?php lagos_e('btn_login'); ?></button>
        </form>
        <p class="auth-alt"><?php lagos_e('no_account'); ?> <a href="<?php echo esc_url(home_url('/registrar/')); ?>"><?php lagos_e('btn_register'); ?></a></p>
      </div>
      <p class="auth-foot">© <?php echo esc_html(date_i18n('Y')); ?> — <?php lagos_e('brand_tag'); ?></p>
    </div>
    <?php
    return ob_get_clean();
});

add_shortcode('lagos_register', function () {
    if (is_user_logged_in()) return '';
    ob_start();
    ?>
    <div class="auth-wrap">
      <div class="auth-card">
        <div class="auth-brand"><?php echo lagospanel_logo_link(); ?></div>
        <h2 class="auth-title"><?php lagos_e('register_title'); ?></h2>
        <p class="auth-sub"><?php lagos_e('register_sub'); ?></p>
        <form method="post">
          <input type="hidden" name="lagos_action" value="register">
          <?php wp_nonce_field('lagos_auth', 'lagos_nonce'); ?>
          <div class="field">
            <label><?php lagos_e('label_name'); ?></label>
            <input type="text" name="name" required autofocus autocomplete="name">
          </div>
          <div class="field">
            <label><?php lagos_e('label_email'); ?></label>
            <input type="email" name="email" required autocomplete="email">
          </div>
          <div class="form-grid">
            <div class="field">
              <label><?php lagos_e('label_password'); ?></label>
              <input type="password" name="password" required minlength="6" autocomplete="new-password">
            </div>
            <div class="field">
              <label><?php lagos_e('label_confirm'); ?></label>
              <input type="password" name="confirm" required minlength="6" autocomplete="new-password">
            </div>
          </div>
          <label class="terms-check" style="display:flex;gap:10px;align-items:flex-start;margin:14px 0 4px;font-size:.85rem;line-height:1.55">
            <input type="checkbox" name="terms" value="1" required style="margin-top:3px;width:16px;height:16px;flex:none">
            <span><?php echo lagos_t('reg_terms', [
                '{terms}'   => '<a href="' . esc_url(home_url('/termos-de-uso/')) . '">' . esc_html(lagos_t('footer_terms')) . '</a>',
                '{privacy}' => '<a href="' . esc_url(home_url('/politica-de-privacidade/')) . '">' . esc_html(lagos_t('footer_privacy')) . '</a>',
            ]); ?></span>
          </label>
          <button type="submit" class="btn btn-primary btn-block btn-lg"><?php lagos_e('btn_register'); ?></button>
        </form>
        <p class="auth-alt"><?php lagos_e('have_account'); ?> <a href="<?php echo esc_url(home_url('/entrar/')); ?>"><?php lagos_e('btn_login'); ?></a></p>
      </div>
    </div>
    <?php
    return ob_get_clean();
});

/* =========================================================
   DASHBOARD
   ========================================================= */
add_shortcode('lagos_dashboard', function () {
    if (!is_user_logged_in()) return lagos_login_prompt();
    $uid    = get_current_user_id();
    $user   = wp_get_current_user();
    $counts = lagos_user_counts($uid);
    $balance = lagos_balance($uid);

    $services = lagos_user_services($uid, 5);
    $invoices = lagos_user_invoices($uid, 5);
    $news     = get_posts(['numberposts' => 1, 'post_status' => 'publish']);
    ob_start();
    ?>
    <div class="welcome-row">
      <div>
        <h2><?php echo esc_html(lagos_t('welcome', ['{name}' => $user->display_name])); ?></h2>
        <p><?php lagos_e('welcome_sub'); ?></p>
      </div>
    </div>

    <?php if ($news) : $n = $news[0]; ?>
    <div class="announce">
      <div class="announce-ic"><?php echo lagos_icon('zap', 19); ?></div>
      <div>
        <h4><?php echo esc_html($n->post_title); ?></h4>
        <p><?php echo esc_html(wp_trim_words($n->post_content, 22, '…')); ?></p>
        <time><?php echo esc_html(get_the_date('', $n)); ?></time>
      </div>
    </div>
    <?php endif; ?>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-ic ic-green"><?php echo lagos_icon('server', 21); ?></div>
        <div><strong><?php echo (int) $counts['active']; ?></strong><span><?php lagos_e('st_services'); ?></span></div>
      </div>
      <div class="stat-card">
        <div class="stat-ic ic-orange"><?php echo lagos_icon('invoice', 21); ?></div>
        <div>
          <strong><?php echo (int) $counts['unpaid']; ?></strong><span><?php lagos_e('st_invoices'); ?></span>
          <?php if ($counts['unpaid']) : ?><small class="down"><?php echo esc_html(lagos_money($counts['unpaid_total'])); ?></small><?php endif; ?>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-ic ic-violet"><?php echo lagos_icon('ticket', 21); ?></div>
        <div><strong><?php echo (int) $counts['tickets']; ?></strong><span><?php lagos_e('st_tickets'); ?></span></div>
      </div>
      <div class="stat-card">
        <div class="stat-ic ic-blue"><?php echo lagos_icon('wallet', 21); ?></div>
        <div><strong><?php echo esc_html(lagos_money($balance)); ?></strong><span><?php lagos_e('st_balance'); ?></span></div>
      </div>
    </div>

    <div class="dash-cols">
      <div>
        <div class="ltable-wrap mb-20">
          <table class="ltable">
            <thead><tr>
              <th><?php lagos_e('col_invoice'); ?></th><th><?php lagos_e('col_status'); ?></th>
              <th><?php lagos_e('col_total'); ?></th><th><?php lagos_e('col_due'); ?></th><th></th>
            </tr></thead>
            <tbody>
            <?php if ($invoices) : foreach ($invoices as $inv) :
                $st = get_post_meta($inv->ID, '_lagos_status', true);
                $pay_url = ($st === 'unpaid' || $st === 'overdue')
                    ? lagos_checkout_url($inv->ID)
                    : '';
            ?>
              <tr>
                <td class="t-strong" data-label="<?php lagos_e('col_invoice'); ?>">#<?php echo esc_html(lagos_invoice_ref($inv->ID)); ?></td>
                <td data-label="<?php lagos_e('col_status'); ?>"><?php echo lagos_status_badge($st); ?></td>
                <td class="t-money" data-label="<?php lagos_e('col_total'); ?>"><?php echo esc_html(lagos_money(get_post_meta($inv->ID, '_lagos_amount', true))); ?></td>
                <td class="t-muted" data-label="<?php lagos_e('col_due'); ?>"><?php echo esc_html(lagos_date(get_post_meta($inv->ID, '_lagos_due', true))); ?></td>
                <td data-label="<?php lagos_e('col_actions'); ?>"><?php if ($pay_url) : ?><a class="btn btn-primary btn-sm" href="<?php echo esc_url($pay_url); ?>"><?php lagos_e('pay'); ?></a><?php endif; ?></td>
              </tr>
            <?php endforeach; else : ?>
              <tr><td colspan="5" class="t-muted"><?php lagos_e('no_invoices'); ?></td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="ltable-wrap">
          <table class="ltable">
            <thead><tr>
              <th><?php lagos_e('col_service'); ?></th><th><?php lagos_e('col_status'); ?></th>
              <th><?php lagos_e('col_price'); ?></th><th><?php lagos_e('col_next_due'); ?></th>
            </tr></thead>
            <tbody>
            <?php if ($services) : foreach ($services as $s) : ?>
              <tr>
                <td class="t-strong" data-label="<?php lagos_e('col_service'); ?>"><?php echo esc_html($s->post_title); ?></td>
                <td data-label="<?php lagos_e('col_status'); ?>"><?php echo lagos_status_badge(get_post_meta($s->ID, '_lagos_status', true)); ?></td>
                <td class="t-money" data-label="<?php lagos_e('col_price'); ?>"><?php echo esc_html(lagos_money(get_post_meta($s->ID, '_lagos_price', true))); ?></td>
                <td class="t-muted" data-label="<?php lagos_e('col_next_due'); ?>"><?php echo esc_html(lagos_date(get_post_meta($s->ID, '_lagos_next_due', true))); ?></td>
              </tr>
            <?php endforeach; else : ?>
              <tr><td colspan="4" class="t-muted"><?php lagos_e('no_services'); ?></td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div>
        <div class="card">
          <h3 class="card-title"><?php echo lagos_icon('zap', 18); ?> <?php lagos_e('quick_actions'); ?></h3>
          <div class="quick-actions">
            <a class="qa-btn" href="<?php echo esc_url(home_url('/loja/')); ?>"><span class="qa-ic"><?php echo lagos_icon('store', 18); ?></span> <?php lagos_e('qa_order'); ?></a>
            <a class="qa-btn" href="<?php echo esc_url(lagos_panel_url('suporte')); ?>"><span class="qa-ic"><?php echo lagos_icon('ticket', 18); ?></span> <?php lagos_e('qa_ticket'); ?></a>
            <a class="qa-btn" href="<?php echo esc_url(lagos_panel_url('faturas')); ?>"><span class="qa-ic"><?php echo lagos_icon('invoice', 18); ?></span> <?php lagos_e('qa_invoices'); ?></a>
          </div>
        </div>
      </div>
    </div>
    <?php
    return ob_get_clean();
});

/* =========================================================
   SERVIÇOS — cards estilo Paymenter (só que melhor)
   ========================================================= */
add_shortcode('lagos_services', function () {
    if (!is_user_logged_in()) return lagos_login_prompt();
    $uid = get_current_user_id();

    // Detalhe de um serviço (?view=ID)
    $view = isset($_GET['view']) ? absint($_GET['view']) : 0;
    if ($view) {
        $svc = get_post($view);
        if ($svc && $svc->post_type === 'lagos_service' && (int) get_post_meta($svc->ID, '_lagos_user', true) === $uid) {
            return lagos_service_detail($svc);
        }
    }

    $services = lagos_user_services($uid);
    ob_start();
    ?>
    <div class="store-head">
      <h2><?php lagos_e('services_title'); ?></h2>
      <p><?php lagos_e('services_sub'); ?></p>
    </div>

    <?php echo lagos_flash(); ?>

    <?php if ($services) : ?>
    <div class="svc-grid">
      <?php foreach ($services as $svc) :
          $st     = get_post_meta($svc->ID, '_lagos_status', true);
          $price  = get_post_meta($svc->ID, '_lagos_price', true);
          $cycle  = get_post_meta($svc->ID, '_lagos_cycle', true);
          $due    = get_post_meta($svc->ID, '_lagos_next_due', true);
          $cancel = get_post_meta($svc->ID, '_lagos_cancel_request', true);
          $icon   = lagos_service_icon($svc->ID);
      ?>
      <div class="svc-card">
        <div class="svc-top">
          <div class="svc-ic ic-violet"><?php echo lagos_icon($icon, 22); ?></div>
          <div class="svc-badges">
            <?php echo lagos_status_badge($st); ?>
            <?php if (function_exists('lagos_module_badge') && get_post_meta($svc->ID, '_lagos_module_status', true)) echo lagos_module_badge($svc->ID); ?>
            <?php if ($cancel) : ?><span class="badge badge-cancelled"><span class="badge-dot"></span><?php lagos_e('cancel_requested'); ?></span><?php endif; ?>
          </div>
        </div>
        <h3><?php echo esc_html($svc->post_title); ?></h3>
        <div class="svc-meta">
          <div>
            <span class="text-muted"><?php lagos_e('col_price'); ?></span>
            <strong><?php echo esc_html(lagos_money($price)); ?> <small class="text-muted">/<?php lagos_e('cycle_' . $cycle); ?></small></strong>
          </div>
          <div>
            <span class="text-muted"><?php lagos_e('col_next_due'); ?></span>
            <strong><?php echo esc_html(lagos_date($due)); ?></strong>
          </div>
        </div>
        <a class="btn btn-primary btn-block" href="<?php echo esc_url(add_query_arg('view', $svc->ID, lagos_panel_url('servicos'))); ?>"><?php lagos_e('manage'); ?> <span class="arr">&rarr;</span></a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else : ?>
      <div class="card center">
        <p class="text-muted" style="font-size:1.05rem"><?php lagos_e('no_services'); ?></p>
        <a class="btn btn-primary" href="<?php echo esc_url(home_url('/loja/')); ?>"><?php lagos_e('qa_order'); ?> &rarr;</a>
      </div>
    <?php endif; ?>
    <?php
    return ob_get_clean();
});

/** Ícone do serviço conforme o módulo do produto */
function lagos_service_icon($service_id) {
    $product = get_post((int) get_post_meta($service_id, '_lagos_product', true));
    if ($product && function_exists('lagos_module_get')) {
        $mid = get_post_meta($product->ID, '_lagos_module', true);
        $m = lagos_module_get($mid);
        if ($m && function_exists('lagos_module_types')) {
            $types = lagos_module_types();
            return $types[$m['type']]['icon'] ?? 'server';
        }
    }
    return 'server';
}

/** Tela de detalhe/gerenciamento do serviço */
function lagos_service_detail($svc) {
    $uid     = get_current_user_id();
    $st      = get_post_meta($svc->ID, '_lagos_status', true);
    $price   = get_post_meta($svc->ID, '_lagos_price', true);
    $cycle   = get_post_meta($svc->ID, '_lagos_cycle', true);
    $due     = get_post_meta($svc->ID, '_lagos_next_due', true);
    $cancel  = get_post_meta($svc->ID, '_lagos_cancel_request', true);
    $renew   = get_post_meta($svc->ID, '_lagos_autorenew', true);
    if ($renew === '') $renew = '1';
    $product = get_post((int) get_post_meta($svc->ID, '_lagos_product', true));

    // dados do módulo
    $conn_name = '';
    $conn_type = '';
    $remote    = get_post_meta($svc->ID, '_lagos_module_remote', true);
    if (function_exists('lagos_module_get') && $product) {
        $m = lagos_module_get(get_post_meta($product->ID, '_lagos_module', true));
        if ($m) {
            $conn_name = $m['name'];
            $types = function_exists('lagos_module_types') ? lagos_module_types() : [];
            $conn_type = $types[$m['type']]['label'] ?? $m['type'];
        }
    }
    ob_start();
    ?>
    <p class="mb-20"><a class="btn btn-ghost btn-sm" href="<?php echo esc_url(lagos_panel_url('servicos')); ?>"><?php lagos_e('back_services'); ?></a></p>

    <?php echo lagos_flash(); ?>

    <div class="card svc-detail">
      <div class="svc-top">
        <div class="svc-ic ic-violet svc-ic-lg"><?php echo lagos_icon(lagos_service_icon($svc->ID), 26); ?></div>
        <div>
          <h2 style="margin:0 0 6px;font-size:1.35rem"><?php echo esc_html($svc->post_title); ?></h2>
          <div class="svc-badges">
            <?php echo lagos_status_badge($st); ?>
            <?php if (function_exists('lagos_module_badge') && get_post_meta($svc->ID, '_lagos_module_status', true)) echo lagos_module_badge($svc->ID); ?>
            <?php if ($cancel) : ?><span class="badge badge-cancelled"><span class="badge-dot"></span><?php lagos_e('cancel_requested'); ?></span><?php endif; ?>
          </div>
        </div>
      </div>

      <dl class="kv-list" style="margin-top:18px">
        <div><dt><?php lagos_e('col_product'); ?></dt><dd><?php echo esc_html($product ? $product->post_title : $svc->post_title); ?></dd></div>
        <div><dt><?php lagos_e('col_price'); ?></dt><dd><?php echo esc_html(lagos_money($price)); ?> / <?php lagos_e('cycle_' . $cycle); ?></dd></div>
        <div><dt><?php lagos_e('col_next_due'); ?></dt><dd><?php echo esc_html(lagos_date($due)); ?></dd></div>
        <div><dt><?php lagos_e('created_at'); ?></dt><dd><?php echo esc_html(date_i18n('d/m/Y', strtotime($svc->post_date))); ?></dd></div>
        <?php $opts_j = json_decode((string) get_post_meta($svc->ID, '_lagos_opts', true), true);
        if ($opts_j && $product && function_exists('lagos_options_extra')) : $ox = lagos_options_extra($product->ID, $opts_j);
            if ($ox['labels']) : ?><div><dt><?php lagos_e('svc_options'); ?></dt><dd><?php echo esc_html(implode(' · ', $ox['labels'])); ?></dd></div><?php endif;
        endif; ?>
        <?php if ($conn_name) : ?>
        <div><dt><?php lagos_e('connection'); ?></dt><dd><?php echo esc_html($conn_name); ?> (<?php echo esc_html($conn_type); ?>)</dd></div>
        <div><dt><?php lagos_e('remote_id'); ?></dt><dd><code><?php echo esc_html($remote ?: '—'); ?></code></dd></div>
        <?php endif; ?>
      </dl>
    </div>

    <?php if (function_exists('lagos_module_sso_supported') && lagos_module_sso_supported($svc->ID) && $st === 'active') : ?>
    <div class="card">
      <h3 class="card-title"><?php echo lagos_icon('zap', 18); ?> <?php lagos_e('sso_title'); ?></h3>
      <p class="text-muted" style="margin:4px 0 12px"><?php lagos_e('sso_sub'); ?></p>
      <a class="btn btn-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=lagos_sso&service=' . $svc->ID), 'lagos_sso_' . $svc->ID)); ?>"><?php lagos_e('sso_open_panel'); ?> <span class="arr">&rarr;</span></a>
    </div>
    <?php endif; ?>

    <div class="card">
      <h3 class="card-title"><?php echo lagos_icon('refresh', 18); ?> <?php lagos_e('auto_renew'); ?></h3>
      <div class="flex-between">
        <p class="text-muted" style="margin:0"><?php lagos_e('autorenew_hint'); ?></p>
        <form method="post">
          <input type="hidden" name="lagos_action" value="svc_autorenew">
          <input type="hidden" name="service_id" value="<?php echo esc_attr($svc->ID); ?>">
          <?php wp_nonce_field('lagos_svc', 'lagos_nonce'); ?>
          <button type="submit" class="btn btn-sm <?php echo $renew === '1' ? 'btn-primary' : 'btn-ghost'; ?>">
            <?php echo $renew === '1' ? lagos_icon('check', 14) . ' ' . esc_html(lagos_t('autorenew_on')) : esc_html(lagos_t('autorenew_off')); ?>
          </button>
        </form>
      </div>
    </div>

    <div class="card">
      <h3 class="card-title"><?php echo lagos_icon('alert', 18); ?> <?php lagos_e('cancel_service'); ?></h3>
      <?php if ($cancel) : ?>
        <p class="text-muted" style="margin:0"><?php echo lagos_icon('clock', 14); ?> <?php lagos_e('cancel_pending_hint'); ?></p>
      <?php else : ?>
        <p class="text-muted" style="margin-top:0;font-size:.9rem"><?php lagos_e('cancel_hint'); ?></p>
        <form method="post">
          <input type="hidden" name="lagos_action" value="svc_cancel">
          <input type="hidden" name="service_id" value="<?php echo esc_attr($svc->ID); ?>">
          <?php wp_nonce_field('lagos_svc', 'lagos_nonce'); ?>
          <div class="field">
            <label><?php lagos_e('cancel_reason'); ?></label>
            <textarea name="reason" style="min-height:80px"></textarea>
          </div>
          <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('<?php echo esc_attr(lagos_t('cancel_confirm')); ?>')"><?php lagos_e('cancel_send'); ?></button>
        </form>
      <?php endif; ?>
    </div>

    <p style="margin-top:18px"><a class="btn btn-ghost" href="<?php echo esc_url(lagos_panel_url('suporte')); ?>"><?php echo lagos_icon('ticket', 16); ?> <?php lagos_e('qa_ticket'); ?></a></p>
    <?php
    return ob_get_clean();
}

/* =========================================================
   FATURAS (+ Pix demo)
   ========================================================= */
add_shortcode('lagos_invoices', function () {
    if (!is_user_logged_in()) return lagos_login_prompt();
    $uid     = get_current_user_id();
    $invoices = lagos_user_invoices($uid);
    ob_start();
    ?>
    <div class="store-head">
      <h2><?php lagos_e('invoices_title'); ?></h2>
      <p><?php lagos_e('invoices_sub'); ?></p>
    </div>
    <div class="ltable-wrap">
      <table class="ltable">
        <thead><tr>
          <th><?php lagos_e('col_invoice'); ?></th><th><?php lagos_e('col_status'); ?></th>
          <th><?php lagos_e('col_total'); ?></th><th><?php lagos_e('col_due'); ?></th>
          <th><?php lagos_e('col_actions'); ?></th>
        </tr></thead>
        <tbody>
        <?php if ($invoices) : foreach ($invoices as $inv) :
            $st    = get_post_meta($inv->ID, '_lagos_status', true);
            $open  = in_array($st, ['unpaid', 'overdue'], true);
            $pay   = lagos_checkout_url($inv->ID);
        ?>
          <tr>
            <td class="t-strong" data-label="<?php lagos_e('col_invoice'); ?>">#<?php echo esc_html(lagos_invoice_ref($inv->ID)); ?></td>
            <td data-label="<?php lagos_e('col_status'); ?>"><?php echo lagos_status_badge($st); ?></td>
            <td class="t-money" data-label="<?php lagos_e('col_total'); ?>"><?php echo esc_html(lagos_money(get_post_meta($inv->ID, '_lagos_amount', true))); ?></td>
            <td class="t-muted" data-label="<?php lagos_e('col_due'); ?>"><?php echo esc_html(lagos_date(get_post_meta($inv->ID, '_lagos_due', true))); ?></td>
            <td data-label="<?php lagos_e('col_actions'); ?>">
              <?php if ($open) : ?>
              <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a class="btn btn-primary btn-sm" href="<?php echo esc_url($pay); ?>"><?php echo lagos_icon('card', 14); ?> <?php lagos_e('gw_pay'); ?></a>
              </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; else : ?>
          <tr><td colspan="5" class="t-muted"><?php lagos_e('no_invoices'); ?></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
    return ob_get_clean();
});

/** Referência da fatura */
function lagos_invoice_ref($id) {
    return str_pad((string) ($id + 1000), 4, '0', STR_PAD_LEFT);
}

/* =========================================================
   SUPORTE (tickets)
   ========================================================= */
add_shortcode('lagos_support', function () {
    if (!is_user_logged_in()) return lagos_login_prompt();
    $uid     = get_current_user_id();
    $user    = wp_get_current_user();
    $tickets = lagos_user_tickets($uid);
    $depts   = ['general' => 'dept_general', 'billing' => 'dept_billing', 'tech' => 'dept_tech'];
    ob_start();
    ?>
    <div class="store-head">
      <h2><?php lagos_e('support_title'); ?></h2>
      <p><?php lagos_e('support_sub'); ?></p>
    </div>

    <div class="card new-ticket-box">
      <h3 class="card-title"><?php echo lagos_icon('ticket', 18); ?> <?php lagos_e('new_ticket'); ?></h3>
      <form method="post">
        <input type="hidden" name="lagos_action" value="new_ticket">
        <?php wp_nonce_field('lagos_ticket', 'lagos_nonce'); ?>
        <div class="form-grid">
          <div class="field">
            <label><?php lagos_e('label_subject'); ?></label>
            <input type="text" name="subject" required>
          </div>
          <div class="field">
            <label><?php lagos_e('label_department'); ?></label>
            <select name="dept">
              <?php foreach ($depts as $k => $lbl) : ?><option value="<?php echo esc_attr($k); ?>"><?php lagos_e($lbl); ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field">
          <label><?php lagos_e('label_priority'); ?></label>
          <select name="priority">
            <option value="low"><?php lagos_e('prio_low'); ?></option>
            <option value="medium" selected><?php lagos_e('prio_medium'); ?></option>
            <option value="high"><?php lagos_e('prio_high'); ?></option>
          </select>
        </div>
        <div class="field">
          <label><?php lagos_e('label_message'); ?> <em class="text-muted">— <?php lagos_e('open_ticket_hint'); ?></em></label>
          <textarea name="message" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary"><?php lagos_e('btn_send_ticket'); ?></button>
      </form>
    </div>

    <h3 style="font-size:1.05rem;margin:26px 0 14px"><?php lagos_e('my_tickets'); ?></h3>
    <?php if ($tickets) : foreach ($tickets as $t) :
        $st    = get_post_meta($t->ID, '_lagos_status', true) ?: 'open';
        $dept  = get_post_meta($t->ID, '_lagos_dept', true) ?: 'general';
        $prio  = get_post_meta($t->ID, '_lagos_priority', true) ?: 'medium';
        $reps  = get_comments(['post_id' => $t->ID, 'status' => 'approve', 'order' => 'ASC']);
    ?>
    <details class="ticket">
      <summary>
        <?php echo lagos_status_badge($st); ?>
        <span class="ticket-subj"><?php echo esc_html($t->post_title); ?></span>
        <span class="ticket-meta">
          <span><?php lagos_e($depts[$dept] ?? 'dept_general'); ?></span>
          <span>·</span>
          <span><?php lagos_e('col_priority'); ?>: <?php lagos_e('prio_' . $prio); ?></span>
        </span>
        <?php echo lagos_icon('chevron', 16); ?>
      </summary>
      <div class="ticket-body">
        <?php if ($st === 'closed' && function_exists('lagos_ticket_rating_html')) : ?>
          <div class="mb-20" style="padding:10px 14px;background:var(--slate-bg);border-radius:10px"><?php echo lagos_ticket_rating_html($t->ID); ?></div>
        <?php endif; ?>
        <div class="thread">
          <div class="msg msg-you">
            <div class="msg-meta"><?php echo esc_html($user->display_name); ?> · <?php echo esc_html(get_the_date('d/m/Y H:i', $t)); ?></div>
            <?php echo wpautop(esc_html($t->post_content)); ?>
          </div>
          <?php foreach ($reps as $r) : $staff = user_can($r->user_id, 'manage_options'); ?>
          <div class="msg <?php echo $staff ? 'msg-staff' : 'msg-you'; ?>">
            <div class="msg-meta"><?php echo $staff ? esc_html(lagos_t('staff')) : esc_html(lagos_t('you')); ?> · <?php echo esc_html(get_comment_date('d/m/Y H:i', $r)); ?></div>
            <?php echo wpautop(esc_html($r->comment_content)); ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if ($st !== 'closed') : ?>
        <form method="post">
          <input type="hidden" name="lagos_action" value="ticket_reply">
          <input type="hidden" name="ticket_id" value="<?php echo esc_attr($t->ID); ?>">
          <?php wp_nonce_field('lagos_ticket', 'lagos_nonce'); ?>
          <div class="field">
            <textarea name="message" required style="min-height:90px"></textarea>
          </div>
          <button type="submit" class="btn btn-primary btn-sm"><?php lagos_e('reply'); ?></button>
        </form>
        <?php endif; ?>
      </div>
    </details>
    <?php endforeach; else : ?>
      <p class="text-muted"><?php lagos_e('no_tickets'); ?></p>
    <?php endif; ?>
    <?php
    return ob_get_clean();
});

/* =========================================================
   LOJA
   ========================================================= */
add_shortcode('lagos_store', function () {
    $cat  = isset($_GET['cat']) ? sanitize_title($_GET['cat']) : '';
    $args = ['limit' => -1];
    if ($cat) $args['cat'] = $cat;
    $products = lagos_get_products($args);
    $terms    = get_terms(['taxonomy' => 'lagos_cat', 'hide_empty' => false]);
    $cats     = [];
    if (!is_wp_error($terms)) {
        foreach ($terms as $tm) $cats[$tm->slug] = $tm->name;
    }
    ob_start();
    ?>
    <div class="store-head">
      <h2><?php lagos_e('store_title'); ?></h2>
      <p><?php lagos_e('store_sub'); ?></p>
    </div>

    <div class="cat-chips">
      <a class="cat-chip<?php echo !$cat ? ' is-active' : ''; ?>" href="<?php echo esc_url(home_url('/loja/')); ?>"><?php lagos_e('all_categories'); ?></a>
      <?php foreach ($cats as $slug => $name) : ?>
        <a class="cat-chip<?php echo $cat === $slug ? ' is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('cat', $slug, home_url('/loja/'))); ?>"><?php echo esc_html($name); ?></a>
      <?php endforeach; ?>
    </div>

    <?php if ($products) : ?>
    <div class="store-grid">
      <?php foreach ($products as $p) :
          $price = get_post_meta($p->ID, '_lagos_price', true);
          $cycle = get_post_meta($p->ID, '_lagos_cycle', true) ?: 'monthly';
          $feat  = array_slice(lagos_features($p->ID), 0, 5);
          $terms_p = get_the_terms($p->ID, 'lagos_cat');
          $has_opts = function_exists('lagos_product_options') && lagos_product_options($p->ID);
          $url   = is_user_logged_in() && !$has_opts ? lagos_order_url($p->ID) : get_permalink($p->ID);
      ?>
      <div class="product-card">
        <div class="p-chip-row">
          <?php if ($terms_p && !is_wp_error($terms_p)) foreach ($terms_p as $tp) : ?>
            <span class="chip chip-cat" style="background:#F1EAFE;color:#7C3AED;border:none"><?php echo esc_html($tp->name); ?></span>
          <?php endforeach; ?>
        </div>
        <h3><a href="<?php echo esc_url(get_permalink($p->ID)); ?>" style="color:inherit"><?php echo esc_html($p->post_title); ?></a></h3>
        <p class="p-tag"><?php echo esc_html(get_the_excerpt($p)); ?></p>
        <div class="product-price">
          <small><?php lagos_e('from'); ?></small>
          <strong><?php echo esc_html(lagos_money($price)); ?></strong>
          <span><?php lagos_e($cycle === 'yearly' ? 'year' : ($cycle === 'one_time' ? 'one_time' : 'month')); ?></span>
        </div>
        <?php if ($feat) : ?>
        <ul class="p-feats">
          <?php foreach ($feat as $f) : ?><li><?php echo lagos_icon('check', 14); ?> <?php echo esc_html($f); ?></li><?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <div style="display:grid;gap:8px">
          <?php if (!empty($has_opts)) : ?>
          <a class="btn btn-primary btn-block" href="<?php echo esc_url(get_permalink($p->ID)); ?>"><?php lagos_e('opt_choose'); ?> <span class="arr">&rarr;</span></a>
          <?php else : ?>
          <a class="btn btn-primary btn-block" href="<?php echo esc_url(lagos_cart_add_url($p->ID)); ?>"><?php echo lagos_icon('cart', 15); ?> <?php lagos_e('add_to_cart'); ?></a>
          <?php endif; ?>
          <a class="btn btn-ghost btn-block btn-sm" href="<?php echo esc_url($url); ?>"><?php lagos_e('buy_now'); ?> →</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else : ?>
      <p class="text-muted"><?php lagos_e('no_data'); ?></p>
    <?php endif; ?>
    <?php
    return ob_get_clean();
});

/* =========================================================
   PERFIL
   ========================================================= */
add_shortcode('lagos_profile', function () {
    if (!is_user_logged_in()) return lagos_login_prompt();
    $uid   = get_current_user_id();
    $user  = wp_get_current_user();
    $key   = lagos_api_key($uid);
    $since = date_i18n('d/m/Y', strtotime($user->user_registered));
    $initial = strtoupper(mb_substr(trim($user->display_name), 0, 1));
    $avatar  = get_avatar($uid, 72, 'identicon', '', ['force_display' => true]);
    ob_start();
    ?>
    <div class="store-head">
      <h2><?php lagos_e('profile_title'); ?></h2>
      <p><?php lagos_e('profile_sub'); ?></p>
    </div>

    <div class="profile-grid">
      <div>
        <div class="card">
          <h3 class="card-title"><?php echo lagos_icon('user', 18); ?> <?php lagos_e('account_info'); ?></h3>
          <div class="profile-hero mb-20">
            <div class="avatar avatar-lg" style="overflow:hidden"><?php echo $avatar; ?></div>
            <div>
              <strong style="font-size:1.05rem"><?php echo esc_html($user->display_name); ?></strong>
              <div class="text-muted" style="font-size:.88rem"><?php echo esc_html($user->user_email); ?></div>
            </div>
          </div>
          <dl class="kv-list">
            <div><dt><?php lagos_e('label_email'); ?></dt><dd><?php echo esc_html($user->user_email); ?></dd></div>
            <div><dt><?php lagos_e('member_since'); ?></dt><dd><?php echo esc_html($since); ?></dd></div>
            <div><dt><?php lagos_e('st_balance'); ?></dt><dd style="color:#16A34A"><?php echo esc_html(lagos_money(lagos_balance($uid))); ?></dd></div>
          </dl>
          <form method="post" style="margin-top:16px">
            <input type="hidden" name="lagos_action" value="deposit">
            <?php wp_nonce_field('lagos_profile', 'lagos_nonce'); ?>
            <label style="font-size:.84rem;font-weight:600;color:var(--muted);display:block;margin-bottom:6px"><?php lagos_e('deposit_title'); ?></label>
            <div class="flex-between">
              <input type="number" name="amount" step="0.01" min="10" placeholder="<?php echo esc_attr(lagos_t('deposit_ph')); ?>" required style="width:150px;padding:9px 12px;border:1px solid var(--line);border-radius:9px">
              <button type="submit" class="btn btn-primary btn-sm"><?php echo lagos_icon('wallet', 15); ?> <?php lagos_e('deposit_btn'); ?></button>
            </div>
            <p class="text-muted" style="margin:8px 0 0;font-size:.8rem"><?php lagos_e('deposit_hint'); ?></p>
          </form>
        </div>

        <div class="card">
          <h3 class="card-title"><?php echo lagos_icon('globe', 18); ?> <?php lagos_e('lang_pref'); ?></h3>
          <p class="text-muted" style="margin-top:0"><?php lagos_e('lang_sub'); ?></p>
          <div style="display:flex;gap:8px">
            <a class="btn <?php echo lagos_current_lang() === 'pt' ? 'btn-primary' : 'btn-ghost'; ?> btn-sm" href="<?php echo esc_url(add_query_arg('lang', 'pt')); ?>"> Português</a>
            <a class="btn <?php echo lagos_current_lang() === 'en' ? 'btn-primary' : 'btn-ghost'; ?> btn-sm" href="<?php echo esc_url(add_query_arg('lang', 'en')); ?>"> English</a>
          </div>
        </div>
      </div>

      <div>
        <div class="card">
          <h3 class="card-title"><?php echo lagos_icon('shield', 18); ?> <?php lagos_e('change_pass'); ?></h3>
          <form method="post">
            <input type="hidden" name="lagos_action" value="profile_pass">
            <?php wp_nonce_field('lagos_profile', 'lagos_nonce'); ?>
            <div class="field">
              <label><?php lagos_e('label_current_pass'); ?></label>
              <input type="password" name="current" required autocomplete="current-password">
            </div>
            <div class="field">
              <label><?php lagos_e('label_new_pass'); ?></label>
              <input type="password" name="new" required minlength="6" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><?php lagos_e('btn_save'); ?></button>
          </form>
        </div>

        <div class="card">
          <h3 class="card-title"><?php echo lagos_icon('api', 18); ?> <?php lagos_e('api_key'); ?></h3>
          <p class="text-muted" style="margin-top:0"><?php lagos_e('api_key_sub'); ?></p>
          <div class="api-key-box mb-20">
            <code id="lagosApiKey"><?php echo esc_html($key); ?></code>
          </div>
          <p class="text-muted" style="margin:-8px 0 14px;font-size:.8rem;font-family:ui-monospace,Menlo,monospace"><?php lagos_e('api_hint'); ?></p>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button class="btn btn-ghost btn-sm" data-copy="<?php echo esc_attr($key); ?>" data-copied="<?php esc_attr_e('Copiado!'); ?>"><?php lagos_e('btn_copy'); ?></button>
          </div>
          <form method="post" style="margin-top:10px">
            <input type="hidden" name="lagos_action" value="api_regen">
            <?php wp_nonce_field('lagos_profile', 'lagos_nonce'); ?>
            <button type="submit" class="btn btn-danger btn-sm"><?php lagos_e('btn_regen'); ?></button>
          </form>
        </div>

        <?php if (function_exists('lagos_security_profile_section')) lagos_security_profile_section(); ?>
      </div>
    </div>
    <?php
    return ob_get_clean();
});

/* =========================================================
   HANDLERS (GET: pedido/pagamento · POST: ticket/resposta/senha/api)
   ========================================================= */
add_action('template_redirect', function () {
    // ---------- PEDIDO DE PRODUTO ----------
    if (isset($_GET['lagos_order'])) {
        $pid = absint($_GET['lagos_order']);
        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/entrar/?redirect_to=' . rawurlencode(get_permalink($pid))));
            exit;
        }
        check_admin_referer('lagos_order_' . $pid, 'lagos_nonce');
        $product = get_post($pid);
        if (!$product || $product->post_type !== 'lagos_product') return;

        $uid    = get_current_user_id();
        $price  = (float) get_post_meta($pid, '_lagos_price', true);
        $cycle  = get_post_meta($pid, '_lagos_cycle', true) ?: 'monthly';

        // opções configuráveis (v0.10)
        $opts   = function_exists('lagos_options_sanitize') && !empty($_GET['lagos_opt']) ? lagos_options_sanitize($pid, (array) wp_unslash($_GET['lagos_opt'])) : [];
        $oextra = $opts && function_exists('lagos_options_extra') ? lagos_options_extra($pid, $opts) : ['extra' => 0.0, 'labels' => []];
        $price += $oextra['extra'];
        $otitle = $oextra['labels'] ? ' (' . implode(', ', $oextra['labels']) . ')' : '';

        // serviço pendente
        $sid = wp_insert_post([
            'post_type'   => 'lagos_service',
            'post_status' => 'publish',
            'post_title'  => $product->post_title,
        ]);
        update_post_meta($sid, '_lagos_user', $uid);
        update_post_meta($sid, '_lagos_product', $pid);
        update_post_meta($sid, '_lagos_status', 'pending');
        update_post_meta($sid, '_lagos_price', $price);
        update_post_meta($sid, '_lagos_cycle', $cycle);
        update_post_meta($sid, '_lagos_next_due', date('Y-m-d', strtotime('+' . ($cycle === 'yearly' ? '1 year' : '30 days'))));
        update_post_meta($sid, '_lagos_opts', $opts ? wp_json_encode($opts, JSON_UNESCAPED_UNICODE) : '');

        // fatura
        $iid = wp_insert_post([
            'post_type'   => 'lagos_invoice',
            'post_status' => 'publish',
            'post_title'  => 'FAT-' . lagos_invoice_ref($sid),
        ]);
        update_post_meta($iid, '_lagos_user', $uid);
        update_post_meta($iid, '_lagos_amount', $price);
        update_post_meta($iid, '_lagos_status', 'unpaid');
        update_post_meta($iid, '_lagos_due', date('Y-m-d', strtotime('+5 days')));
        update_post_meta($iid, '_lagos_items', $product->post_title . $otitle . ' | 1 | ' . number_format($price, 2, ',', '.'));
        update_post_meta($iid, '_lagos_service', $sid);
        if (function_exists('lagos_mail_invoice')) lagos_mail_invoice($iid);

        wp_safe_redirect(lagos_panel_url('faturas') . '?lagos_msg=order_success');
        exit;
    }

    // PAGAMENTO: acontece exclusivamente em /pagamento/ (gateways verificados)
    // ou via webhook assinado. O antigo ?lagos_pay= (marcava como pago sem
    // cobrança) foi REMOVIDO — bypass de pagamento inaceitável em produção.

    // ---------- POSTs ----------
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['lagos_action'])) return;
    $action = sanitize_key($_POST['lagos_action']);
    if (!is_user_logged_in()) return;
    $uid = get_current_user_id();

    // novo ticket
    if ($action === 'new_ticket') {
        if (!isset($_POST['lagos_nonce']) || !wp_verify_nonce($_POST['lagos_nonce'], 'lagos_ticket')) return;
        $subject = sanitize_text_field(wp_unslash($_POST['subject'] ?? ''));
        $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
        if (!$subject || !$message) {
            wp_safe_redirect(add_query_arg('lagos_err', 'err_required', lagos_panel_url('suporte')));
            exit;
        }
        $tid = wp_insert_post([
            'post_type'    => 'lagos_ticket',
            'post_status'  => 'publish',
            'post_title'   => $subject,
            'post_content' => $message,
        ]);
        update_post_meta($tid, '_lagos_user', $uid);
        update_post_meta($tid, '_lagos_status', 'open');
        update_post_meta($tid, '_lagos_dept', sanitize_key($_POST['dept'] ?? 'general'));
        update_post_meta($tid, '_lagos_priority', sanitize_key($_POST['priority'] ?? 'medium'));
        if (function_exists('lagos_mail_ticket')) lagos_mail_ticket($tid, wp_get_current_user());
        wp_safe_redirect(add_query_arg('lagos_msg', 'ticket_created', lagos_panel_url('suporte')));
        exit;
    }

    // resposta de ticket
    if ($action === 'ticket_reply') {
        if (!isset($_POST['lagos_nonce']) || !wp_verify_nonce($_POST['lagos_nonce'], 'lagos_ticket')) return;
        $tid = absint($_POST['ticket_id'] ?? 0);
        $msg = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
        $ticket = get_post($tid);
        if ($msg && $ticket && $ticket->post_type === 'lagos_ticket' && (int) get_post_meta($tid, '_lagos_user', true) === $uid) {
            wp_insert_comment([
                'comment_post_ID'      => $tid,
                'comment_content'      => $msg,
                'comment_approved'     => 1,
                'user_id'              => $uid,
                'comment_author'       => wp_get_current_user()->display_name,
                'comment_author_email' => wp_get_current_user()->user_email,
                'comment_date'         => current_time('mysql'),
            ]);
            update_post_meta($tid, '_lagos_status', 'customer_reply');
            wp_safe_redirect(add_query_arg('lagos_msg', 'reply_sent', lagos_panel_url('suporte')));
            exit;
        }
    }

    // renovacao automatica (toggle)
    if ($action === 'svc_autorenew') {
        if (!isset($_POST['lagos_nonce']) || !wp_verify_nonce($_POST['lagos_nonce'], 'lagos_svc')) return;
        $sid = absint($_POST['service_id'] ?? 0);
        $svc = get_post($sid);
        if ($svc && $svc->post_type === 'lagos_service' && (int) get_post_meta($sid, '_lagos_user', true) === $uid) {
            $cur = get_post_meta($sid, '_lagos_autorenew', true);
            update_post_meta($sid, '_lagos_autorenew', $cur === '1' ? '0' : '1');
            wp_safe_redirect(add_query_arg(['view' => $sid, 'lagos_msg' => 'saved_ok'], lagos_panel_url('servicos')));
            exit;
        }
    }

    // solicitacao de cancelamento
    if ($action === 'svc_cancel') {
        if (!isset($_POST['lagos_nonce']) || !wp_verify_nonce($_POST['lagos_nonce'], 'lagos_svc')) return;
        $sid = absint($_POST['service_id'] ?? 0);
        $reason = sanitize_textarea_field(wp_unslash($_POST['reason'] ?? ''));
        $svc = get_post($sid);
        if ($svc && $svc->post_type === 'lagos_service' && (int) get_post_meta($sid, '_lagos_user', true) === $uid) {
            update_post_meta($sid, '_lagos_cancel_request', current_time('mysql'));
            update_post_meta($sid, '_lagos_cancel_reason', $reason);
            $tid = wp_insert_post([
                'post_type' => 'lagos_ticket', 'post_status' => 'publish',
                'post_title' => 'Cancelamento: ' . $svc->post_title,
                'post_content' => 'Solicitação de cancelamento do serviço "' . $svc->post_title . '"' . ($reason ? "

Motivo: " . $reason : ''),
            ]);
            if ($tid && !is_wp_error($tid)) {
                update_post_meta($tid, '_lagos_user', $uid);
                update_post_meta($tid, '_lagos_status', 'open');
                update_post_meta($tid, '_lagos_dept', 'billing');
                update_post_meta($tid, '_lagos_priority', 'medium');
                update_post_meta($tid, '_lagos_service_ref', $sid);
            }
            wp_safe_redirect(add_query_arg(['view' => $sid, 'lagos_msg' => 'cancel_sent'], lagos_panel_url('servicos')));
            exit;
        }
    }

    // recarga de saldo
    if ($action === 'deposit') {
        if (!isset($_POST['lagos_nonce']) || !wp_verify_nonce($_POST['lagos_nonce'], 'lagos_profile')) return;
        $amount = round((float) ($_POST['amount'] ?? 0), 2);
        if ($amount < 10) {
            wp_safe_redirect(add_query_arg('lagos_err', 'err_min_deposit', lagos_panel_url('perfil')));
            exit;
        }
        $iid = wp_insert_post([
            'post_type' => 'lagos_invoice', 'post_status' => 'publish',
            'post_title' => 'REC-' . strtoupper(wp_generate_password(6, false, false)),
        ]);
        update_post_meta($iid, '_lagos_user', $uid);
        update_post_meta($iid, '_lagos_amount', $amount);
        update_post_meta($iid, '_lagos_status', 'unpaid');
        update_post_meta($iid, '_lagos_due', date('Y-m-d', strtotime('+5 days')));
        update_post_meta($iid, '_lagos_items', 'Recarga de saldo | 1 | ' . number_format($amount, 2, ',', '.'));
        update_post_meta($iid, '_lagos_deposit', '1');
        if (function_exists('lagos_mail_invoice')) lagos_mail_invoice($iid);
        wp_safe_redirect(add_query_arg('lagos_msg', 'deposit_created', lagos_panel_url('faturas')));
        exit;
    }

    // alterar senha
    if ($action === 'profile_pass') {
        if (!isset($_POST['lagos_nonce']) || !wp_verify_nonce($_POST['lagos_nonce'], 'lagos_profile')) return;
        $user = get_userdata($uid);
        $cur  = (string) ($_POST['current'] ?? '');
        $new  = (string) ($_POST['new'] ?? '');
        if (!wp_check_password($cur, $user->user_pass, $uid)) {
            wp_safe_redirect(add_query_arg('lagos_err', 'wrong_pass', lagos_panel_url('perfil')));
            exit;
        }
        if (strlen($new) >= 6) {
            wp_update_user(['ID' => $uid, 'user_pass' => $new]);
            lagos_audit('password_change', 'senha alterada no perfil');
            wp_safe_redirect(add_query_arg('lagos_msg', 'saved_ok', lagos_panel_url('perfil')));
            exit;
        }
        wp_safe_redirect(add_query_arg('lagos_err', 'err_short', lagos_panel_url('perfil')));
        exit;
    }

    // regenerar chave de API
    if ($action === 'api_regen') {
        if (!isset($_POST['lagos_nonce']) || !wp_verify_nonce($_POST['lagos_nonce'], 'lagos_profile')) return;
        lagos_api_key($uid, true);
        lagos_audit('api_key', 'chave de API regenerada');
        wp_safe_redirect(add_query_arg('lagos_msg', 'regen_ok', lagos_panel_url('perfil')));
        exit;
    }
});

/** Staff respondeu (comentário no admin) → status answered */
add_action('comment_post', function ($comment_id) {
    $comment = get_comment($comment_id);
    if (!$comment) return;
    $post = get_post($comment->comment_post_ID);
    if ($post && $post->post_type === 'lagos_ticket' && user_can($comment->user_id, 'manage_options')) {
        update_post_meta($post->ID, '_lagos_status', 'answered');
    }
});

/** Logo no formulário de auth (usa o do tema se existir) */
function lagospanel_logo_link() {
    if (function_exists('lagospanel_logo')) {
        return '<a href="' . esc_url(home_url('/')) . '">' . lagospanel_logo(46) . '</a>';
    }
    return '<a href="' . esc_url(home_url('/')) . '" style="font-weight:800;font-size:1.3rem">Lagos<b>Panel</b></a>';
}

/* =========================================================
   BASE DE CONHECIMENTO
   ========================================================= */
add_shortcode('lagos_kb', function () {
    $q   = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
    $cat = isset($_GET['cat']) ? sanitize_title($_GET['cat']) : '';

    $args = ['post_type' => 'lagos_kb', 'numberposts' => -1, 'post_status' => 'publish', 'orderby' => 'menu_order title', 'order' => 'ASC'];
    if ($q)   $args['s'] = $q;
    if ($cat) $args['tax_query'] = [['taxonomy' => 'lagos_kbcat', 'field' => 'slug', 'terms' => $cat]];
    $posts = get_posts($args);

    $terms = get_terms(['taxonomy' => 'lagos_kbcat', 'hide_empty' => false]);
    $cats  = [];
    if (!is_wp_error($terms)) {
        foreach ($terms as $t) $cats[$t->slug] = $t->name;
    }
    $base = home_url('/base-de-conhecimento/');
    ob_start();
    ?>
    <div class="store-head">
      <h2><?php lagos_e('kb_title'); ?></h2>
      <p><?php lagos_e('kb_sub'); ?></p>
    </div>

    <form method="get" action="<?php echo esc_url($base); ?>" class="kb-search">
      <div class="flex-between">
        <input type="text" name="q" value="<?php echo esc_attr($q); ?>" placeholder="<?php echo esc_attr(lagos_t('kb_search')); ?>" style="flex:1;max-width:420px;padding:11px 16px;border:1px solid var(--line);border-radius:11px">
        <button type="submit" class="btn btn-primary"><?php echo lagos_icon('send', 15); ?> <?php lagos_e('kb_search_btn'); ?></button>
      </div>
    </form>

    <?php if ($cats) : ?>
    <div class="cat-chips">
      <a class="cat-chip<?php echo !$cat ? ' is-active' : ''; ?>" href="<?php echo esc_url($base); ?>"><?php lagos_e('all_categories'); ?></a>
      <?php foreach ($cats as $slug => $name) : ?>
        <a class="cat-chip<?php echo $cat === $slug ? ' is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('cat', $slug, $base)); ?>"><?php echo esc_html($name); ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($posts) : foreach ($posts as $a) :
        $terms_a = get_the_terms($a->ID, 'lagos_kbcat');
    ?>
    <details class="ticket kb-article">
      <summary>
        <span class="kb-ic"><?php echo lagos_icon('book', 17); ?></span>
        <span class="ticket-subj"><?php echo esc_html($a->post_title); ?></span>
        <span class="ticket-meta">
          <?php if ($terms_a && !is_wp_error($terms_a)) : ?><span class="chip chip-cat" style="background:#F1EAFE;color:#7C3AED;border:none"><?php echo esc_html($terms_a[0]->name); ?></span><?php endif; ?>
        </span>
        <?php echo lagos_icon('chevron', 16); ?>
      </summary>
      <div class="ticket-body post-body"><?php echo apply_filters('the_content', $a->post_content); ?></div>
    </details>
    <?php endforeach; else : ?>
      <p class="text-muted"><?php lagos_e('kb_empty'); ?></p>
    <?php endif; ?>
    <?php
    return ob_get_clean();
});
