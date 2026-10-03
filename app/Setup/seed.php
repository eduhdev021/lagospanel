<?php
/**
 * LagosPanel Core — Dados de demonstração
 */
if (!defined('ABSPATH')) exit;

function lagos_seed() {
    if (function_exists('lagos_panel_mode') && lagos_panel_mode() === 'production') return; // produção: sem dados de exemplo
    if (get_option('lagos_seeded') === LAGOS_CORE_VERSION) return;

    // ===== Categorias =====
    $cats = [
        'hospedagem' => 'Hospedagem',
        'vps'        => 'VPS & Cloud',
        'dominios'   => 'Domínios',
        'ssl'        => 'Certificados SSL',
        'jogos'      => 'Servidores de Jogo',
    ];
    foreach ($cats as $slug => $name) {
        if (!term_exists($slug, 'lagos_cat')) {
            wp_insert_term($name, 'lagos_cat', ['slug' => $slug]);
        }
    }

    // ===== Produtos =====
    $products = [
        ['Lagos Start', 'hospedagem', 9.90, 'monthly', 1,
         "Hospedagem compartilhada perfeita para começar.\n1 site hospedado\n10 GB NVMe SSD\nCertificado SSL grátis\nE-mails ilimitados\nBackup diário"],
        ['Lagos Pro', 'hospedagem', 19.90, 'monthly', 0,
         "Para quem quer mais desempenho.\n5 sites hospedados\n50 GB NVMe SSD\nCertificado SSL grátis\nCDN incluída\nSuporte prioritário"],
        ['VPS Lagos Cloud 2 GB', 'vps', 34.90, 'monthly', 0,
         "Servidor virtual com NVMe.\n2 vCPU dedicados\n2 GB de RAM\n60 GB NVMe SSD\n1 TB de tráfego\nIPv4 dedicado"],
        ['VPS Lagos Cloud 4 GB', 'vps', 59.90, 'monthly', 0,
         "Mais poder para aplicações.\n4 vCPU dedicados\n4 GB de RAM\n120 GB NVme SSD\n2 TB de tráfego\nIPv4 dedicado"],
        ['Domínio .com', 'dominios', 39.90, 'yearly', 0,
         "Registre seu endereço na web.\nRegistro por 1 ano\nDNS gerenciado\nWhois privativo\nTransferência fácil"],
        ['Certificado SSL Plus', 'ssl', 49.90, 'yearly', 0,
         "Selo de confiança para seu site.\nCriptografia 256 bits\nSelo no navegador\nReemissão grátis\nCompatível com todos os browsers"],
        ['Servidor Minecraft Start', 'jogos', 29.90, 'monthly', 0,
         "Servidor Minecraft com Pterodactyl.\n2 GB de RAM dedicados\nProteção anti-DDoS\nAcesso ao console web\nInstala em segundos"],
    ];
    $product_ids = [];
    foreach ($products as $i => [$title, $cat, $price, $cycle, $featured, $features]) {
        $existing = get_page_by_title($title, OBJECT, 'lagos_product');
        if ($existing) { $product_ids[$title] = $existing->ID; continue; }
        $pid = wp_insert_post([
            'post_type'    => 'lagos_product',
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_content' => '<p>Plano <strong>' . $title . '</strong> da LagosPanel. Recursos premium, servidores no Brasil e suporte humanizado 24/7 pela companhia Lagos.</p>',
            'post_excerpt' => 'Plano ' . $title . ' — infraestrutura premium e suporte 24/7.',
            'menu_order'   => $i,
        ]);
        if (!$pid || is_wp_error($pid)) continue;
        update_post_meta($pid, '_lagos_price', $price);
        update_post_meta($pid, '_lagos_cycle', $cycle);
        update_post_meta($pid, '_lagos_featured', $featured ? '1' : '');
        update_post_meta($pid, '_lagos_features', $features);
        wp_set_object_terms($pid, [$cat], 'lagos_cat');
        $product_ids[$title] = $pid;
    }

    // ===== Base de conhecimento =====
    $kb_cats = ['comecando' => 'Começando', 'pagamentos' => 'Pagamentos', 'seguranca' => 'Segurança', 'suporte' => 'Suporte'];
    foreach ($kb_cats as $slug => $name) {
        if (!term_exists($slug, 'lagos_kbcat')) wp_insert_term($name, 'lagos_kbcat', ['slug' => $slug]);
    }
    $kb_articles = [
        ['Como pagar minha fatura com Pix', 'pagamentos', "<p>Depois de contratar um serviço, a fatura fica disponível em <strong>Faturas</strong>. Clique em <strong>Pix</strong>, escaneie o QR Code ou copie o código copia-e-cola no app do seu banco. O valor é confirmado automaticamente e o serviço ativado em instantes.</p>"],
        ['Como ativar a autenticação em dois fatores', 'seguranca', "<p>Em <strong>Perfil → Autenticação em dois fatores</strong>, clique em <strong>Ativar</strong>. Adicione a chave exibida no seu aplicativo autenticador (Google Authenticator, Authy, 1Password) e confirme com o código de 6 dígitos gerado.</p><p>A partir daí, todo login exigirá o código do aplicativo.</p>"],
        ['Como funciona a recarga de saldo', 'pagamentos', "<p>Em <strong>Perfil → Adicionar saldo</strong>, informe o valor (mínimo R$ 10) e clique em <strong>Gerar fatura</strong>. Após o pagamento via Pix, o valor é creditado automaticamente na sua carteira e pode ser usado para pagar faturas futuras.</p>"],
        ['Gerenciando seus serviços', 'comecando', "<p>Na área <strong>Serviços</strong> você acompanha o status de cada contratação, a renovação automática e o provisionamento no servidor. Clique em <strong>Gerenciar</strong> para ver detalhes, alterar a renovação automática ou solicitar cancelamento.</p>"],
        ['Como abrir um bom ticket de suporte', 'suporte', "<p>Para agilizar o atendimento: descreva o problema com detalhes, informe o serviço afetado, inclua mensagens de erro e os passos para reproduzir. Prefira o departamento <strong>Técnico</strong> para problemas e <strong>Financeiro</strong> para questões de cobrança.</p>"],
        ['O que é provisionamento automático', 'comecando', "<p>Quando uma fatura é paga, o LagosPanel provisiona o serviço automaticamente no painel correspondente — cPanel para hospedagem, Virtualizor para VPS, Pterodactyl para servidores de jogo e Cloudflare para zonas DNS. O status aparece na coluna <strong>Servidor</strong> em Serviços.</p>"],
    ];
    foreach ($kb_articles as $i => [$title, $cat, $content]) {
        if (get_page_by_path(sanitize_title($title), OBJECT, 'lagos_kb')) continue;
        $aid = wp_insert_post([
            'post_type' => 'lagos_kb', 'post_status' => 'publish',
            'post_title' => $title, 'post_content' => $content, 'menu_order' => $i,
        ]);
        if ($aid && !is_wp_error($aid)) wp_set_object_terms($aid, [$cat], 'lagos_kbcat');
    }

    // ===== Downloads demo (arquivos reais em uploads/lagos) =====
    $updir = wp_upload_dir();
    $lagos_dir = trailingslashit($updir['basedir']) . 'lagos';
    if (!is_dir($lagos_dir)) wp_mkdir_p($lagos_dir);
    $files = [
        ['Guia de configuracao DNS.txt', "GUIA DE CONFIGURACAO DNS\n\n== LagosPanel ==\n\n1. Acesse o painel da Lagos\n2. Va em Servicos > Gerenciar\n3. Configure os nameservers:\n   ns1.lagos.com.br\n   ns2.lagos.com.br\n\nDuvidas? Abra um ticket em Suporte.", 'Passo a passo para apontar seu domínio para os servidores da Lagos.'],
        ['Checklist de seguranca.txt', "CHECKLIST DE SEGURANCA\n\n== LagosPanel ==\n\n[ ] Ativar 2FA no Perfil\n[ ] Senha forte e unica\n[ ] Chave de API guardada com segredo\n[ ] Revisar sessoes ativas periodicamente\n\nFeito isso, sua conta esta muito mais segura!", 'Checklist recomendado para manter sua conta segura.'],
        ['Como pagar via Pix.txt', "PAGAMENTO VIA PIX\n\n== LagosPanel ==\n\n1. Va em Faturas\n2. Clique no botao Pix\n3. Escaneie o QR Code ou copie o codigo\n4. Confirme no app do banco\n\nO valor e confirmado automaticamente em segundos.", 'Instruções completas para pagar suas faturas com Pix.'],
    ];
    foreach ($files as $i => [$fname, $content, $desc]) {
        $fpath = $lagos_dir . '/' . $fname;
        if (!file_exists($fpath)) file_put_contents($fpath, $content);
        $furl = trailingslashit($updir['baseurl']) . 'lagos/' . rawurlencode($fname);
        if (!get_page_by_title($fname, OBJECT, 'lagos_download')) {
            $did = wp_insert_post([
                'post_type' => 'lagos_download', 'post_status' => 'publish',
                'post_title' => str_replace('.txt', '', $fname), 'post_excerpt' => $desc, 'menu_order' => $i,
            ]);
            if ($did && !is_wp_error($did)) update_post_meta($did, '_lagos_file', $furl);
        }
    }

    // ===== Incidente demo (resolvido) =====
    if (!get_posts(['post_type' => 'lagos_incident', 'numberposts' => 1])) {
        $iid = wp_insert_post([
            'post_type' => 'lagos_incident', 'post_status' => 'publish',
            'post_title' => 'Latência elevada no cluster de hospedagem BR',
            'post_content' => 'Identificamos latência elevada no cluster de hospedagem entre 02:10 e 03:40 (BRT). A causa foi um pico de tráfego em um dos uplinks. O tráfego foi redirecionado e a latência voltou ao normal.',
        ]);
        if ($iid && !is_wp_error($iid)) {
            update_post_meta($iid, '_lagos_inc_status', 'resolved');
            update_post_meta($iid, '_lagos_inc_component', 'm1');
        }
    }

    // ===== Conexões de provisionamento (demo, modo simulado) =====
    if (!get_option('lagos_modules')) {
        update_option('lagos_modules', [
            'm1' => ['name' => 'cPanel — Cluster BR',   'type' => 'cpanel',        'host' => 'https://whm-cluster.lagos.internal:2087', 'creds' => ['host' => 'https://whm-cluster.lagos.internal:2087', 'whm_user' => 'lagos', 'api_token' => ''], 'simulate' => 1],
            'm2' => ['name' => 'Pterodactyl — Game BR',  'type' => 'pterodactyl',   'host' => 'https://ptero.lagos.internal',            'creds' => ['host' => 'https://ptero.lagos.internal', 'api_key' => ''], 'simulate' => 1],
            'm3' => ['name' => 'Virtualizor — Node BR1', 'type' => 'virtualizor',   'host' => 'https://vz-br1.lagos.internal:4085',       'creds' => ['host' => 'https://vz-br1.lagos.internal:4085', 'api_key' => '', 'api_pass' => ''], 'simulate' => 1],
            'm4' => ['name' => 'DNS Cloudflare',         'type' => 'cloudflare_dns','host' => 'api.cloudflare.com',                      'creds' => ['api_token' => '', 'account_id' => ''], 'simulate' => 1],
            'm5' => ['name' => 'aaPanel — Web BR',       'type' => 'aapanel',       'host' => 'http://aapanel.lagos.internal:8888',       'creds' => ['host' => 'http://aapanel.lagos.internal:8888', 'token' => ''], 'simulate' => 1],
        ]);
    }

    // ===== Vínculo produto → módulo =====
    $attach = [
        'Lagos Start'             => 'm1', // cPanel
        'Lagos Pro'               => 'm1', // cPanel
        'VPS Lagos Cloud 2 GB'    => 'm3', // Virtualizor
        'VPS Lagos Cloud 4 GB'    => 'm3', // Virtualizor
        'Domínio .com'            => 'm4', // Cloudflare DNS
        'Servidor Minecraft Start'=> 'm2', // Pterodactyl
    ];
    foreach ($attach as $title => $mid) {
        if (isset($product_ids[$title]) && !get_post_meta($product_ids[$title], '_lagos_module', true)) {
            update_post_meta($product_ids[$title], '_lagos_module', $mid);
        }
    }

    // ===== Cliente demo =====
    $client_id = username_exists('cliente@lagos.com');
    if (!$client_id) {
        $client_id = wp_insert_user([
            'user_login'   => 'cliente@lagos.com',
            'user_email'   => 'cliente@lagos.com',
            'user_pass'    => 'cliente123',
            'display_name' => 'Maria Oliveira',
            'first_name'   => 'Maria',
            'role'         => 'lagos_client',
        ]);
        update_user_meta($client_id, 'lagos_balance', 150.00);
        lagos_aff_code($client_id);
        lagos_api_key($client_id);
    }

    // ===== Serviços demo =====
    if (!get_posts(['post_type' => 'lagos_service', 'numberposts' => 1, 'post_status' => 'any'])) {
        $demo_services = [
            // [título, status, preço, ciclo, renovação, módulo, id remoto]
            ['Lagos Start — meusite.com.br', 'active', 9.90, 'monthly', '+18 days', 'm1', 'cpanel-lg-10241'],
            ['VPS Lagos Cloud 2 GB', 'active', 34.90, 'monthly', '+12 days', 'm3', 'vz-br1-2048'],
            ['Domínio .com — meusite.com.br', 'pending', 39.90, 'yearly', '+30 days', 'm4', ''],
        ];
        $svc_ids = [];
        foreach ($demo_services as [$title, $st, $price, $cycle, $due, $mod, $remote]) {
            $sid = wp_insert_post(['post_type' => 'lagos_service', 'post_status' => 'publish', 'post_title' => $title]);
            update_post_meta($sid, '_lagos_user', $client_id);
            update_post_meta($sid, '_lagos_status', $st);
            update_post_meta($sid, '_lagos_price', $price);
            update_post_meta($sid, '_lagos_cycle', $cycle);
            update_post_meta($sid, '_lagos_next_due', date('Y-m-d', strtotime($due)));
            // produto vinculado + status de provisionamento
            foreach ($product_ids as $pt => $ppid) {
                if (strpos($title, $pt) === 0) { update_post_meta($sid, '_lagos_product', $ppid); break; }
            }
            if ($mod) {
                update_post_meta($sid, '_lagos_module_status', $st === 'active' ? 'active' : 'provisioning');
                if ($remote) update_post_meta($sid, '_lagos_module_remote', $remote);
            }
            $svc_ids[] = $sid;
        }

        // ===== Faturas demo =====
        $demo_invoices = [
            ['FAT-1001', 'paid', 9.90, '-12 days', 'Lagos Start — meusite.com.br | 1 | 9,90', $svc_ids[0] ?? 0],
            ['FAT-1002', 'unpaid', 34.90, '+6 days', 'VPS Lagos Cloud 2 GB | 1 | 34,90', $svc_ids[1] ?? 0],
            ['FAT-1003', 'overdue', 39.90, '-3 days', 'Domínio .com — meusite.com.br | 1 | 39,90', $svc_ids[2] ?? 0],
        ];
        foreach ($demo_invoices as [$title, $st, $amount, $due, $items, $svc]) {
            $iid = wp_insert_post(['post_type' => 'lagos_invoice', 'post_status' => 'publish', 'post_title' => $title]);
            update_post_meta($iid, '_lagos_user', $client_id);
            update_post_meta($iid, '_lagos_status', $st);
            update_post_meta($iid, '_lagos_amount', $amount);
            update_post_meta($iid, '_lagos_due', date('Y-m-d', strtotime($due)));
            update_post_meta($iid, '_lagos_items', $items);
            update_post_meta($iid, '_lagos_service', $svc);
        }

        // ===== Tickets demo =====
        $t1 = wp_insert_post([
            'post_type' => 'lagos_ticket', 'post_status' => 'publish',
            'post_title' => 'Site lento desde ontem',
            'post_content' => 'Olá! Meu site (meusite.com.br) está demorando bastante para carregar desde ontem à noite. Podem verificar?',
        ]);
        update_post_meta($t1, '_lagos_user', $client_id);
        update_post_meta($t1, '_lagos_status', 'open');
        update_post_meta($t1, '_lagos_dept', 'tech');
        update_post_meta($t1, '_lagos_priority', 'high');

        $t2 = wp_insert_post([
            'post_type' => 'lagos_ticket', 'post_status' => 'publish',
            'post_title' => 'Dúvida sobre a fatura FAT-1002',
            'post_content' => 'Oi! A fatura FAT-1002 pode ser paga via Pix? Obrigada!',
        ]);
        update_post_meta($t2, '_lagos_user', $client_id);
        update_post_meta($t2, '_lagos_status', 'answered');
        update_post_meta($t2, '_lagos_dept', 'billing');
        update_post_meta($t2, '_lagos_priority', 'medium');
        wp_insert_comment([
            'comment_post_ID'      => $t2,
            'comment_content'      => 'Olá Maria! Sim, assim que o gateway Pix estiver ativo você conseguirá pagar direto pelo painel. Enquanto isso, o botão "Já paguei" registra o pagamento em modo demonstração. ',
            'comment_approved'     => 1,
            'comment_author'       => 'Equipe Lagos',
            'comment_author_email' => 'suporte@lagos.com.br',
            'comment_date'         => current_time('mysql'),
        ]);
    }

    // ===== Cupons demo =====
    foreach ([['LAGOS10', 'percent', 10], ['BOASVINDAS5', 'fixed', 5]] as [$code, $type, $value]) {
        if (!get_posts(['post_type' => 'lagos_coupon', 'post_status' => 'publish', 'numberposts' => 1, 'name' => sanitize_title($code)])) {
            $cid = wp_insert_post(['post_type' => 'lagos_coupon', 'post_status' => 'publish', 'post_title' => $code]);
            if ($cid && !is_wp_error($cid)) {
                update_post_meta($cid, '_lagos_type', $type);
                update_post_meta($cid, '_lagos_value', $value);
                update_post_meta($cid, '_lagos_active', '1');
            }
        }
    }

    // ===== Novidade =====
    if (!get_posts(['numberposts' => 1, 'post_status' => 'publish'])) {
        wp_insert_post([
            'post_status' => 'publish',
            'post_title'  => 'LagosPanel 0.1 no ar! ',
            'post_content' => "<p>A primeira versão do painel da <strong>Lagos Soluções</strong> está online!</p><p>Agora você pode gerenciar seus serviços, faturas e tickets em um só lugar — em português e inglês. Em breve: gateway Pix real, 2FA e API pública.</p>",
        ]);
    }

    // ===== Configurações padrão =====
    add_option('lagos_company_name', 'Lagos Soluções');
    add_option('lagos_support_email', 'suporte@lagos.com.br');
    add_option('lagos_currency', 'BRL');

    update_option('lagos_seeded', LAGOS_CORE_VERSION);
}
