<?php
/**
 * LagosPanel Core — Relatórios Financeiros (v0.9)
 * Receita, inadimplência, clientes, churn e MRR no admin.
 *
 * © 2026 Lagos Soluções — Todos os direitos reservados.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_submenu_page('lagospanel', 'Relatórios LagosPanel', 'Relatórios', 'manage_options', 'lagos-reports', 'lagos_reports_page');
}, 20);

function lagos_reports_page() {
    if (!current_user_can('manage_options')) return;

    $t30 = date('Y-m-d', strtotime('-30 days'));

    /* ── Faturas ── */
    $total = 0.0; $count_paid = 0; $rev_30d = 0.0;
    $by_month = []; $by_item = []; $payers = [];
    $open_n = 0; $open_sum = 0.0; $overdue = [];

    foreach (get_posts(['post_type' => 'lagos_invoice', 'numberposts' => -1, 'post_status' => 'any']) as $inv) {
        $amt = (float) get_post_meta($inv->ID, '_lagos_amount', true);
        $st  = (string) get_post_meta($inv->ID, '_lagos_status', true);
        $ym  = substr($inv->post_date, 0, 7);
        if ($st === 'paid') {
            $total += $amt; $count_paid++;
            $by_month[$ym] = ($by_month[$ym] ?? 0) + $amt;
            $payers[(int) get_post_meta($inv->ID, '_lagos_user', true)] = 1;
            if (substr((string) get_post_meta($inv->ID, '_lagos_paid_at', true), 0, 10) >= $t30) $rev_30d += $amt;
            $first = trim(explode('|', trim(explode("\n", (string) get_post_meta($inv->ID, '_lagos_items', true))[0]))[0]);
            $label = ($first !== '') ? $first : $inv->post_title;
            $by_item[$label] = ($by_item[$label] ?? 0) + $amt;
        } elseif (in_array($st, ['unpaid', 'overdue'], true)) {
            $due = (string) get_post_meta($inv->ID, '_lagos_due', true);
            if ($due !== '' && $due < date('Y-m-d')) $overdue[] = $inv;
            else { $open_n++; $open_sum += $amt; }
        }
    }
    arsort($by_item);
    $overdue_sum = 0.0;
    foreach ($overdue as $o) $overdue_sum += (float) get_post_meta($o->ID, '_lagos_amount', true);

    /* ── Clientes ── */
    $clients = get_users(['role' => 'lagos_client']);
    $new_30d = 0;
    foreach ($clients as $c) if (substr($c->user_registered, 0, 10) >= $t30) $new_30d++;

    /* ── Serviços / MRR / churn ── */
    $svc_active = 0; $svc_suspended = 0; $churned = 0; $mrr = 0.0;
    foreach (get_posts(['post_type' => 'lagos_service', 'numberposts' => -1, 'post_status' => 'any']) as $s) {
        $st = (string) get_post_meta($s->ID, '_lagos_status', true);
        if ($st === 'active') {
            $svc_active++;
            $prod = get_post((int) get_post_meta($s->ID, '_lagos_product', true));
            if ($prod) {
                $p = (float) get_post_meta($prod->ID, '_lagos_price', true);
                $mrr += ((string) get_post_meta($prod->ID, '_lagos_cycle', true) === 'yearly') ? $p / 12 : $p;
            }
        } elseif ($st === 'suspended') $svc_suspended++;
        elseif (in_array($st, ['cancelled', 'terminated'], true) && substr($s->post_modified, 0, 10) >= $t30) $churned++;
    }
    $arpu = count($payers) ? $total / count($payers) : 0.0;
    $churn_pct = ($svc_active + $churned) ? $churned / ($svc_active + $churned) * 100 : 0.0;

    /* ── 12 meses ── */
    $months = [];
    for ($i = 11; $i >= 0; $i--) $months[date('Y-m', strtotime($i . ' months ago'))] = 0.0;
    foreach ($by_month as $ym => $v) if (array_key_exists($ym, $months)) $months[$ym] = $v;
    $maxm = max(array_values($months)) ?: 1.0;

    $card = static function ($label, $value, $sub = '', $accent = '#7C3AED') {
        return '<div style="background:#fff;border:1px solid #E3E9F4;border-radius:14px;padding:16px 18px;min-width:150px;flex:1">'
            . '<div style="font-size:.78rem;color:#8B7BB8;font-weight:600;text-transform:uppercase;letter-spacing:.04em">' . esc_html($label) . '</div>'
            . '<div style="font-size:1.45rem;font-weight:800;color:' . esc_attr($accent) . ';margin-top:4px">' . $value . '</div>'
            . ($sub !== '' ? '<div style="font-size:.78rem;color:#8B7BB8;margin-top:2px">' . esc_html($sub) . '</div>' : '')
            . '</div>';
    };
    ?>
    <div style="display:flex;align-items:center;gap:14px;margin:0 0 18px">
        <?php echo lagos_icon('chart', 26); ?>
        <div>
            <h1 style="margin:0;font-size:1.5rem">Relatórios Financeiros</h1>
            <div style="color:#8B7BB8;font-size:.85rem;margin-top:2px">Receita, inadimplência, clientes e churn — valores na moeda base (BRL)</div>
        </div>
    </div>

    <div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:14px">
        <?php
        echo $card('Receita total', esc_html(lagos_money($total, 'BRL')), $count_paid . ' faturas pagas');
        echo $card('Receita 30 dias', esc_html(lagos_money($rev_30d, 'BRL')), 'pagamentos do período', '#22B07D');
        echo $card('MRR estimado', esc_html(lagos_money($mrr, 'BRL')), $svc_active . ' serviços ativos', '#C040E0');
        echo $card('Ticket médio', esc_html(lagos_money($arpu, 'BRL')), count($payers) . ' clientes pagantes');
        ?>
    </div>
    <div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px">
        <?php
        echo $card('Em aberto', esc_html(lagos_money($open_sum, 'BRL')), $open_n . ' faturas', '#E0A21A');
        echo $card('Vencidas', esc_html(lagos_money($overdue_sum, 'BRL')), count($overdue) . ' faturas', '#D64550');
        echo $card('Clientes', count($clients), '+' . $new_30d . ' nos últimos 30 dias', '#22B07D');
        echo $card('Serviços', $svc_active . ' ativos', $svc_suspended . ' suspensos · churn 30d ' . number_format($churn_pct, 1, ',', '.') . '%');
        ?>
    </div>

    <div style="background:#fff;border:1px solid #E3E9F4;border-radius:14px;padding:20px 22px;margin-bottom:18px">
        <div style="font-weight:700;margin-bottom:6px">Receita — últimos 12 meses</div>
        <svg viewBox="0 0 910 210" style="width:100%;height:auto" role="img" aria-label="Receita mensal">
            <defs><linearGradient id="lg-rg" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#7C3AED"/><stop offset="1" stop-color="#C040E0"/>
            </linearGradient></defs>
            <?php $i = 0; foreach ($months as $ym => $v) :
                $h = $v > 0 ? max(8, (int) round($v / $maxm * 150)) : 3;
                $x = 16 + $i * 74; $y = 172 - $h; ?>
                <rect x="<?php echo (int) $x; ?>" y="<?php echo (int) $y; ?>" width="48" height="<?php echo (int) $h; ?>" rx="7" fill="<?php echo $v > 0 ? 'url(#lg-rg)' : '#ECE7F7'; ?>"/>
                <?php if ($v > 0) : ?><text x="<?php echo (int) $x + 24; ?>" y="<?php echo (int) $y - 6; ?>" text-anchor="middle" font-size="10" font-weight="700" fill="#5B4A8A"><?php echo esc_html(lagos_money($v, 'BRL')); ?></text><?php endif; ?>
                <text x="<?php echo (int) $x + 24; ?>" y="192" text-anchor="middle" font-size="10" fill="#8B7BB8"><?php echo esc_html(date_i18n('M/y', strtotime($ym . '-01'))); ?></text>
            <?php $i++; endforeach; ?>
        </svg>
    </div>

    <div style="display:flex;gap:18px;flex-wrap:wrap">
        <div style="background:#fff;border:1px solid #E3E9F4;border-radius:14px;padding:20px 22px;flex:1;min-width:340px">
            <div style="font-weight:700;margin-bottom:12px">Receita por produto/serviço</div>
            <?php $maxi = $by_item ? (max($by_item) ?: 1.0) : 1.0; foreach (array_slice($by_item, 0, 8, true) as $label => $v) : ?>
              <div style="margin-bottom:10px">
                <div style="display:flex;justify-content:space-between;gap:10px;font-size:.85rem;margin-bottom:4px">
                  <span style="font-weight:600"><?php echo esc_html($label); ?></span>
                  <span style="color:#7C3AED;font-weight:700;white-space:nowrap"><?php echo esc_html(lagos_money($v, 'BRL')); ?></span>
                </div>
                <div style="background:#F0ECFA;border-radius:6px;height:8px"><div style="width:<?php echo (int) round($v / $maxi * 100); ?>%;height:8px;border-radius:6px;background:linear-gradient(90deg,#7C3AED,#C040E0)"></div></div>
              </div>
            <?php endforeach; if (!$by_item) : ?><div style="color:#8B7BB8">Nenhuma fatura paga ainda.</div><?php endif; ?>
        </div>
        <div style="background:#fff;border:1px solid #E3E9F4;border-radius:14px;padding:20px 22px;flex:1;min-width:340px">
            <div style="font-weight:700;margin-bottom:12px">Inadimplência (vencidas)</div>
            <?php if ($overdue) : ?>
            <table class="wp-list-table widefat striped" style="border-radius:8px;overflow:hidden">
              <thead><tr><th>Fatura</th><th>Cliente</th><th>Dias</th><th style="text-align:right">Valor</th></tr></thead>
              <tbody>
              <?php foreach (array_slice($overdue, 0, 10) as $o) :
                $cu = get_userdata((int) get_post_meta($o->ID, '_lagos_user', true));
                $days = (int) floor((time() - strtotime((string) get_post_meta($o->ID, '_lagos_due', true))) / 86400); ?>
                <tr>
                  <td><a href="<?php echo esc_url(get_edit_post_link($o->ID, 'url')); ?>">#<?php echo esc_html(lagos_invoice_ref($o->ID)); ?></a></td>
                  <td><?php echo esc_html($cu ? $cu->display_name : '—'); ?></td>
                  <td><?php echo $days; ?></td>
                  <td style="text-align:right;font-weight:700;color:#D64550"><?php echo esc_html(lagos_money((float) get_post_meta($o->ID, '_lagos_amount', true), 'BRL')); ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
            <?php else : ?><div style="color:#8B7BB8">Nenhuma fatura vencida — tudo em dia.</div><?php endif; ?>
        </div>
    </div>
    <?php
}
