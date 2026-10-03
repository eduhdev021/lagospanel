<?php
/**
 * LagosPanel Core — Administração (menu unificado estilo WHMCS)
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_menu_page(
        'LagosPanel',
        'LagosPanel',
        'manage_options',
        'lagospanel',
        'lagos_admin_home',
        'dashicons-superhero-alt',
        3
    );
    add_submenu_page('lagospanel', 'Configurações LagosPanel', 'Configurações', 'manage_options', 'lagos-settings', 'lagos_admin_settings');
    add_submenu_page('lagospanel', 'Clientes LagosPanel', 'Clientes', 'manage_options', 'lagos-clients', 'lagos_admin_clients_page');
});

/** Confirma um pagamento manual (transferência) */
add_action('admin_post_lagos_manual_confirm', function () {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');
    $iid = absint($_GET['invoice'] ?? 0);
    check_admin_referer('lagos_manual_' . $iid);
    if (function_exists('lagos_invoice_mark_paid')) lagos_invoice_mark_paid($iid);
    delete_post_meta($iid, '_lagos_awaiting');
    lagos_audit('manual_confirm', 'confirmação manual da fatura #' . $iid);
    wp_safe_redirect(admin_url('admin.php?page=lagospanel&confirmed=' . $iid));
    exit;
});

/** Exportação CSV (clientes / faturas / auditoria) */
add_action('admin_post_lagos_export', function () {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');
    check_admin_referer('lagos_export');
    $type = sanitize_key($_GET['type'] ?? '');
    $rows = [];

    if ($type === 'clients') {
        foreach (get_users(['role' => 'lagos_client']) as $u) {
            $svc = 0; $paid = 0.0;
            foreach (get_posts(['post_type' => 'lagos_service', 'numberposts' => -1, 'post_status' => 'any', 'meta_key' => '_lagos_user', 'meta_value' => $u->ID]) as $s) {
                if (get_post_meta($s->ID, '_lagos_status', true) === 'active') $svc++;
            }
            foreach (get_posts(['post_type' => 'lagos_invoice', 'numberposts' => -1, 'post_status' => 'any', 'meta_key' => '_lagos_user', 'meta_value' => $u->ID]) as $i) {
                if (get_post_meta($i->ID, '_lagos_status', true) === 'paid') $paid += (float) get_post_meta($i->ID, '_lagos_amount', true);
            }
            $rows[] = [
                'nome' => $u->display_name, 'email' => $u->user_email,
                'cliente_desde' => mysql2date('d/m/Y', $u->user_registered),
                'servicos_ativos' => $svc,
                'total_pago' => number_format($paid, 2, ',', '.'),
                'carteira' => number_format((float) get_user_meta($u->ID, 'lagos_balance', true), 2, ',', '.'),
                'ultimo_acesso' => get_user_meta($u->ID, '_lagos_last_login', true) ?: 'nunca',
                'dois_fatores' => get_user_meta($u->ID, '_lagos_2fa_enabled', true) === '1' ? 'sim' : 'nao',
                'email_confirmado' => get_user_meta($u->ID, '_lagos_activation', true) ? 'pendente' : 'sim',
            ];
        }
    } elseif ($type === 'invoices') {
        foreach (get_posts(['post_type' => 'lagos_invoice', 'numberposts' => -1, 'post_status' => 'any']) as $i) {
            $u = get_userdata((int) get_post_meta($i->ID, '_lagos_user', true));
            $rows[] = [
                'fatura' => lagos_invoice_ref($i->ID),
                'cliente' => $u ? $u->user_email : '',
                'total' => number_format((float) get_post_meta($i->ID, '_lagos_amount', true), 2, ',', '.'),
                'status' => get_post_meta($i->ID, '_lagos_status', true),
                'vencimento' => get_post_meta($i->ID, '_lagos_due', true),
                'paga_em' => get_post_meta($i->ID, '_lagos_paid_at', true) ?: '',
                'criada_em' => $i->post_date,
            ];
        }
    } elseif ($type === 'audit') {
        foreach (array_reverse(get_option('lagos_audit_log', [])) as $e) {
            $rows[] = [
                'data' => $e['time'], 'usuario' => $e['user'], 'evento' => $e['event'],
                'detalhe' => $e['detail'], 'ip' => $e['ip'], 'navegador' => $e['ua'],
            ];
        }
    } else {
        wp_die('Tipo de exportação inválido.');
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="lagospanel-' . $type . '-' . gmdate('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8 (Excel BR)
    if ($rows) fputcsv($out, array_keys($rows[0]), ';');
    foreach ($rows as $r) fputcsv($out, $r, ';');
    fclose($out);
    exit;
});

function lagos_export_url($type) {
    return wp_nonce_url(admin_url('admin-post.php?action=lagos_export&type=' . $type), 'lagos_export');
}

/** Página de Clientes (estilo WHMCS) */
function lagos_admin_clients_page() {
    $s = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $args = ['role' => 'lagos_client'];
    $users = get_users($args);
    if ($s !== '') {
        $s = strtolower($s);
        $users = array_values(array_filter($users, function ($u) use ($s) {
            return strpos(strtolower($u->display_name), $s) !== false || strpos(strtolower($u->user_email), $s) !== false;
        }));
    }

    // totais por cliente (serviços ativos, total pago, carteira, último acesso)
    $rows = [];
    foreach ($users as $u) {
        $svc_active = 0; $svc_total = 0; $paid = 0.0;
        foreach (get_posts(['post_type' => 'lagos_service', 'numberposts' => -1, 'post_status' => 'any', 'meta_key' => '_lagos_user', 'meta_value' => $u->ID]) as $svc) {
            $svc_total++;
            if (get_post_meta($svc->ID, '_lagos_status', true) === 'active') $svc_active++;
        }
        foreach (get_posts(['post_type' => 'lagos_invoice', 'numberposts' => -1, 'post_status' => 'any', 'meta_key' => '_lagos_user', 'meta_value' => $u->ID]) as $inv) {
            if (get_post_meta($inv->ID, '_lagos_status', true) === 'paid') $paid += (float) get_post_meta($inv->ID, '_lagos_amount', true);
        }
        $rows[] = [
            'u' => $u,
            'avatar'   => get_avatar($u->ID, 34, 'identicon', '', ['force_display' => true]),
            'svc'      => $svc_active,
            'svc_total'=> $svc_total,
            'paid'     => $paid,
            'balance'  => (float) get_user_meta($u->ID, 'lagos_balance', true),
            'last'     => get_user_meta($u->ID, '_lagos_last_login', true),
            '2fa'      => get_user_meta($u->ID, '_lagos_2fa_enabled', true) === '1',
            'pending'  => (bool) get_user_meta($u->ID, '_lagos_activation', true),
        ];
    }
    usort($rows, function ($a, $b) { return $b['paid'] <=> $a['paid']; });
    ?>
    <div class="wrap">
      <h1 class="wp-heading-inline">Clientes</h1>
      <form method="get" style="display:inline-block;margin-left:10px">
        <input type="hidden" name="page" value="lagos-clients">
        <p class="search-box"><input type="search" name="s" value="<?php echo esc_attr($s); ?>" placeholder="nome ou e-mail">
        <button class="button">Buscar</button></p>
      </form>
      <p class="description"><?php echo count($rows); ?> cliente(s) — ordenados por total pago. Clique no nome para editar.
        <a href="<?php echo esc_url(lagos_export_url('clients')); ?>" class="button button-small" style="margin-left:8px">Exportar CSV</a></p>

      <table class="wp-list-table widefat fixed striped" cellspacing="0" style="margin-top:12px">
        <thead><tr>
          <th style="width:26%">Cliente</th><th style="width:12%">Serviços</th><th style="width:13%">Total pago</th>
          <th style="width:11%">Carteira</th><th style="width:12%">Último acesso</th>
          <th style="width:8%">2FA</th><th style="width:10%">Status</th>
        </tr></thead>
        <tbody>
        <?php if (!$rows) : ?>
          <tr><td colspan="7" style="color:#8a8f98">Nenhum cliente encontrado.</td></tr>
        <?php else : foreach ($rows as $r) : ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <span style="border-radius:50%;overflow:hidden;width:34px;height:34px;flex:none"><?php echo $r['avatar']; ?></span>
                <span>
                  <a href="<?php echo esc_url(get_edit_user_link($r['u']->ID)); ?>"><strong><?php echo esc_html($r['u']->display_name); ?></strong></a>
                  <div style="font-size:12px;color:#5D6E8C"><?php echo esc_html($r['u']->user_email); ?></div>
                </span>
              </div>
            </td>
            <td><?php echo $r['svc']; ?> ativo(s) / <?php echo $r['svc_total']; ?></td>
            <td><strong>R$ <?php echo esc_html(number_format($r['paid'], 2, ',', '.')); ?></strong></td>
            <td>R$ <?php echo esc_html(number_format($r['balance'], 2, ',', '.')); ?></td>
            <td><?php echo $r['last'] ? esc_html(mysql2date('d/m/Y H:i', $r['last'])) : '—'; ?></td>
            <td><?php echo $r['2fa'] ? '<span style="color:#0a7c33;font-weight:600">Ativo</span>' : '<span style="color:#8a8f98">—</span>'; ?></td>
            <td><?php echo $r['pending'] ? '<span style="color:#B45309;font-weight:600">E-mail pendente</span>' : '<span style="color:#0a7c33">Confirmado</span>'; ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php
}

function lagos_admin_home() {
    // ── modo do painel + avisos de produção ──
    $mode = function_exists('lagos_panel_mode') ? lagos_panel_mode() : 'demo';
    $sandbox_active = [];
    if ($mode === 'production' && function_exists('lagos_gateways_config')) {
        foreach (lagos_gateways_config() as $gid => $gcfg) {
            if (!empty($gcfg['enabled']) && !empty($gcfg['sandbox'])) $sandbox_active[] = $gid;
        }
    }
    // pagamentos manuais aguardando confirmação da equipe
    $manual_pending = [];
    foreach (get_posts(['post_type' => 'lagos_invoice', 'numberposts' => 20, 'post_status' => 'publish']) as $inv) {
        if (get_post_meta($inv->ID, '_lagos_awaiting', true) && get_post_meta($inv->ID, '_lagos_status', true) !== 'paid') {
            $manual_pending[] = $inv;
        }
    }
    $last_cron = get_option('lagos_last_cron', null);
    $clients = count(get_users(['role' => 'lagos_client']));
    $services = wp_count_posts('lagos_service');
    $invoices = wp_count_posts('lagos_invoice');
    $tickets  = wp_count_posts('lagos_ticket');

    $unpaid_total = 0;
    $paid_by_month = [];
    $paid_total = 0;
    foreach (get_posts(['post_type' => 'lagos_invoice', 'numberposts' => -1, 'post_status' => 'publish']) as $inv) {
        $st = get_post_meta($inv->ID, '_lagos_status', true);
        $amount = (float) get_post_meta($inv->ID, '_lagos_amount', true);
        if (in_array($st, ['unpaid', 'overdue'], true)) $unpaid_total += $amount;
        if ($st === 'paid' && strpos($inv->post_date, date('Y')) === 0) {
            $m = (int) substr($inv->post_date, 5, 2);
            $paid_by_month[$m] = ($paid_by_month[$m] ?? 0) + $amount;
            $paid_total += $amount;
        }
    }
    $mrr = 0;
    foreach (get_posts(['post_type' => 'lagos_service', 'numberposts' => -1, 'post_status' => 'publish']) as $svc) {
        if (get_post_meta($svc->ID, '_lagos_status', true) === 'active' && get_post_meta($svc->ID, '_lagos_cycle', true) === 'monthly') {
            $mrr += (float) get_post_meta($svc->ID, '_lagos_price', true);
        }
    }
    $products = wp_count_posts('lagos_product');
    $rating = function_exists('lagos_ticket_rating_avg') ? lagos_ticket_rating_avg() : null;
    ?>
    <div class="wrap">
        <h1><span class="dashicons dashicons-superhero-alt"></span> LagosPanel — Administração</h1>

        <?php if (isset($_GET['confirmed'])) : ?>
          <div class="notice notice-success is-dismissible"><p>Pagamento manual confirmado — fatura #<?php echo (int) $_GET['confirmed']; ?> marcada como paga.</p></div>
        <?php endif; ?>
        <?php if (isset($_GET['cron'])) : ?>
          <div class="notice notice-success is-dismissible"><p>Rotina diária executada (renovações, vencidas e suspensões).</p></div>
        <?php endif; ?>

        <?php if ($mode === 'demo') : ?>
          <div class="notice notice-warning" style="border-left-color:#b32d2e">
            <p><strong>MODO DEMONSTRAÇÃO</strong> — dados de exemplo, Pix simulado e aprovação sem cobrança estão ativos.
            Mude para produção em <a href="<?php echo esc_url(admin_url('admin.php?page=lagos-settings')); ?>">Configurações</a> quando for faturar de verdade.</p>
          </div>
        <?php elseif ($sandbox_active) : ?>
          <div class="notice notice-error">
            <p><strong>ATENÇÃO:</strong> gateways ativos em modo TESTE em produção:
            <strong><?php echo esc_html(implode(', ', $sandbox_active)); ?></strong> — nenhuma cobrança real será feita até desligar o modo teste.</p>
          </div>
        <?php endif; ?>

        <?php if ($manual_pending) : ?>
          <div style="background:#fff;border:1px solid #dcdcde;border-left:4px solid #C040E0;border-radius:6px;padding:14px 18px;margin:10px 0 6px;max-width:960px">
            <strong>Transferências aguardando confirmação (<?php echo count($manual_pending); ?>)</strong>
            <table style="width:100%;margin-top:8px;font-size:13px">
              <?php foreach ($manual_pending as $inv) : $uid = (int) get_post_meta($inv->ID, '_lagos_user', true); $u = get_userdata($uid); ?>
              <tr>
                <td style="padding:6px 8px 6px 0">#<?php echo esc_html(lagos_invoice_ref($inv->ID)); ?></td>
                <td style="padding:6px 8px 6px 0"><?php echo esc_html($u ? $u->display_name : '—'); ?></td>
                <td style="padding:6px 8px 6px 0"><strong>R$ <?php echo esc_html(number_format((float) get_post_meta($inv->ID, '_lagos_amount', true), 2, ',', '.')); ?></strong></td>
                <td style="padding:6px 0">
                  <a class="button button-small button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=lagos_manual_confirm&invoice=' . $inv->ID), 'lagos_manual_' . $inv->ID)); ?>">Confirmar pagamento</a>
                </td>
              </tr>
              <?php endforeach; ?>
            </table>
          </div>
        <?php endif; ?>

        <p style="color:#8a8f98;font-size:12.5px;margin:4px 0 14px">
          Automação diária: renovações <?php echo esc_html(max(1, (int) get_option('lagos_renew_days', 5))); ?>d antes ·
          suspensão <?php echo esc_html(max(1, (int) get_option('lagos_suspend_days', 3))); ?>d após vencer ·
          última execução: <?php echo esc_html($last_cron['at'] ?? 'nunca'); ?>
          <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=lagos_cron_run'), 'lagos_cron_run')); ?>" class="button button-small" style="margin-left:8px">Executar agora</a>
        </p>

        <?php $recent = array_slice(get_option('lagos_audit_log', []), 0, 8); if ($recent) : ?>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 18px;margin:14px 0;max-width:960px">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
            <strong>Atividade recente</strong>
            <span style="font-size:12.5px">
              Exportar:
              <a href="<?php echo esc_url(lagos_export_url('clients')); ?>">Clientes</a> ·
              <a href="<?php echo esc_url(lagos_export_url('invoices')); ?>">Faturas</a> ·
              <a href="<?php echo esc_url(lagos_export_url('audit')); ?>">Auditoria</a> (CSV)
            </span>
          </div>
          <table style="width:100%;margin-top:8px;font-size:13px">
            <?php foreach ($recent as $e) : ?>
            <tr>
              <td style="padding:5px 10px 5px 0;color:#5D6E8C;white-space:nowrap"><?php echo esc_html(mysql2date('d/m H:i', $e['time'])); ?></td>
              <td style="padding:5px 10px 5px 0;font-weight:600"><?php echo esc_html($e['user']); ?></td>
              <td style="padding:5px 10px 5px 0"><span style="color:#7C3AED;font-weight:600"><?php echo esc_html($e['event']); ?></span></td>
              <td style="padding:5px 0;color:#2A3B58"><?php echo esc_html(mb_substr($e['detail'], 0, 80)); ?></td>
            </tr>
            <?php endforeach; ?>
          </table>
          <p style="margin:10px 0 0;font-size:12.5px"><a href="<?php echo esc_url(admin_url('admin.php?page=lagos-audit')); ?>">Ver todos os eventos →</a></p>
        </div>
        <?php endif; ?>
        <p class="text-muted">Painel de billing da <strong>Lagos Soluções</strong>. Gerencie tudo por aqui.</p>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px;margin:24px 0">
            <?php
            $cards = [
                ['Clientes', $clients, 'admin-users', '#7C3AED'],
                ['Servicos ativos', isset($services->publish) ? (int) $services->publish : 0, 'admin-links', '#16A34A'],
                ['Faturas em aberto', lagos_money($unpaid_total), 'money-alt', '#D97706'],
                ['Tickets abertos', isset($tickets->open) ? (int) $tickets->open : (int) $tickets->publish, 'format-chat', '#9333EA'],
                ['Produtos', isset($products->publish) ? (int) $products->publish : 0, 'cart', '#0891B2'],
                ['MRR estimado', lagos_money($mrr), 'chart-bar', '#C040E0'],
                ['Satisfacao', $rating ? $rating . ' / 5' : '-', 'star-filled', '#F59E0B'],
            ];
            foreach ($cards as [$label, $value, $icon, $color]) :
            ?>
            <div style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:18px">
                <div style="display:flex;align-items:center;gap:10px">
                    <span class="dashicons dashicons-<?php echo esc_attr($icon); ?>" style="font-size:26px;width:26px;height:26px;color:<?php echo esc_attr($color); ?>"></span>
                    <div>
                        <div style="font-size:1.4rem;font-weight:700;line-height:1.1"><?php echo esc_html($value); ?></div>
                        <div style="color:#646970;font-size:.82rem"><?php echo esc_html($label); ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <h2>Faturamento <?php echo esc_html(date('Y')); ?> — R$ <?php echo esc_html(number_format($paid_total, 2, ',', '.')); ?></h2>
        <?php
        $meses = [1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];
        $max = max(array_merge([1], $paid_by_month));
        $bar_w = 34; $gap = 16; $h = 160;
        $svg = '<svg width="' . (12 * ($bar_w + $gap) + 10) . '" height="' . ($h + 40) . '" viewBox="0 0 ' . (12 * ($bar_w + $gap) + 10) . ' ' . ($h + 40) . '" style="max-width:100%;height:auto">';
        foreach ($meses as $m => $lbl) {
            $v = (float) ($paid_by_month[$m] ?? 0);
            $bh = $max > 0 ? max(3, round($v / $max * $h)) : 3;
            $x = 8 + ($m - 1) * ($bar_w + $gap);
            $y = $h - $bh;
            $fill = $v > 0 ? 'url(#barGrad)' : '#EDEDF7';
            $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $bar_w . '" height="' . $bh . '" rx="6" fill="' . $fill . '"/>';
            if ($v > 0) {
                $svg .= '<text x="' . ($x + $bar_w / 2) . '" y="' . ($y - 6) . '" font-size="10" font-weight="700" fill="#7C3AED" text-anchor="middle">' . esc_html(number_format($v, 0, ',', '.')) . '</text>';
            }
            $svg .= '<text x="' . ($x + $bar_w / 2) . '" y="' . ($h + 16) . '" font-size="11" fill="#646970" text-anchor="middle">' . $lbl . '</text>';
        }
        $svg .= '<defs><linearGradient id="barGrad" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#9F67F0"/><stop offset="1" stop-color="#7C3AED"/></linearGradient></defs></svg>';
        echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:22px;max-width:100%;overflow-x:auto">' . $svg . '</div>';
        ?>

        <h2 style="margin-top:28px">Atalhos rapidos</h2>
        <p>
            <a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=lagos_product')); ?>">+ Novo produto</a>
            <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=lagos_product')); ?>">Produtos</a>
            <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=lagos_service')); ?>">Servicos</a>
            <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=lagos_invoice')); ?>">Faturas</a>
            <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=lagos_ticket')); ?>">Tickets</a>
            <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=lagos_download')); ?>">Downloads</a>
            <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=lagos_incident')); ?>">Incidentes</a>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=lagos-modules')); ?>">Conexoes</a>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=lagos-settings')); ?>">Configuracoes</a>
        </p>

        <h2 style="margin-top:28px">Como usar</h2>
        <ol style="max-width:640px;line-height:1.9">
            <li><strong>Produtos</strong> aparecem automaticamente na <a href="<?php echo esc_url(home_url('/loja/')); ?>">loja</a> e nos destaques da home (marque "Destaque").</li>
            <li>Quando o cliente contrata, um <strong>servico pendente</strong> e uma <strong>fatura</strong> sao criados automaticamente.</li>
            <li>O pagamento demo (botao "Ja paguei") marca a fatura como paga e <strong>ativa o servico</strong> (provisionando no modulo vinculado).</li>
            <li>Responda tickets em <strong>LagosPanel &rarr; Tickets</strong> (o cliente ve a resposta na area dele; o status muda para "Respondido").</li>
            <li>Edite servicos, faturas e clientes em qualquer momento - tudo aparece na area do cliente em tempo real.</li>
        </ol>
    </div>
    <?php
}

function lagos_admin_settings() {
    if (!current_user_can('manage_options')) return;
    if (isset($_GET['pipe'])) : ?>
      <div class="notice notice-<?php echo $_GET['pipe'] === 'err' ? 'error' : 'success'; ?> is-dismissible"><p><?php echo $_GET['pipe'] === 'err' ? 'Não foi possível conectar ao servidor IMAP — confira host/porta/credenciais.' : 'Verificação concluída — ' . (int) $_GET['pipe'] . ' mensagem(ns) processada(s).'; ?></p></div>
    <?php endif;
    if (isset($_GET['rates'])) : ?>
      <div class="notice notice-<?php echo $_GET['rates'] === 'ok' ? 'success' : 'error'; ?> is-dismissible"><p><?php echo $_GET['rates'] === 'ok' ? 'Câmbio atualizado com as cotações de referência do BCE.' : 'Não foi possível consultar o câmbio agora — ajuste as taxas manualmente.'; ?></p></div>
    <?php endif;

    if (!empty($_POST['lagos_settings']) && check_admin_referer('lagos_settings_save')) {
        update_option('lagos_company_name', sanitize_text_field(wp_unslash($_POST['company_name'] ?? 'Lagos Soluções')));
        update_option('lagos_support_email', sanitize_email(wp_unslash($_POST['support_email'] ?? '')));
        update_option('lagos_currency', sanitize_text_field(wp_unslash($_POST['currency'] ?? 'BRL')));
        do_action('lagos_settings_saved');
        echo '<div class="notice notice-success is-dismissible"><p>Configurações salvas.</p></div>';
    }
    if (isset($_GET['lagos_mailtest'])) {
        $ok = $_GET['lagos_mailtest'] === 'ok';
        echo '<div class="notice ' . ($ok ? 'notice-success' : 'notice-error') . ' is-dismissible"><p>' . ($ok ? 'E-mail de teste enviado! Confira a caixa de entrada e o log abaixo.' : 'Falha ao enviar o e-mail de teste. Verifique o SMTP e o log abaixo.') . '</p></div>';
    }
    $company = get_option('lagos_company_name', 'Lagos Soluções');
    $email   = get_option('lagos_support_email', 'suporte@lagos.com.br');
    $curr    = get_option('lagos_currency', 'BRL');
    ?>
    <div class="wrap">
        <h1>Configurações do LagosPanel</h1>
        <form method="post">
            <?php wp_nonce_field('lagos_settings_save'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th>Nome da companhia</th>
                    <td><input type="text" name="company_name" class="regular-text" value="<?php echo esc_attr($company); ?>"></td>
                </tr>
                <tr>
                    <th>E-mail de suporte</th>
                    <td><input type="email" name="support_email" class="regular-text" value="<?php echo esc_attr($email); ?>"></td>
                </tr>
                <tr>
                    <th>Moeda</th>
                    <td>
                        <select name="currency">
                            <?php foreach (['BRL' => 'Real (R$)', 'USD' => 'Dólar ($)', 'EUR' => 'Euro (€)'] as $k => $l) : ?>
                                <option value="<?php echo esc_attr($k); ?>" <?php selected($curr, $k); ?>><?php echo esc_html($l); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <?php do_action('lagos_settings_extra'); ?>
            <?php submit_button('Salvar configurações'); ?>
        </form>
    </div>
    <?php
}
