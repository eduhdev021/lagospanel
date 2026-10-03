<?php
/**
 * LagosPanel Core — Sistema de Gateways de Pagamento (estilo Paymenter) — v0.6
 *
 * Tipos aceitos (os mesmos do Paymenter):
 *   Cartão  → Stripe (Checkout Session)
 *   Wallet  → PayPal (Orders v2)
 *   Europa  → Mollie (Payments API)
 *   Pix/BR  → Mercado Pago (Pix QR + Checkout Pro)
 *   Cripto  → Coinbase Commerce (Charges)
 *   Manual  → Transferência bancária (instruções)
 *   Demo    → Pix simulação (QR)
 *
 * Extensível: registre novos gateways via filter 'lagos_gateways'
 * (add_filter('lagos_gateways', fn($gws) => $gws + ['meu-gw' => new Meu_Gateway()]))
 * — igual às extensões do marketplace do Paymenter.
 */
if (!defined('ABSPATH')) exit;

/* ══════════════════════════════════════════════════════════
   BASE — classe abstrata de gateway
   ══════════════════════════════════════════════════════════ */
abstract class Lagos_Gateway {
    public $id = '', $name = '', $desc = '', $icon = 'card', $methods = [], $color = '#7C3AED';
    public $has_sandbox = true;   // gateway real com modo teste
    public $is_virtual  = false;  // apenas simulação local (Demo)

    /** Campos de configuração no admin: [['key','label','type','placeholder','desc']] */
    public function fields() { return []; }

    /**
     * Inicia um pagamento (modo REAL). Recebe: invoice (WP_Post), amount, cfg (config do gateway).
     * Retorna: ['redirect'=>url] | ['qr'=>['code'=>..., 'b64'=>...]] | ['instructions'=>html] | ['error'=>msg]
     */
    public function pay($invoice, $amount, $cfg) { return ['error' => 'not_implemented']; }

    /** Valida webhook e retorna o ID da fatura a marcar como paga (0 = inválido). */
    public function verify_webhook($cfg, $body, $headers, $params) { return 0; }

    public function cfg($cfg, $key, $def = '') { return $cfg['settings'][$key] ?? $def; }
}

/* ══════════════════════════════════════════════════════════
   REGISTRO + CONFIGURAÇÃO
   ══════════════════════════════════════════════════════════ */
function lagos_gateways() {
    static $gws = null;
    if ($gws !== null) return $gws;

    foreach (glob(LAGOS_CORE_DIR . 'includes/gateways/class-lagos-gateway-*.php') as $f) require_once $f;

    $gws = [
        'pix'          => new Lagos_Gateway_PixDemo(),
        'mercadopago'  => new Lagos_Gateway_MercadoPago(),
        'pagseguro'    => new Lagos_Gateway_PagSeguro(),
        'gerencianet'  => new Lagos_Gateway_Gerencianet(),
        'stripe'       => new Lagos_Gateway_Stripe(),
        'paypal'       => new Lagos_Gateway_PayPal(),
        'mollie'       => new Lagos_Gateway_Mollie(),
        'coinbase'     => new Lagos_Gateway_Coinbase(),
        'manual'       => new Lagos_Gateway_Manual(),
    ];

    // extensibilidade — registre outros gateways por aqui
    return apply_filters('lagos_gateways', $gws);
}

/** Config padrão (primeira execução): Pix demo + Mercado Pago/Stripe em teste + Manual ativos */
function lagos_gateways_defaults() {
    return [
        'pix'         => ['enabled' => 1, 'sandbox' => 1, 'order' => 1, 'settings' => []],
        'mercadopago' => ['enabled' => 1, 'sandbox' => 1, 'order' => 2, 'settings' => []],
        'stripe'      => ['enabled' => 1, 'sandbox' => 1, 'order' => 3, 'settings' => []],
        'paypal'      => ['enabled' => 0, 'sandbox' => 1, 'order' => 4, 'settings' => []],
        'mollie'      => ['enabled' => 0, 'sandbox' => 1, 'order' => 5, 'settings' => []],
        'coinbase'    => ['enabled' => 0, 'sandbox' => 1, 'order' => 6, 'settings' => []],
        'pagseguro'   => ['enabled' => 1, 'sandbox' => 1, 'order' => 7, 'settings' => []],
        'gerencianet' => ['enabled' => 1, 'sandbox' => 1, 'order' => 8, 'settings' => []],
        'manual'      => ['enabled' => 0, 'sandbox' => 0, 'order' => 9, 'settings' => ['instructions' => "Banco: Lagos Bank\nAgência: 0001\nConta: 123456-7\nCNPJ: 00.000.000/0001-00\n\nEnvie o comprovante para financeiro@lagos.com.br informando o número da fatura."]],
    ];
}

function lagos_gateways_config() {
    $cfg = get_option('lagos_gateways_config', null);
    if (!is_array($cfg)) {
        $cfg = lagos_gateways_defaults();
        update_option('lagos_gateways_config', $cfg);
    }
    // garante chaves novas
    foreach (lagos_gateways_defaults() as $id => $def) {
        if (!isset($cfg[$id])) $cfg[$id] = $def;
    }
    return $cfg;
}

function lagos_gateway_config($id) {
    $cfg = lagos_gateways_config();
    return $cfg[$id] ?? ['enabled' => 0, 'sandbox' => 1, 'order' => 99, 'settings' => []];
}

/** Modo do painel: 'demo' (padrão) ou 'production' */
function lagos_panel_mode() {
    return get_option('lagos_mode', 'demo') === 'production' ? 'production' : 'demo';
}

/** Gateways ativos ordenados */
function lagos_gateways_active() {
    $out = [];
    $production = lagos_panel_mode() === 'production';
    foreach (lagos_gateways_config() as $id => $cfg) {
        if (empty($cfg['enabled'])) continue;
        // em produção, gateways virtuais (QR fake) nunca aparecem no checkout
        if ($production) {
            $gw0 = lagos_gateways()[$id] ?? null;
            if ($gw0 && $gw0->is_virtual) continue;
        }
        $gw = lagos_gateways()[$id] ?? null;
        if ($gw) $out[$id] = $gw;
    }
    uasort($out, function ($a, $b) use ($cfg) {
        return (lagos_gateway_config($a->id)['order'] ?? 99) <=> (lagos_gateway_config($b->id)['order'] ?? 99);
    });
    return $out;
}

/** URL do webhook do gateway (para cadastrar no painel do provedor) */
function lagos_gateway_webhook_url($id) {
    return rest_url('lagos/v1/gateways/' . $id . '/webhook');
}

/** URL de checkout de uma fatura */
function lagos_checkout_url($invoice_id) {
    return wp_nonce_url(home_url('/pagamento/') . '?invoice=' . $invoice_id, 'lagos_pay_' . $invoice_id, 'lagos_nonce');
}

/* ══════════════════════════════════════════════════════════
   HTTP helper (curl nativo — tolerante a conexões fechadas
   sem marcador de fim, como no RDAP da Verisign)
   ══════════════════════════════════════════════════════════ */
function lagos_gw_http($method, $url, $args = []) {
    if (!function_exists('curl_init')) {
        $resp = wp_remote_request($url, ['method' => $method, 'timeout' => 20, 'headers' => $args['headers'] ?? [], 'body' => $args['json'] ?? ($args['form'] ?? null)]);
        if (is_wp_error($resp)) return ['code' => 0, 'body' => '', 'json' => null];
        return ['code' => (int) wp_remote_retrieve_response_code($resp), 'body' => wp_remote_retrieve_body($resp), 'json' => json_decode(wp_remote_retrieve_body($resp), true)];
    }
    $ch = curl_init($url);
    $headers = $args['headers'] ?? [];
    if (!empty($args['bearer'])) $headers[] = 'Authorization: Bearer ' . $args['bearer'];
    if (!empty($args['basic']))  $headers[] = 'Authorization: Basic ' . base64_encode($args['basic']);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $args['timeout'] ?? 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'LagosPanel/0.6 (+https://lagossolucoes.com.br)',
    ];
    if (strcasecmp($method, 'POST') === 0) {
        $opts[CURLOPT_POST] = true;
        if (isset($args['form'])) {
            $opts[CURLOPT_POSTFIELDS] = http_build_query($args['form']);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        } elseif (isset($args['json'])) {
            $opts[CURLOPT_POSTFIELDS] = wp_json_encode($args['json']);
            $headers[] = 'Content-Type: application/json';
        } else {
            $opts[CURLOPT_POSTFIELDS] = $args['body'] ?? '';
        }
    }
    if (!empty($args['cert_p12'])) {
        $opts[CURLOPT_SSLCERT]     = $args['cert_p12'];
        $opts[CURLOPT_SSLCERTTYPE] = 'P12';
        $opts[CURLOPT_SSLCERTPASSWD] = $args['cert_pass'] ?? '';
    }
    if ($headers) $opts[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts);
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($code === 0 && $err) return ['code' => 0, 'body' => (string) $raw, 'json' => null, 'error' => $err];
    return ['code' => $code, 'body' => (string) $raw, 'json' => json_decode((string) $raw, true)];
}

/* ══════════════════════════════════════════════════════════
   PAGAMENTO CENTRALIZADO (idempotente)
   ══════════════════════════════════════════════════════════ */
function lagos_invoice_mark_paid($iid) {
    $iid = absint($iid);
    $inv = get_post($iid);
    if (!$inv || $inv->post_type !== 'lagos_invoice') return false;
    if (get_post_meta($iid, '_lagos_status', true) === 'paid') return true;

    update_post_meta($iid, '_lagos_status', 'paid');
    update_post_meta($iid, '_lagos_paid_at', current_time('mysql'));
    $uid = (int) get_post_meta($iid, '_lagos_user', true);
    lagos_audit('payment', 'fatura #' . $iid . ' (R$ ' . get_post_meta($iid, '_lagos_amount', true) . ') marcada como paga', $uid);
    if (function_exists('lagos_webhook_send')) lagos_webhook_send('invoice.paid', ['invoice' => $iid, 'ref' => lagos_invoice_ref($iid), 'amount' => (float) get_post_meta($iid, '_lagos_amount', true), 'currency' => 'BRL', 'deposit' => get_post_meta($iid, '_lagos_deposit', true) === '1', 'user' => $uid]);

    // recarga de saldo → credita na carteira
    if (get_post_meta($iid, '_lagos_deposit', true) === '1') {
        $amount = (float) get_post_meta($iid, '_lagos_amount', true);
        update_user_meta($uid, 'lagos_balance', lagos_balance($uid) + $amount);
        if (function_exists('lagos_mail_deposit')) lagos_mail_deposit($iid, $amount);
        return true;
    }

    // ativa todos os serviços vinculados (checkout simples ou carrinho)
    $sids = [];
    $single = (int) get_post_meta($iid, '_lagos_service', true);
    if ($single) $sids[] = $single;
    $multi = get_post_meta($iid, '_lagos_services', true);
    if ($multi) $sids = array_merge($sids, array_map('intval', explode(',', $multi)));
    foreach (array_unique(array_filter($sids)) as $s) {
        update_post_meta($s, '_lagos_status', 'active');
        update_post_meta($s, '_lagos_next_due', date('Y-m-d', strtotime('+30 days')));
        if (function_exists('lagos_webhook_send')) lagos_webhook_send('service.activated', ['service' => $s, 'user' => $uid]);
    }

    // provisionamento (módulos) + afiliados + e-mail
    do_action('lagos_invoice_paid', $iid);
    if (function_exists('lagos_mail_paid')) lagos_mail_paid($iid);
    return true;
}

/* ══════════════════════════════════════════════════════════
   WEBHOOK REST — /wp-json/lagos/v1/gateways/{id}/webhook
   ══════════════════════════════════════════════════════════ */
add_action('rest_api_init', function () {
    register_rest_route('lagos/v1', '/gateways/(?P<id>[a-z0-9_-]+)/webhook', [
        'methods'             => ['POST', 'GET'],
        'permission_callback' => '__return_true',
        'callback'            => 'lagos_gw_webhook',
    ]);
});

function lagos_gw_webhook($request) {
    // parâmetro de ROTA explicitamente: corpos de webhook (JSON) também têm
    // campo "id", e no WP_REST_Request os parâmetros JSON têm precedência
    $id  = sanitize_key($request->get_url_params()['id'] ?? '');
    $gws = lagos_gateways();
    if (!isset($gws[$id])) {
        return new WP_Error('lagos_gw_unknown', 'Gateway desconhecido.', ['status' => 404]);
    }
    $cfg = lagos_gateway_config($id);
    if (empty($cfg['enabled'])) {
        return new WP_Error('lagos_gw_disabled', 'Gateway desativado.', ['status' => 404]);
    }

    // headers REST vêm como array — normaliza para string (ex.: Stripe-Signature)
    $hdrs = [];
    foreach ((array) $request->get_headers() as $k => $v) {
        $hdrs[$k] = is_array($v) ? implode(', ', $v) : (string) $v;
    }
    $iid = (int) $gws[$id]->verify_webhook($cfg, (string) $request->get_body(), $hdrs, (array) $request->get_params());
    if (!$iid) {
        return new WP_Error('lagos_gw_invalid', 'Assinatura ou evento inválido.', ['status' => 403]);
    }
    lagos_audit('webhook', 'webhook ' . $id . ' confirmou fatura #' . $iid);
    lagos_invoice_mark_paid($iid);
    return rest_ensure_response(['success' => true, 'gateway' => $id, 'invoice' => $iid]);
}

/* ══════════════════════════════════════════════════════════
   CHECKOUT DO CLIENTE — página /pagamento/
   ══════════════════════════════════════════════════════════ */
add_shortcode('lagos_checkout', 'lagos_checkout_render');

function lagos_checkout_render() {
    if (!is_user_logged_in()) return lagos_login_prompt();
    $uid = get_current_user_id();

    // ── Etapa 1: escolha do gateway (?invoice=ID&lagos_nonce=...) ──
    if (isset($_GET['invoice'])) {
        $iid = absint($_GET['invoice']);
        if (!wp_verify_nonce($_GET['lagos_nonce'] ?? '', 'lagos_pay_' . $iid)) {
            return '<div class="notice-err">' . lagos_icon('alert', 18) . ' ' . esc_html(lagos_t('gw_invalid')) . '</div>';
        }
        $inv = get_post($iid);
        if (!$inv || $inv->post_type !== 'lagos_invoice' || (int) get_post_meta($iid, '_lagos_user', true) !== $uid) {
            return '<div class="notice-err">' . lagos_icon('alert', 18) . ' ' . esc_html(lagos_t('gw_invalid')) . '</div>';
        }
        if (!in_array(get_post_meta($iid, '_lagos_status', true), ['unpaid', 'overdue'], true)) {
            return '<div class="notice-ok">' . lagos_icon('check', 18) . ' ' . esc_html(lagos_t('gw_paid_ok')) . '</div>'
                . '<a class="btn btn-primary" href="' . esc_url(lagos_panel_url('faturas')) . '">' . esc_html(lagos_t('gw_back_faturas')) . '</a>';
        }

        $amount = get_post_meta($iid, '_lagos_amount', true);
        $active = lagos_gateways_active();
        ob_start(); ?>
        <div class="page-hero" style="padding:40px 0">
          <div class="wrap" style="max-width:640px">
            <h1 style="font-size:1.6rem"><?php lagos_e('gw_title'); ?></h1>
            <p style="color:#C4B0F5;margin:6px 0 0"><?php lagos_e('gw_choose'); ?></p>
          </div>
        </div>
        <div class="wrap page-content" style="max-width:640px">
          <div class="card" style="padding:18px 20px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
            <div>
              <div class="t-muted" style="font-size:.85rem"><?php lagos_e('col_invoice'); ?></div>
              <div class="t-strong">#<?php echo esc_html(lagos_invoice_ref($iid)); ?></div>
              <div style="margin-top:6px"><a href="<?php echo esc_url(add_query_arg('print', '1')); ?>" style="color:#A78BFA;font-size:.85rem;text-decoration:none;font-weight:600"><?php echo lagos_icon('download', 14); ?> <?php lagos_e('inv_pdf_link'); ?></a></div>
            </div>
            <div style="text-align:right">
              <div class="t-muted" style="font-size:.85rem"><?php lagos_e('gw_total'); ?></div>
              <div class="t-strong t-money" style="font-size:1.3rem;color:#7C3AED"><?php echo esc_html(lagos_money($amount)); ?></div>
            </div>
          </div>

          <h3 class="section-title" style="margin:26px 0 12px"><?php lagos_e('gw_methods'); ?></h3>
          <?php if (!$active) : ?>
            <div class="notice-err"><?php lagos_e('gw_none'); ?></div>
          <?php else : ?>
          <div class="gw-grid">
            <?php foreach ($active as $gw) :
                $cfg = lagos_gateway_config($gw->id);
                $isTest = !$gw->is_virtual && !empty($cfg['sandbox']);
            ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="gw-btn">
              <input type="hidden" name="action" value="lagos_gw_checkout">
              <input type="hidden" name="invoice" value="<?php echo esc_attr($iid); ?>">
              <input type="hidden" name="gateway" value="<?php echo esc_attr($gw->id); ?>">
              <?php wp_nonce_field('lagos_gw_checkout'); ?>
              <button type="submit" style="--gw:<?php echo esc_attr($gw->color); ?>">
                <span class="gw-ico"><?php echo lagos_icon($gw->icon, 24); ?></span>
                <span class="gw-name"><?php echo esc_html($gw->name); ?></span>
                <span class="gw-chips">
                  <?php foreach ($gw->methods as $m) : ?><i><?php echo esc_html($m); ?></i><?php endforeach; ?>
                </span>
                <?php if ($isTest) : ?><span class="gw-test"><?php lagos_e('gw_test'); ?></span><?php endif; ?>
              </button>
            </form>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <p style="margin-top:22px"><a class="btn btn-ghost" href="<?php echo esc_url(lagos_panel_url('faturas')); ?>"><?php lagos_e('gw_back_faturas'); ?></a></p>
        </div>
        <?php
        return ob_get_clean();
    }

    // ── Etapa 2: sessão de pagamento (?pid=xxx) ──
    if (isset($_GET['pid'])) {
        $pid = preg_replace('/[^a-z0-9]/i', '', $_GET['pid']);
        $pay = get_transient('lagos_gwpay_' . $pid);
        if (!$pay) {
            return '<div class="notice-err">' . lagos_icon('alert', 18) . ' ' . esc_html(lagos_t('gw_expired')) . '</div>'
                . '<a class="btn btn-primary" href="' . esc_url(lagos_panel_url('faturas')) . '">' . esc_html(lagos_t('gw_back_faturas')) . '</a>';
        }
        $iid = (int) $pay['inv'];
        $inv = get_post($iid);
        $gws = lagos_gateways();
        $gw  = $gws[$pay['gw']] ?? null;
        if (!$inv || !$gw || (int) get_post_meta($iid, '_lagos_user', true) !== $uid) {
            return '<div class="notice-err">' . lagos_icon('alert', 18) . ' ' . esc_html(lagos_t('gw_invalid')) . '</div>';
        }
        if (get_post_meta($iid, '_lagos_status', true) === 'paid') {
            return '<div class="notice-ok">' . lagos_icon('check', 18) . ' ' . esc_html(lagos_t('gw_paid_ok')) . '</div>'
                . '<a class="btn btn-primary" href="' . esc_url(lagos_panel_url('faturas')) . '">' . esc_html(lagos_t('gw_back_faturas')) . '</a>';
        }

        $cfg    = lagos_gateway_config($gw->id);
        $amount = (float) get_post_meta($iid, '_lagos_amount', true);
        $isTest = !$gw->is_virtual && !empty($cfg['sandbox']);
        $nonce  = wp_create_nonce('lagos_gw_confirm_' . $pid);

        // multi-moeda: cobra na moeda do cliente; gateways Pix-only sempre em BRL
        $cur     = function_exists('lagos_currency_current') ? lagos_currency_current() : 'BRL';
        $allowed = property_exists($gw, 'only_currencies') ? (array) $gw->only_currencies : [];
        if ($allowed && !in_array($cur, $allowed, true)) $cur = 'BRL';
        $pay_amount = $amount;
        if (!$gw->is_virtual && function_exists('lagos_money_convert')) {
            $cfg['settings']['currency'] = $cur;
            $pay_amount = lagos_money_convert($amount, $cur);
        }

        ob_start(); ?>
        <div class="page-hero" style="padding:40px 0">
          <div class="wrap" style="max-width:560px">
            <h1 style="font-size:1.6rem"><?php echo lagos_icon($gw->icon, 26); ?> <?php echo esc_html($gw->name); ?></h1>
            <p style="color:#C4B0F5;margin:6px 0 0">#<?php echo esc_html(lagos_invoice_ref($iid)); ?> · <?php echo esc_html(lagos_money($amount, $cur)); ?></p>
          </div>
        </div>
        <div class="wrap page-content" style="max-width:560px">
          <?php if ($isTest) : ?>
            <div class="notice-warn" style="margin-bottom:16px"><?php echo lagos_icon('alert', 18); ?> <b><?php lagos_e('gw_test'); ?></b> — <?php lagos_e('gw_test_note'); ?></div>
          <?php endif; ?>
          <?php if ($cur !== 'BRL') : ?>
            <p style="margin:-6px 0 14px;font-size:.85rem;color:#6D28D9;font-weight:600"><?php echo esc_html(lagos_t('cur_charged', ['{cur}' => $cur])); ?></p>
          <?php endif; ?>

          <div class="card gw-panel" style="--gw:<?php echo esc_attr($gw->color); ?>">
            <?php
            // ── Modo simulação (demo/sandbox) ──
            if ($gw->is_virtual || $isTest) :
                $res = $gw->is_virtual ? $gw->pay($inv, $amount, $cfg) : null;
                if (!empty($res['qr'])) : ?>
                <p class="t-muted" style="text-align:center"><?php lagos_e('gw_scan'); ?></p>
                <div class="qr" style="background:#fff"><?php echo $res['qr']['svg']; ?></div>
                <div class="pix-code"><?php echo esc_html($res['qr']['code']); ?></div>
                <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:14px">
                  <button class="btn btn-ghost" data-copy="<?php echo esc_attr($res['qr']['code']); ?>" data-copied="<?php echo esc_attr(lagos_t('gw_copied')); ?>"><?php echo lagos_icon('api', 16); ?> <?php lagos_e('gw_copy'); ?></button>
                </div>
                <?php endif; ?>
                <div class="gw-amount"><?php echo esc_html(lagos_money($amount, $cur)); ?></div>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                  <input type="hidden" name="action" value="lagos_gw_confirm">
                  <input type="hidden" name="pid" value="<?php echo esc_attr($pid); ?>">
                  <?php wp_nonce_field('lagos_gw_confirm_' . $pid); ?>
                  <button type="submit" class="btn btn-primary btn-block" style="margin-top:14px"><?php echo lagos_icon('check', 18); ?> <?php lagos_e('gw_approve'); ?></button>
                </form>

            <?php
            // ── Modo REAL ──
            else :
                $res = $gw->pay($inv, $pay_amount, $cfg);
                if (!empty($res['error'])) : ?>
                    <div class="notice-err"><?php echo esc_html($res['error']); ?></div>
                    <p style="margin-top:14px"><a class="btn btn-ghost" href="<?php echo esc_url(lagos_checkout_url($iid)); ?>"><?php lagos_e('gw_choose'); ?></a></p>
                <?php elseif (!empty($res['redirect'])) : ?>
                    <div class="gw-amount"><?php echo esc_html(lagos_money($amount, $cur)); ?></div>
                    <p class="t-muted" style="text-align:center"><?php echo esc_html(sprintf(lagos_t('gw_redirect_note'), $gw->name)); ?></p>
                    <a class="btn btn-primary btn-block" style="margin-top:14px" href="<?php echo esc_url($res['redirect']); ?>"><?php lagos_e('gw_pay'); ?> — <?php echo esc_html($gw->name); ?></a>
                <?php elseif (!empty($res['qr'])) : ?>
                    <p class="t-muted" style="text-align:center"><?php lagos_e('gw_scan'); ?></p>
                    <?php if (!empty($res['qr']['b64'])) : ?>
                    <div class="gw-qr"><img src="data:image/png;base64,<?php echo esc_attr($res['qr']['b64']); ?>" alt="QR Pix"></div>
                    <?php endif; ?>
                    <div class="pix-code"><?php echo esc_html($res['qr']['code']); ?></div>
                    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:14px">
                      <button class="btn btn-ghost" data-copy="<?php echo esc_attr($res['qr']['code']); ?>" data-copied="<?php echo esc_attr(lagos_t('gw_copied')); ?>"><?php echo lagos_icon('api', 16); ?> <?php lagos_e('gw_copy'); ?></button>
                    </div>
                <?php elseif (!empty($res['instructions'])) : ?>
                    <div class="gw-instructions"><?php echo wp_kses_post($res['instructions']); ?></div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                      <input type="hidden" name="action" value="lagos_gw_confirm">
                      <input type="hidden" name="pid" value="<?php echo esc_attr($pid); ?>">
                      <?php wp_nonce_field('lagos_gw_confirm_' . $pid); ?>
                      <button type="submit" class="btn btn-primary btn-block" style="margin-top:14px"><?php lagos_e('gw_manual_done_btn'); ?></button>
                    </form>
                <?php endif;
            endif; ?>
          </div>
          <p style="margin-top:18px;text-align:center"><a class="t-muted" href="<?php echo esc_url(lagos_checkout_url($iid)); ?>"><?php echo lagos_icon('chevron', 14); ?> <?php lagos_e('gw_choose'); ?></a></p>
        </div>
        <?php
        return ob_get_clean();
    }

    return '<div class="notice-err">' . lagos_icon('alert', 18) . ' ' . esc_html(lagos_t('gw_invalid')) . '</div>';
}

/* ── Handler: iniciar sessão de pagamento ── */
add_action('admin_post_lagos_gw_checkout', function () {
    if (!is_user_logged_in()) { wp_safe_redirect(home_url('/entrar/')); exit; }
    check_admin_referer('lagos_gw_checkout');
    $iid = absint($_POST['invoice'] ?? 0);
    $gid = sanitize_key($_POST['gateway'] ?? '');
    $inv = get_post($iid);
    $gws = lagos_gateways_active();
    if (!$inv || $inv->post_type !== 'lagos_invoice'
        || (int) get_post_meta($iid, '_lagos_user', true) !== get_current_user_id()
        || !in_array(get_post_meta($iid, '_lagos_status', true), ['unpaid', 'overdue'], true)
        || !isset($gws[$gid])) {
        wp_safe_redirect(lagos_panel_url('faturas') . '?lagos_err=gw_invalid');
        exit;
    }
    $pid = wp_generate_password(24, false, false);
    set_transient('lagos_gwpay_' . $pid, ['inv' => $iid, 'gw' => $gid, 'uid' => get_current_user_id()], 1800);
    wp_safe_redirect(home_url('/pagamento/') . '?pid=' . $pid);
    exit;
});

/* ── Handler: confirmar (simulação / sandbox / manual) ── */
add_action('admin_post_lagos_gw_confirm', function () {
    if (!is_user_logged_in()) { wp_safe_redirect(home_url('/entrar/')); exit; }
    $pid = preg_replace('/[^a-z0-9]/i', '', $_POST['pid'] ?? '');
    check_admin_referer('lagos_gw_confirm_' . $pid, '_wpnonce');
    $pay = get_transient('lagos_gwpay_' . $pid);
    if (!$pay || (int) $pay['uid'] !== get_current_user_id()) {
        wp_safe_redirect(lagos_panel_url('faturas') . '?lagos_err=gw_invalid');
        exit;
    }
    $iid = (int) $pay['inv'];
    $gw  = lagos_gateways()[$pay['gw']] ?? null;
    delete_transient('lagos_gwpay_' . $pid);

    // manual → aguardando confirmação da equipe
    if ($gw && $gw->id === 'manual') {
        update_post_meta($iid, '_lagos_awaiting', current_time('mysql'));
        wp_safe_redirect(lagos_panel_url('faturas') . '?lagos_msg=manual_pending');
        exit;
    }

    lagos_invoice_mark_paid($iid);
    if (get_post_meta($iid, '_lagos_deposit', true) === '1') {
        wp_safe_redirect(lagos_panel_url('perfil') . '?lagos_msg=deposit_ok');
        exit;
    }
    wp_safe_redirect(lagos_panel_url('faturas') . '?lagos_msg=paid_success');
    exit;
});

/* ══════════════════════════════════════════════════════════
   ADMIN — página "Pagamentos" (Gateways)
   ══════════════════════════════════════════════════════════ */
add_action('admin_menu', function () {
    // prioridade 11: após o menu pai 'lagospanel' (admin.php)
    add_submenu_page('lagospanel', 'Gateways de Pagamento', 'Pagamentos', 'manage_options', 'lagos-gateways', 'lagos_admin_gateways_page');
}, 11);

function lagos_admin_gateways_page() {
    $gws  = lagos_gateways();
    $cfgs = lagos_gateways_config();
    $edit = isset($_GET['edit']) ? sanitize_key($_GET['edit']) : '';

    if ($edit && isset($gws[$edit])) {
        $gw  = $gws[$edit];
        $cfg = $cfgs[$edit];
        $saved = isset($_GET['saved']);
        ?>
        <div class="wrap">
          <h1 class="wp-heading-inline"><?php echo esc_html($gw->name); ?> — Gateway</h1>
          <a href="<?php echo esc_url(admin_url('admin.php?page=lagos-gateways')); ?>" class="page-title-action">← Voltar</a>
          <?php if ($saved) : ?><div class="notice notice-success is-dismissible"><p>Configurações salvas.</p></div><?php endif; ?>
          <hr class="wp-header-end">

          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="lagos_gateway_save">
            <input type="hidden" name="gateway" value="<?php echo esc_attr($edit); ?>">
            <?php wp_nonce_field('lagos_gateway_save'); ?>

            <table class="form-table" role="presentation">
              <tr>
                <th>Ativo</th>
                <td><label><input type="checkbox" name="enabled" <?php checked(!empty($cfg['enabled'])); ?>> Aceitar pagamentos por este gateway</label></td>
              </tr>
              <?php if ($gw->has_sandbox) : ?>
              <tr>
                <th>Modo teste</th>
                <td><label><input type="checkbox" name="sandbox" <?php checked(!empty($cfg['sandbox'])); ?>> Sandbox — simula aprovação sem cobrança real (útil para homologar)</label></td>
              </tr>
              <?php endif; ?>
              <tr>
                <th>Ordem</th>
                <td><input type="number" name="order" value="<?php echo esc_attr($cfg['order'] ?? 99); ?>" min="1" max="99" style="width:70px"> (ordem de exibição no checkout)</td>
              </tr>
              <?php foreach ($gw->fields() as $f) :
                  [$key, $label, $type, $ph, $desc] = array_pad($f, 5, '');
                  $val = $cfg['settings'][$key] ?? '';
              ?>
              <tr>
                <th><label for="f_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                <td>
                  <?php if ($type === 'textarea') : ?>
                    <textarea name="s_<?php echo esc_attr($key); ?>" id="f_<?php echo esc_attr($key); ?>" rows="6" class="large-text code" placeholder="<?php echo esc_attr($ph); ?>"><?php echo esc_textarea($val); ?></textarea>
                  <?php elseif ($type === 'select') : ?>
                    <select name="s_<?php echo esc_attr($key); ?>" id="f_<?php echo esc_attr($key); ?>">
                      <?php foreach ($ph as $ov => $ol) : ?><option value="<?php echo esc_attr($ov); ?>" <?php selected($val, $ov); ?>><?php echo esc_html($ol); ?></option><?php endforeach; ?>
                    </select>
                  <?php else : ?>
                    <input type="<?php echo esc_attr($type ?: 'text'); ?>" name="s_<?php echo esc_attr($key); ?>" id="f_<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($val); ?>" class="regular-text" placeholder="<?php echo esc_attr($ph); ?>" autocomplete="off">
                  <?php endif; ?>
                  <?php if ($desc) : ?><p class="description"><?php echo esc_html($desc); ?></p><?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$gw->is_virtual) : ?>
              <tr>
                <th>URL de Webhook</th>
                <td>
                  <input type="text" readonly value="<?php echo esc_attr(lagos_gateway_webhook_url($edit)); ?>" class="regular-text" onclick="this.select()">
                  <p class="description">Cadastre esta URL no painel do provedor para confirmação automática dos pagamentos.</p>
                </td>
              </tr>
              <?php endif; ?>
            </table>
            <?php submit_button('Salvar gateway'); ?>
          </form>
        </div>
        <?php
        return;
    }
    ?>
    <div class="wrap">
      <h1 class="wp-heading-inline">Gateways de Pagamento</h1>
      <p class="description">Formas de pagamento aceitas no checkout — mesmos tipos do Paymenter (Stripe, PayPal, Mollie, Mercado Pago, Coinbase, manual). Ative, configure as credenciais e desligue o modo teste para cobrar de verdade.</p>
      <?php if (isset($_GET['toggled'])) : ?><div class="notice notice-success is-dismissible"><p>Status atualizado.</p></div><?php endif; ?>

      <table class="wp-list-table widefat fixed striped" cellspacing="0" style="margin-top:14px;max-width:960px">
        <thead><tr><th style="width:26%">Gateway</th><th>Métodos</th><th style="width:12%">Status</th><th style="width:9%">Ordem</th><th style="width:22%">Ações</th></tr></thead>
        <tbody>
        <?php foreach ($gws as $id => $gw) : $cfg = $cfgs[$id] ?? []; ?>
          <tr>
            <td>
              <strong style="font-size:14px"><?php echo esc_html($gw->name); ?></strong>
              <div class="description" style="margin-top:2px"><?php echo esc_html($gw->desc); ?></div>
            </td>
            <td><?php foreach ($gw->methods as $m) : echo '<span style="display:inline-block;background:#EDE9FE;color:#7C3AED;border-radius:99px;padding:2px 10px;font-size:12px;margin:2px 4px 2px 0">' . esc_html($m) . '</span>'; endforeach; ?></td>
            <td>
              <?php if ($gw->is_virtual && lagos_panel_mode() === 'production') : ?>
                <span style="color:#b32d2e;font-weight:600">Bloqueado no modo produção</span>
              <?php elseif (!empty($cfg['enabled'])) : ?>
                <span style="color:#0a7c33;font-weight:600">Ativo<?= (!empty($cfg['sandbox']) && $gw->has_sandbox) ? ' · Teste' : '' ?></span>
              <?php else : ?>
                <span style="color:#8a8f98">Inativo</span>
              <?php endif; ?>
            </td>
            <td><?php echo esc_html($cfg['order'] ?? '—'); ?></td>
            <td>
              <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=lagos-gateways&edit=' . $id)); ?>">Configurar</a>
              <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=lagos_gateway_toggle&gateway=' . $id), 'lagos_gw_toggle_' . $id)); ?>"><?php echo !empty($cfg['enabled']) ? 'Desativar' : 'Ativar'; ?></a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <div style="background:#fff;border:1px solid #dcdcde;border-left:4px solid #7C3AED;border-radius:6px;padding:14px 18px;margin-top:18px;max-width:960px">
        <p style="margin:0;font-size:13.5px">
          <strong>Adicionar outros gateways:</strong> o sistema é extensível como o marketplace do Paymenter.
          Basta registrar uma classe que estenda <code>Lagos_Gateway</code> no filter <code>lagos_gateways</code> —
          ela aparece aqui no admin e no checkout automaticamente, com métodos próprios (pay + webhook).
        </p>
      </div>
    </div>
    <?php
}

/* ── Salvar configuração do gateway ── */
add_action('admin_post_lagos_gateway_save', function () {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');
    check_admin_referer('lagos_gateway_save');
    $gid = sanitize_key($_POST['gateway'] ?? '');
    $gws = lagos_gateways();
    if (!isset($gws[$gid])) wp_die('Gateway desconhecido.');

    $cfgs = lagos_gateways_config();
    $gw   = $gws[$gid];
    $settings = [];
    foreach ($gw->fields() as $f) {
        $key = $f[0];
        $settings[$key] = $_POST['s_' . $key] ?? '';
    }
    $cfgs[$gid] = [
        'enabled'  => isset($_POST['enabled']) ? 1 : 0,
        'sandbox'  => isset($_POST['sandbox']) ? 1 : 0,
        'order'    => max(1, min(99, (int) ($_POST['order'] ?? 99))),
        'settings' => $settings,
    ];
    update_option('lagos_gateways_config', $cfgs);
    lagos_audit('gateway_config', 'configuração do gateway ' . $gid . ' salva');
    wp_safe_redirect(admin_url('admin.php?page=lagos-gateways&edit=' . $gid . '&saved=1'));
    exit;
});

/* ── Ativar/desativar rápido ── */
add_action('admin_post_lagos_gateway_toggle', function () {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');
    $gid = sanitize_key($_GET['gateway'] ?? '');
    check_admin_referer('lagos_gw_toggle_' . $gid);
    $gws = lagos_gateways();
    if (!isset($gws[$gid])) wp_die('Gateway desconhecido.');
    $cfgs = lagos_gateways_config();
    $cfgs[$gid]['enabled'] = empty($cfgs[$gid]['enabled']) ? 1 : 0;
    update_option('lagos_gateways_config', $cfgs);
    wp_safe_redirect(admin_url('admin.php?page=lagos-gateways&toggled=1'));
    exit;
});

/* ══════════════════════════════════════════════════════════
   FATURA EM DOCUMENTO (imprimível / salvar em PDF)
   /pagamento/?invoice=ID&lagos_nonce=...&print=1
   ══════════════════════════════════════════════════════════ */
add_action('template_redirect', function () {
    if (!isset($_GET['invoice'], $_GET['print'], $_GET['lagos_nonce'])) return;
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/pagamento/') === false) return;
    if (!is_user_logged_in()) return;
    $iid = absint($_GET['invoice']);
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['lagos_nonce'])), 'lagos_pay_' . $iid)) return;
    $inv = get_post($iid);
    if (!$inv || $inv->post_type !== 'lagos_invoice' || (int) get_post_meta($iid, '_lagos_user', true) !== get_current_user_id()) return;
    lagos_invoice_doc($inv);
    exit;
});

function lagos_invoice_doc($inv) {
    $iid = $inv->ID;
    $uid = (int) get_post_meta($iid, '_lagos_user', true);
    $u = get_userdata($uid);
    $amount = (float) get_post_meta($iid, '_lagos_amount', true);
    $st = (string) get_post_meta($iid, '_lagos_status', true);
    $due = (string) get_post_meta($iid, '_lagos_due', true);
    $paid_at = (string) get_post_meta($iid, '_lagos_paid_at', true);
    $company = (string) get_option('lagos_company_name', 'Lagos Soluções');
    $support = (string) get_option('lagos_support_email', '');
    $cur = lagos_currency_current();

    $items = [];
    foreach (explode("\n", (string) get_post_meta($iid, '_lagos_items', true)) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $p = array_map('trim', explode('|', $line));
        $n = (string) ($p[2] ?? '0');
        $val = strpos($n, ',') !== false ? (float) str_replace(',', '.', str_replace('.', '', $n)) : (float) $n;
        $items[] = [(string) ($p[0] ?? ''), (int) ($p[1] ?? 1), $val];
    }
    if (!$items) $items[] = [lagos_t('inv_service'), 1, $amount];

    $badge = $st === 'paid'
        ? [lagos_t('inv_st_paid'), '#22B07D']
        : (($due !== '' && $due < date('Y-m-d')) ? [lagos_t('inv_st_overdue'), '#D64550'] : [lagos_t('inv_st_open'), '#E0A21A']);
    $logo = get_theme_file_uri('assets/img/logo-lagos-solucoes.png');
    ?>
<!doctype html>
<html lang="<?php echo lagos_t('inv_st_paid') === 'Paga' ? 'pt-BR' : 'en'; ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html(lagos_t('inv_doc')); ?> #<?php echo esc_html(lagos_invoice_ref($iid)); ?> — <?php echo esc_html($company); ?></title>
<style>
  *{box-sizing:border-box}
  body{margin:0;background:#EDEAF6;font:15px/1.55 -apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#241A3E}
  .doc{max-width:800px;margin:28px auto;background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(36,26,62,.12);padding:40px 44px}
  .top{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap}
  .top img{height:46px;width:auto}
  .ref{text-align:right}
  .ref h1{margin:0;font-size:1.7rem;letter-spacing:.02em;color:#5B21B6}
  .badge{display:inline-block;padding:3px 12px;border-radius:999px;font-size:.78rem;font-weight:700;color:#fff;margin-top:6px}
  .grid{display:flex;gap:14px;flex-wrap:wrap;margin:28px 0 6px}
  .box{flex:1;min-width:220px;background:#F7F5FC;border:1px solid #E5DFF5;border-radius:12px;padding:14px 16px}
  .box h2{margin:0 0 8px;font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;color:#7C3AED}
  .box div{margin:2px 0;font-size:.9rem}
  .muted{color:#8B7BB8}
  table{width:100%;border-collapse:collapse;margin-top:22px}
  th{background:#F1ECFB;color:#5B3FA8;text-align:left;font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;padding:10px 12px}
  th:first-child{border-radius:8px 0 0 0}th:last-child{border-radius:0 8px 0 0}
  td{padding:12px;border-bottom:1px solid #EFEBF8;font-size:.92rem}
  .tot td{border-bottom:none;font-weight:800;font-size:1.05rem;color:#5B21B6}
  .equiv{color:#8B7BB8;font-size:.85rem;font-weight:600}
  .note{margin-top:26px;padding:14px 16px;background:#F7F5FC;border-left:4px solid #7C3AED;border-radius:8px;font-size:.9rem}
  .foot{margin-top:26px;padding-top:16px;border-top:1px solid #EFEBF8;display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;font-size:.8rem;color:#8B7BB8}
  .actions{max-width:800px;margin:0 auto 14px;display:flex;gap:10px;flex-wrap:wrap}
  .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:10px;font-weight:700;text-decoration:none;font-size:.9rem;border:none;cursor:pointer}
  .btn-p{background:linear-gradient(135deg,#7C3AED,#C040E0);color:#fff}
  .btn-g{background:#fff;color:#5B21B6;border:1px solid #D8CFF2}
  @media print{body{background:#fff}.actions{display:none}.doc{box-shadow:none;margin:0;max-width:100%;border-radius:0}}
</style>
</head>
<body>
<div class="actions">
  <button class="btn btn-p" onclick="window.print()"><?php echo lagos_icon('download', 16); ?> <?php echo esc_html(lagos_t('inv_print')); ?></button>
  <a class="btn btn-g" href="<?php echo esc_url(home_url('/painel/faturas/')); ?>"><?php echo esc_html(lagos_t('gw_back_faturas')); ?></a>
</div>
<div class="doc">
  <div class="top">
    <div>
      <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($company); ?>">
      <?php if ($support) : ?><div class="muted" style="margin-top:8px;font-size:.85rem"><?php echo esc_html($support); ?></div><?php endif; ?>
    </div>
    <div class="ref">
      <h1><?php echo esc_html(lagos_t('inv_doc')); ?> #<?php echo esc_html(lagos_invoice_ref($iid)); ?></h1>
      <span class="badge" style="background:<?php echo esc_attr($badge[1]); ?>"><?php echo esc_html($badge[0]); ?></span>
    </div>
  </div>
  <div class="grid">
    <div class="box">
      <h2><?php echo esc_html(lagos_t('inv_issued_to')); ?></h2>
      <div><strong><?php echo esc_html($u ? $u->display_name : '—'); ?></strong></div>
      <div class="muted"><?php echo esc_html($u ? $u->user_email : ''); ?></div>
      <div class="muted"><?php echo esc_html($u ? $u->user_login : ''); ?></div>
    </div>
    <div class="box">
      <h2><?php echo esc_html(lagos_t('inv_doc')); ?></h2>
      <div><?php echo esc_html(lagos_t('inv_issue_date')); ?>: <strong><?php echo esc_html(date_i18n('d/m/Y', strtotime($inv->post_date))); ?></strong></div>
      <div><?php echo esc_html(lagos_t('inv_due_date')); ?>: <strong><?php echo esc_html($due ? date_i18n('d/m/Y', strtotime($due)) : '—'); ?></strong></div>
      <?php if ($paid_at) : ?><div><?php echo esc_html(lagos_t('inv_paid_on')); ?>: <strong><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($paid_at))); ?></strong></div><?php endif; ?>
    </div>
  </div>
  <table>
    <thead><tr><th><?php echo esc_html(lagos_t('inv_description')); ?></th><th style="width:70px;text-align:center"><?php echo esc_html(lagos_t('inv_qty')); ?></th><th style="width:130px;text-align:right"><?php echo esc_html(lagos_t('inv_amount')); ?></th></tr></thead>
    <tbody>
    <?php foreach ($items as $it) : ?>
      <tr><td><?php echo esc_html($it[0]); ?></td><td style="text-align:center"><?php echo (int) $it[1]; ?></td><td style="text-align:right"><?php echo esc_html(lagos_money($it[2], 'BRL')); ?></td></tr>
    <?php endforeach; ?>
      <tr class="tot"><td colspan="2" style="text-align:right"><?php echo esc_html(lagos_t('gw_total')); ?></td><td style="text-align:right"><?php echo esc_html(lagos_money($amount, 'BRL')); ?></td></tr>
      <?php if ($cur !== 'BRL') : ?><tr><td colspan="3" style="text-align:right" class="equiv">&#8776; <?php echo esc_html(lagos_money($amount, $cur)); ?> (<?php echo esc_html($cur); ?>)</td></tr><?php endif; ?>
    </tbody>
  </table>
  <div class="note"><?php echo esc_html(lagos_t('inv_thanks', ['{company}' => $company])); ?></div>
  <div class="foot">
    <div>&copy; <?php echo date_i18n('Y'); ?> <?php echo esc_html($company); ?> — Todos os direitos reservados</div>
    <div><?php echo esc_html(lagos_t('inv_generated')); ?></div>
  </div>
</div>
<script>window.addEventListener('load', function(){setTimeout(function(){window.print()},500)});</script>
</body>
</html>
    <?php
}
