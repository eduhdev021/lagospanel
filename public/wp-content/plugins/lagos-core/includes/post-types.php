<?php
/**
 * LagosPanel Core — Tipos de post (produtos, serviços, faturas, tickets)
 */
if (!defined('ABSPATH')) exit;

function lagos_register_post_types() {

    // ===== Produtos (loja) =====
    register_post_type('lagos_product', [
        'labels' => [
            'name' => 'Produtos', 'singular_name' => 'Produto',
            'add_new_item' => 'Adicionar novo produto', 'edit_item' => 'Editar produto',
            'all_items' => 'Produtos',
        ],
        'public' => true, 'show_ui' => true, 'show_in_menu' => 'lagospanel',
        'has_archive' => false, 'rewrite' => ['slug' => 'loja', 'with_front' => false],
        'supports' => ['title', 'editor', 'excerpt'],
        'menu_icon' => 'dashicons-cart',
        'map_meta_cap' => true,
        'capability_type' => 'post', 'capabilities' => ['create_posts' => 'do_not_allow'],
    ]);

    register_taxonomy('lagos_cat', 'lagos_product', [
        'labels' => ['name' => 'Categorias', 'singular_name' => 'Categoria'],
        'public' => true, 'hierarchical' => true,
        'rewrite' => ['slug' => 'loja/categoria', 'with_front' => false],
        'show_admin_column' => true,
    ]);

    // ===== Serviços (contratados pelos clientes) =====
    register_post_type('lagos_service', [
        'labels' => [
            'name' => 'Serviços', 'singular_name' => 'Serviço',
            'add_new_item' => 'Adicionar novo serviço', 'edit_item' => 'Editar serviço',
            'all_items' => 'Serviços',
        ],
        'public' => false, 'show_ui' => true, 'show_in_menu' => 'lagospanel',
        'supports' => ['title'],
        'menu_icon' => 'dashicons-cloud',
    ]);

    // ===== Faturas =====
    register_post_type('lagos_invoice', [
        'labels' => [
            'name' => 'Faturas', 'singular_name' => 'Fatura',
            'add_new_item' => 'Adicionar nova fatura', 'edit_item' => 'Editar fatura',
            'all_items' => 'Faturas',
        ],
        'public' => false, 'show_ui' => true, 'show_in_menu' => 'lagospanel',
        'supports' => ['title'],
        'menu_icon' => 'dashicons-media-spreadsheet',
    ]);

    // ===== Downloads =====
    register_post_type('lagos_download', [
        'labels' => [
            'name' => 'Downloads', 'singular_name' => 'Download',
            'add_new_item' => 'Adicionar novo download', 'edit_item' => 'Editar download',
            'all_items' => 'Downloads',
        ],
        'public' => false, 'show_ui' => true, 'show_in_menu' => 'lagospanel',
        'supports' => ['title', 'excerpt'],
        'menu_icon' => 'dashicons-download',
    ]);

    // ===== Incidentes (status da rede) =====
    register_post_type('lagos_incident', [
        'labels' => [
            'name' => 'Incidentes', 'singular_name' => 'Incidente',
            'add_new_item' => 'Novo incidente', 'edit_item' => 'Editar incidente',
            'all_items' => 'Incidentes',
        ],
        'public' => false, 'show_ui' => true, 'show_in_menu' => 'lagospanel',
        'supports' => ['title', 'editor'],
        'menu_icon' => 'dashicons-warning',
    ]);

    // ===== Base de conhecimento =====
    register_post_type('lagos_kb', [
        'labels' => [
            'name' => 'Base de conhecimento', 'singular_name' => 'Artigo',
            'add_new_item' => 'Adicionar novo artigo', 'edit_item' => 'Editar artigo',
            'all_items' => 'Base de conhecimento',
        ],
        'public' => true, 'show_ui' => true, 'show_in_menu' => 'lagospanel',
        'has_archive' => false, 'rewrite' => ['slug' => 'artigo', 'with_front' => false],
        'supports' => ['title', 'editor', 'excerpt'],
        'menu_icon' => 'dashicons-book',
    ]);

    register_taxonomy('lagos_kbcat', 'lagos_kb', [
        'labels' => ['name' => 'Categorias de KB', 'singular_name' => 'Categoria de KB'],
        'public' => false, 'hierarchical' => true, 'show_ui' => true,
        'show_admin_column' => true,
    ]);

    // ===== Tickets de suporte =====
    register_post_type('lagos_ticket', [
        'labels' => [
            'name' => 'Tickets', 'singular_name' => 'Ticket',
            'add_new_item' => 'Novo ticket', 'edit_item' => 'Editar ticket',
            'all_items' => 'Tickets',
        ],
        'public' => false, 'show_ui' => true, 'show_in_menu' => 'lagospanel',
        'supports' => ['title', 'editor', 'comments'],
        'menu_icon' => 'dashicons-sos',
    ]);
}
add_action('init', 'lagos_register_post_types');

/* =========================================================
   Meta boxes (admin)
   ========================================================= */

function lagos_meta_boxes() {
    add_meta_box('lagos_service_box', 'Dados do serviço', 'lagos_service_box', 'lagos_service', 'normal', 'high');
    add_meta_box('lagos_invoice_box', 'Dados da fatura', 'lagos_invoice_box', 'lagos_invoice', 'normal', 'high');
    add_meta_box('lagos_ticket_box', 'Dados do ticket', 'lagos_ticket_box', 'lagos_ticket', 'side', 'high');
    add_meta_box('lagos_product_box', 'Dados do produto (loja)', 'lagos_product_box', 'lagos_product', 'normal', 'high');
    add_meta_box('lagos_coupon_box', 'Dados do cupom', 'lagos_coupon_box', 'lagos_coupon', 'normal', 'high');
    add_meta_box('lagos_download_box', 'Arquivo', 'lagos_download_box', 'lagos_download', 'normal', 'high');
    add_meta_box('lagos_incident_box', 'Dados do incidente', 'lagos_incident_box', 'lagos_incident', 'side', 'high');
}
add_action('add_meta_boxes', 'lagos_meta_boxes');

function lagos_users_dropdown($selected = 0) {
    $users = get_users(['fields' => ['ID', 'display_name', 'user_email'], 'number' => 500]);
    echo '<select name="lagos_user" style="width:100%">';
    echo '<option value="">— cliente —</option>';
    foreach ($users as $u) {
        printf('<option value="%d"%s>%s (%s)</option>', $u->ID, selected($selected, $u->ID, false), esc_html($u->display_name), esc_html($u->user_email));
    }
    echo '</select>';
}

function lagos_nonce_field($action) {
    wp_nonce_field('lagos_meta_' . $action, 'lagos_meta_nonce');
}

function lagos_service_box($post) {
    lagos_nonce_field('service');
    $st  = get_post_meta($post->ID, '_lagos_status', true) ?: 'pending';
    $pr  = get_post_meta($post->ID, '_lagos_price', true);
    $cy  = get_post_meta($post->ID, '_lagos_cycle', true) ?: 'monthly';
    $due = get_post_meta($post->ID, '_lagos_next_due', true);
    $usr = get_post_meta($post->ID, '_lagos_user', true);
    echo '<p><label><strong>Cliente</strong></label>'; lagos_users_dropdown($usr); echo '</p>';
    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">';
    echo '<p><label><strong>Status</strong></label><select name="lagos_status" style="width:100%">';
    foreach (['active', 'pending', 'suspended', 'cancelled'] as $s) {
        printf('<option value="%s"%s>%s</option>', $s, selected($st, $s, false), ucfirst($s));
    }
    echo '</select></p>';
    echo '<p><label><strong>Ciclo</strong></label><select name="lagos_cycle" style="width:100%">';
    foreach (['monthly' => 'Mensal', 'yearly' => 'Anual', 'one_time' => 'Único'] as $k => $lbl) {
        printf('<option value="%s"%s>%s</option>', $k, selected($cy, $k, false), $lbl);
    }
    echo '</select></p>';
    printf('<p><label><strong>Valor (R$)</strong></label><input type="number" step="0.01" name="lagos_price" value="%s" class="widefat"></p>', esc_attr($pr));
    printf('<p><label><strong>Próxima renovação</strong></label><input type="date" name="lagos_next_due" value="%s" class="widefat"></p>', esc_attr($due));
    echo '</div>';
}

function lagos_invoice_box($post) {
    lagos_nonce_field('invoice');
    $st    = get_post_meta($post->ID, '_lagos_status', true) ?: 'unpaid';
    $am    = get_post_meta($post->ID, '_lagos_amount', true);
    $due   = get_post_meta($post->ID, '_lagos_due', true);
    $usr   = get_post_meta($post->ID, '_lagos_user', true);
    $items = get_post_meta($post->ID, '_lagos_items', true);
    echo '<p><label><strong>Cliente</strong></label>'; lagos_users_dropdown($usr); echo '</p>';
    echo '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">';
    echo '<p><label><strong>Status</strong></label><select name="lagos_status" style="width:100%">';
    foreach (['unpaid', 'paid', 'overdue', 'refunded'] as $s) {
        printf('<option value="%s"%s>%s</option>', $s, selected($st, $s, false), ucfirst($s));
    }
    echo '</select></p>';
    printf('<p><label><strong>Total (R$)</strong></label><input type="number" step="0.01" name="lagos_amount" value="%s" class="widefat"></p>', esc_attr($am));
    printf('<p><label><strong>Vencimento</strong></label><input type="date" name="lagos_due" value="%s" class="widefat"></p>', esc_attr($due));
    echo '</div>';
    printf('<p><label><strong>Itens</strong> <em>(um por linha: Descrição | qtd | valor)</em></label><textarea name="lagos_items" rows="4" class="widefat">%s</textarea></p>', esc_textarea($items));
}

function lagos_ticket_box($post) {
    lagos_nonce_field('ticket');
    $st  = get_post_meta($post->ID, '_lagos_status', true) ?: 'open';
    $dp  = get_post_meta($post->ID, '_lagos_dept', true) ?: 'general';
    $pr  = get_post_meta($post->ID, '_lagos_priority', true) ?: 'medium';
    $usr = get_post_meta($post->ID, '_lagos_user', true);
    echo '<p><label><strong>Cliente</strong></label>'; lagos_users_dropdown($usr); echo '</p>';
    echo '<p><label><strong>Status</strong></label><select name="lagos_status" style="width:100%">';
    foreach (['open', 'answered', 'customer_reply', 'closed'] as $s) {
        printf('<option value="%s"%s>%s</option>', $s, selected($st, $s, false), ucfirst($s));
    }
    echo '</select></p>';
    echo '<p><label><strong>Departamento</strong></label><select name="lagos_dept" style="width:100%">';
    foreach (['general', 'billing', 'tech'] as $d) {
        printf('<option value="%s"%s>%s</option>', $d, selected($dp, $d, false), ucfirst($d));
    }
    echo '</select></p>';
    echo '<p><label><strong>Prioridade</strong></label><select name="lagos_priority" style="width:100%">';
    foreach (['low', 'medium', 'high'] as $p) {
        printf('<option value="%s"%s>%s</option>', $p, selected($pr, $p, false), ucfirst($p));
    }
    echo '</select></p>';
}

function lagos_product_box($post) {
    lagos_nonce_field('product');
    $pr  = get_post_meta($post->ID, '_lagos_price', true);
    $cy  = get_post_meta($post->ID, '_lagos_cycle', true) ?: 'monthly';
    $ft  = get_post_meta($post->ID, '_lagos_featured', true);
    $fl  = get_post_meta($post->ID, '_lagos_features', true);
    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">';
    printf('<p><label><strong>Preço (R$)</strong></label><input type="number" step="0.01" name="lagos_price" value="%s" class="widefat"></p>', esc_attr($pr));
    echo '<p><label><strong>Ciclo</strong></label><select name="lagos_cycle" style="width:100%">';
    foreach (['monthly' => 'Mensal', 'yearly' => 'Anual', 'one_time' => 'Único'] as $k => $lbl) {
        printf('<option value="%s"%s>%s</option>', $k, selected($cy, $k, false), $lbl);
    }
    echo '</select></p>';
    echo '</div>';
    printf('<p><label><strong>Recursos</strong> <em>(um por linha)</em></label><textarea name="lagos_features" rows="5" class="widefat">%s</textarea></p>', esc_textarea($fl));
    printf('<p><label><strong>Opções configuráveis</strong> <em>(uma por linha: Nome | tipo | escolha=acréscimo;escolha=acréscimo — tipos: select, radio, slider, text)</em></label><textarea name="lagos_options" rows="3" class="widefat" placeholder="Memória RAM (GB) | slider | 2=0;4=20;8=60&#10;Backup extra | select | Não=0;Sim=9.90">%s</textarea></p>', esc_textarea(get_post_meta($post->ID, '_lagos_options', true)));
    // Módulo de provisionamento (Pterodactyl, cPanel, aaPanel, Virtualizor, DNS...)
    if (function_exists('lagos_modules')) {
        $mods = lagos_modules();
        $cur  = get_post_meta($post->ID, '_lagos_module', true);
        $types = lagos_module_types();
        echo '<p><label><strong> Módulo de provisionamento</strong> <em>(executado quando a fatura é paga)</em></label>';
        echo '<select name="lagos_module" style="width:100%">';
        echo '<option value="">— nenhum (ativação manual) —</option>';
        foreach ($mods as $mid => $m) {
            printf('<option value="%s"%s>%s — %s</option>', esc_attr($mid), selected($cur, $mid, false), esc_html($m['name']), esc_html($types[$m['type']]['label'] ?? $m['type']));
        }
        echo '</select></p>';
        printf('<p><label><strong>Parâmetros do módulo (JSON)</strong> <em>ex.: {"egg": 5, "memory": 2048}</em></label><textarea name="lagos_module_extra" rows="2" class="widefat" placeholder="{}">%s</textarea></p>', esc_textarea(get_post_meta($post->ID, '_lagos_module_extra', true)));
    }
    echo '<p><label><input type="checkbox" name="lagos_featured" value="1"' . checked($ft, '1', false) . '> <strong>Destaque</strong> (aparece na home)</label></p>';
}

function lagos_coupon_box($post) {
    lagos_nonce_field('product'); // reutiliza o nonce do produto
    $type   = get_post_meta($post->ID, '_lagos_type', true) ?: 'percent';
    $value  = get_post_meta($post->ID, '_lagos_value', true);
    $active = get_post_meta($post->ID, '_lagos_active', true);
    echo '<p><strong>O título é o código do cupom</strong> (ex.: <code>LAGOS10</code>).</p>';
    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">';
    echo '<p><label><strong>Tipo de desconto</strong></label><select name="lagos_type" style="width:100%">';
    printf('<option value="percent"%s>Percentual (%%)</option>', selected($type, 'percent', false));
    printf('<option value="fixed"%s>Valor fixo (R$)</option>', selected($type, 'fixed', false));
    echo '</select></p>';
    printf('<p><label><strong>Valor</strong></label><input type="number" step="0.01" name="lagos_value" value="%s" class="widefat"></p>', esc_attr($value));
    echo '</div>';
    echo '<p><label><input type="checkbox" name="lagos_active" value="1"' . checked($active, '1', false) . '> <strong>Ativo</strong></label></p>';
}

function lagos_download_box($post) {
    lagos_nonce_field('product');
    printf('<p><label><strong>URL do arquivo</strong></label><input type="url" name="lagos_file" class="widefat" value="%s" placeholder="https://... ou /wp-content/uploads/..."></p>', esc_url(get_post_meta($post->ID, '_lagos_file', true)));
    echo '<p class="text-muted">Envie o arquivo em Mídia → Adicionar novo e cole a URL aqui.</p>';
}

function lagos_incident_box($post) {
    lagos_nonce_field('product');
    $st  = get_post_meta($post->ID, '_lagos_inc_status', true) ?: 'investigating';
    $comp = get_post_meta($post->ID, '_lagos_inc_component', true);
    echo '<p><label><strong>Status</strong></label><select name="lagos_inc_status" style="width:100%">';
    foreach (['investigating' => 'Investigando', 'monitoring' => 'Monitorando', 'resolved' => 'Resolvido'] as $k => $l) {
        printf('<option value="%s"%s>%s</option>', $k, selected($st, $k, false), $l);
    }
    echo '</select></p>';
    echo '<p><label><strong>Componente afetado (opcional)</strong></label><select name="lagos_inc_component" style="width:100%">';
    echo '<option value="">— nenhum —</option>';
    if (function_exists('lagos_modules')) {
        foreach (lagos_modules() as $id => $m) {
            printf('<option value="%s"%s>%s</option>', esc_attr($id), selected($comp, $id, false), esc_html($m['name']));
        }
    }
    echo '</select></p>';
}

function lagos_save_meta($post_id) {
    if (!isset($_POST['lagos_meta_nonce']) || !wp_verify_nonce($_POST['lagos_meta_nonce'], 'lagos_meta_service') && !wp_verify_nonce($_POST['lagos_meta_nonce'], 'lagos_meta_invoice') && !wp_verify_nonce($_POST['lagos_meta_nonce'], 'lagos_meta_ticket') && !wp_verify_nonce($_POST['lagos_meta_nonce'], 'lagos_meta_product')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $fields = ['lagos_user', 'lagos_status', 'lagos_price', 'lagos_cycle', 'lagos_next_due', 'lagos_amount', 'lagos_due', 'lagos_items', 'lagos_dept', 'lagos_priority', 'lagos_features', 'lagos_type', 'lagos_value', 'lagos_module', 'lagos_module_extra', 'lagos_file', 'lagos_inc_status', 'lagos_inc_component'];
    foreach ($fields as $f) {
        if (isset($_POST[$f])) update_post_meta($post_id, '_' . $f, sanitize_text_field(wp_unslash($_POST[$f])));
    }
    if (isset($_POST['lagos_status']) && in_array($_POST['lagos_status'], ['active', 'pending', 'suspended', 'cancelled', 'unpaid', 'paid', 'overdue', 'refunded', 'open', 'answered', 'customer_reply', 'closed'], true)) {
        update_post_meta($post_id, '_lagos_status', sanitize_key($_POST['lagos_status']));
    }
    update_post_meta($post_id, '_lagos_featured', isset($_POST['lagos_featured']) ? '1' : '');
    if (isset($_POST['lagos_options'])) update_post_meta($post_id, '_lagos_options', sanitize_textarea_field(wp_unslash($_POST['lagos_options'])));
}
add_action('save_post', 'lagos_save_meta');
