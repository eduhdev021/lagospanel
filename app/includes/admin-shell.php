<?php
/**
 * LagosPanel Core — Admin Shell (visual estilo WHMCS) — v0.7
 *
 * Transforma as páginas LagosPanel no wp-admin em uma interface
 * estilo WHMCS: topbar roxa com busca global e notificações,
 * sidebar escura agrupada por seções e conteúdo em tela cheia.
 *
 * © 2026 Lagos Soluções — Todos os direitos reservados.
 */
if (!defined('ABSPATH')) exit;

/** Nossas páginas no wp-admin (slugs) e CPTs que já ficam no menu lagospanel */
function lagos_admin_shell_pages() {
    return ['lagospanel', 'lagos-settings', 'lagos-clients', 'lagos-gateways', 'lagos-modules', 'lagos-reports', 'lagos-audit'];
}
function lagos_admin_shell_cpts() {
    return ['lagos_product', 'lagos_service', 'lagos_invoice', 'lagos_ticket', 'lagos_kb', 'lagos_download', 'lagos_incident'];
}

function lagos_admin_shell_active() {
    if (!current_user_can('manage_options')) return false;
    if (in_array($_GET['page'] ?? '', lagos_admin_shell_pages(), true)) return true;
    if (in_array($_GET['post_type'] ?? '', lagos_admin_shell_cpts(), true)
        && strpos($_SERVER['SCRIPT_NAME'] ?? '', 'edit.php') !== false) return true;
    return false;
}

/* Inicia o buffer em todas as páginas nossas */
add_action('admin_init', function () {
    if (lagos_admin_shell_active()) {
        ob_start('lagos_admin_shell_filter');
    }
}, 1);

/** Filtra o HTML final do wp-admin e injeta o shell */
function lagos_admin_shell_filter($html) {
    if (stripos($html, '<body') === false) return $html;

    $side  = lagos_admin_shell_sidebar();
    $top   = lagos_admin_shell_topbar();
    $css   = lagos_admin_shell_css();

    // injeta estilo e chrome
    $html = str_ireplace('</head>', '<style id="lagos-shell-css">' . $css . '</style></head>', $html);
    $html = preg_replace('/<body([^>]*)class="([^"]*)"/i', '<body$1class="$2 lagos-shell"', $html, 1);
    $html = preg_replace('/<div id="wpwrap">/i', '<div id="wpwrap">' . $top . $side, $html, 1);
    return $html;
}

/** Notificações do sino (pendências reais) */
function lagos_admin_shell_notices() {
    $items = [];
    $unpaid = 0;
    foreach (get_posts(['post_type' => 'lagos_invoice', 'numberposts' => -1, 'post_status' => 'publish']) as $inv) {
        if (in_array(get_post_meta($inv->ID, '_lagos_status', true), ['unpaid', 'overdue'], true)) $unpaid++;
    }
    if ($unpaid) $items[] = ['icon' => 'invoice', 'text' => $unpaid . ' fatura(s) em aberto', 'url' => admin_url('edit.php?post_type=lagos_invoice')];

    $open = 0;
    foreach (get_posts(['post_type' => 'lagos_ticket', 'numberposts' => -1, 'post_status' => 'publish']) as $t) {
        if (get_post_meta($t->ID, '_lagos_status', true) !== 'closed') $open++;
    }
    if ($open) $items[] = ['icon' => 'ticket', 'text' => $open . ' ticket(s) aberto(s)', 'url' => admin_url('edit.php?post_type=lagos_ticket')];

    $manual = 0;
    foreach (get_posts(['post_type' => 'lagos_invoice', 'numberposts' => 20, 'post_status' => 'publish']) as $inv) {
        if (get_post_meta($inv->ID, '_lagos_awaiting', true) && get_post_meta($inv->ID, '_lagos_status', true) !== 'paid') $manual++;
    }
    if ($manual) $items[] = ['icon' => 'card', 'text' => $manual . ' transferência(s) aguardando', 'url' => admin_url('admin.php?page=lagospanel')];
    return $items;
}

/** Topbar estilo WHMCS */
function lagos_admin_shell_topbar() {
    $notices = lagos_admin_shell_notices();
    $n = count($notices);
    $me = wp_get_current_user();

    $list = '';
    foreach ($notices as $i) {
        $list .= '<a href="' . esc_url($i['url']) . '">' . lagos_icon($i['icon'], 15) . ' ' . esc_html($i['text']) . '</a>';
    }
    if (!$list) $list = '<span class="lg-empty">' . lagos_icon('check', 15) . ' Tudo em dia</span>';

    return '<div id="lagos-topbar">
      <button type="button" id="lagos-burger" title="Menu">' . lagos_icon('menu', 18) . '</button>
      <a class="lg-logo" href="' . esc_url(admin_url('admin.php?page=lagospanel')) . '">Lagos<span>Panel</span></a>
      <form class="lg-search" method="get" action="' . esc_url(admin_url('admin.php')) . '">
        <input type="hidden" name="page" value="lagos-clients">
        <input type="search" name="s" placeholder="Buscar cliente, e-mail..." autocomplete="off">
        <button type="submit">' . lagos_icon('send', 14) . '</button>
      </form>
      <div class="lg-right">
        <a class="lg-site" href="' . esc_url(home_url('/')) . '" target="_blank">' . lagos_icon('globe', 15) . ' Ver site</a>
        <div class="lg-bell-wrap">
          <button type="button" class="lg-bell" id="lagos-bell" title="Notificações">' . lagos_icon('alert', 17) . ($n ? '<b>' . $n . '</b>' : '') . '</button>
          <div class="lg-notices" id="lagos-notices">' . $list . '</div>
        </div>
        <span class="lg-me">' . get_avatar($me->ID, 30) . '<em>' . esc_html($me->display_name) . '</em></span>
      </div>
    </div>';
}

/** Sidebar estilo WHMCS (grupos) */
function lagos_admin_shell_sidebar() {
    $menu = [
        '' => [
            ['admin.php?page=lagospanel', 'home', 'Dashboard'],
        ],
        'Clientes' => [
            ['admin.php?page=lagos-clients', 'user', 'Clientes'],
        ],
        'Financeiro' => [
            ['edit.php?post_type=lagos_invoice', 'invoice', 'Faturas'],
            ['admin.php?page=lagos-reports', 'chart', 'Relatórios'],
            ['admin.php?page=lagos-gateways', 'card', 'Gateways'],
        ],
        'Produtos & Serviços' => [
            ['edit.php?post_type=lagos_product', 'store', 'Produtos'],
            ['edit.php?post_type=lagos_service', 'server', 'Serviços'],
            ['admin.php?page=lagos-modules', 'wrench', 'Conexões'],
        ],
        'Suporte' => [
            ['edit.php?post_type=lagos_ticket', 'ticket', 'Tickets'],
            ['edit.php?post_type=lagos_kb', 'book', 'Base de conhecimento'],
        ],
        'Conteúdo' => [
            ['edit.php?post_type=lagos_download', 'download', 'Downloads'],
            ['edit.php?post_type=lagos_incident', 'clock', 'Incidentes'],
        ],
        'Sistema' => [
            ['admin.php?page=lagos-settings', 'settings', 'Configurações'],
            ['admin.php?page=lagos-audit', 'shield', 'Auditoria'],
        ],
    ];

    $cur = $_SERVER['REQUEST_URI'] ?? '';
    $html = '<nav id="lagos-side"><div class="lg-side-scroll">';
    foreach ($menu as $group => $items) {
        if ($group) $html .= '<div class="lg-group">' . esc_html($group) . '</div>';
        foreach ($items as [$url, $icon, $label]) {
            $active = (strpos($cur, strtok($url, '?')) !== false) && (strpos($url, 'page=') ? strpos($cur, explode('page=', $url)[1]) !== false : true);
            $html .= '<a href="' . esc_url(admin_url($url)) . '" class="' . ($active ? 'lg-on' : '') . '">'
                . lagos_icon($icon, 16) . '<span>' . esc_html($label) . '</span></a>';
        }
    }
    $html .= '</div><div class="lg-side-foot">LagosPanel v' . esc_html(defined('LAGOS_CORE_VERSION') ? LAGOS_CORE_VERSION : '') . '<br>© ' . date_i18n('Y') . ' Lagos Soluções</div></nav>';
    return $html;
}

/** CSS do shell */
function lagos_admin_shell_css() {
    return '
#lagos-topbar{position:fixed;top:0;left:0;right:0;height:52px;z-index:100050;display:flex;align-items:center;gap:14px;
  padding:0 16px;background:linear-gradient(90deg,#6D28D9,#7C3AED 55%,#C040E0);color:#fff;box-shadow:0 2px 10px rgba(18,11,36,.25)}
#lagos-burger{display:none;background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:8px;padding:6px 8px;cursor:pointer}
#lagos-topbar .lg-logo{font-size:1.15rem;font-weight:800;color:#fff;text-decoration:none;letter-spacing:-.02em;white-space:nowrap}
#lagos-topbar .lg-logo span{font-weight:400;opacity:.9}
#lagos-topbar .lg-search{flex:1;max-width:420px;display:flex;background:rgba(255,255,255,.16);border-radius:10px;overflow:hidden;border:1px solid rgba(255,255,255,.25)}
#lagos-topbar .lg-search input{flex:1;background:transparent;border:none;outline:none;color:#fff;padding:7px 12px;font-size:13px;min-width:0}
#lagos-topbar .lg-search input::placeholder{color:rgba(255,255,255,.7)}
#lagos-topbar .lg-search button{background:transparent;border:none;color:#fff;padding:0 10px;cursor:pointer}
#lagos-topbar .lg-right{margin-left:auto;display:flex;align-items:center;gap:16px}
#lagos-topbar .lg-site{color:#fff;text-decoration:none;font-size:12.5px;opacity:.92;display:flex;gap:6px;align-items:center}
#lagos-topbar .lg-site:hover{opacity:1}
.lg-bell-wrap{position:relative}
#lagos-topbar .lg-bell{background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:10px;padding:7px 9px;cursor:pointer;position:relative}
#lagos-topbar .lg-bell b{position:absolute;top:-6px;right:-6px;background:#F43F5E;color:#fff;font-size:10px;border-radius:99px;padding:1px 5px}
.lg-notices{display:none;position:absolute;right:0;top:44px;background:#fff;border:1px solid #E3E9F4;border-radius:12px;
  box-shadow:0 14px 40px rgba(18,11,36,.18);min-width:250px;padding:8px;z-index:100060}
.lg-notices a,.lg-notices .lg-empty{display:flex;gap:8px;align-items:center;padding:9px 12px;color:#12203A;text-decoration:none;
  font-size:13px;border-radius:8px}
.lg-notices a:hover{background:#F6F3FE;color:#6D28D9}
.lg-notices svg{color:#7C3AED;flex:none}
.lg-bell-wrap.open .lg-notices{display:block}
#lagos-topbar .lg-me{display:flex;align-items:center;gap:8px;font-size:13px}
#lagos-topbar .lg-me img{border-radius:50%;display:block}
#lagos-topbar .lg-me em{font-style:normal;font-weight:600;white-space:nowrap}

#lagos-side{position:fixed;top:52px;left:0;bottom:0;width:236px;background:#201742;z-index:100040;display:flex;flex-direction:column}
.lg-side-scroll{overflow-y:auto;flex:1;padding:12px 10px}
#lagos-side .lg-group{color:#8F7BC8;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;padding:16px 12px 6px}
#lagos-side a{display:flex;align-items:center;gap:10px;color:#CFC4F2;text-decoration:none;font-size:13.5px;
  padding:9px 12px;border-radius:9px;margin:1px 0}
#lagos-side a svg{color:#8F7BC8;flex:none}
#lagos-side a:hover{background:rgba(255,255,255,.07);color:#fff}
#lagos-side a.lg-on{background:linear-gradient(90deg,#7C3AED,#8B5CF6);color:#fff;box-shadow:0 6px 16px rgba(124,58,237,.4)}
#lagos-side a.lg-on svg{color:#fff}
.lg-side-foot{padding:12px 16px;font-size:11px;color:#8F7BC8;border-top:1px solid #2E2258;line-height:1.6}

body.lagos-shell #adminmenuback,body.lagos-shell #adminmenumain{display:none!important}
body.lagos-shell #wpcontent,body.lagos-shell #wpfooter{margin-left:236px!important}
body.lagos-shell #wpcontent{padding-top:64px;padding-left:24px}
body.lagos-shell #wpbody-content{padding-bottom:40px}
body.lagos-shell #wpfooter{left:236px}
body.lagos-shell .wrap{margin-right:20px}
body.lagos-shell #screen-meta-links,body.lagos-shell #screen-meta{margin-left:0}

@media (max-width:960px){
  #lagos-burger{display:inline-flex}
  #lagos-side{transform:translateX(-100%);transition:transform .2s}
  body.lagos-side-open #lagos-side{transform:none}
  body.lagos-shell #wpcontent,body.lagos-shell #wpfooter{margin-left:0!important}
  body.lagos-shell #wpfooter{left:0}
  #lagos-topbar .lg-me em{display:none}
}
';
}

/* Toggle sidebar/bell (JS mínimo) */
add_action('admin_footer', function () {
    if (!lagos_admin_shell_active()) return;
    echo '<script>
(function(){
  var b=document.getElementById("lagos-burger");if(b)b.onclick=function(){document.body.classList.toggle("lagos-side-open")};
  var w=document.querySelector(".lg-bell-wrap");if(w){var bell=document.getElementById("lagos-bell");
    bell.onclick=function(e){e.stopPropagation();w.classList.toggle("open")};
    document.addEventListener("click",function(){w.classList.remove("open")});}
})();
</script>';
});
