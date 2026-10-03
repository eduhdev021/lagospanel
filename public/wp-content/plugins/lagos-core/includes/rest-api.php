<?php
/**
 * LagosPanel Core — API REST v1
 * Base: /wp-json/lagos/v1
 * Autenticação: header X-Lagos-Key (chave de API do cliente, ver Perfil)
 */
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
    // Público
    register_rest_route('lagos/v1', '/products', [
        'methods'  => 'GET',
        'callback' => 'lagos_api_products',
        'permission_callback' => '__return_true',
    ]);

    // Autenticados
    register_rest_route('lagos/v1', '/me', [
        'methods'  => 'GET',
        'callback' => 'lagos_api_me',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('lagos/v1', '/services', [
        'methods'  => 'GET',
        'callback' => 'lagos_api_services',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('lagos/v1', '/invoices', [
        'methods'  => 'GET',
        'callback' => 'lagos_api_invoices',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('lagos/v1', '/tickets', [
        'methods'  => 'GET',
        'callback' => 'lagos_api_tickets',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('lagos/v1', '/tickets', [
        'methods'  => 'POST',
        'callback' => 'lagos_api_tickets_create',
        'permission_callback' => '__return_true',
    ]);
});

/** Localiza o usuário pela chave de API (header X-Lagos-Key ou ?key=) */
function lagos_api_user() {
    $key = $_SERVER['HTTP_X_LAGOS_KEY'] ?? '';
    if (!$key && !empty($_GET['key'])) $key = $_GET['key'];
    $key = trim((string) $key);
    if (strlen($key) < 16) return null;

    $users = get_users([
        'meta_key'   => 'lagos_api_key',
        'meta_value' => $key,
        'number'     => 1,
        'fields'     => ['ID'],
    ]);
    if (!$users) return null;
    return get_userdata($users[0]->ID);
}

function lagos_api_err() {
    return new WP_Error('lagos_unauthorized', 'Chave de API inválida ou ausente. Envie o header X-Lagos-Key.', ['status' => 401]);
}

function lagos_api_products() {
    $out = [];
    foreach (lagos_get_products() as $p) {
        $terms = get_the_terms($p->ID, 'lagos_cat');
        $out[] = [
            'id'       => $p->ID,
            'name'     => $p->post_title,
            'slug'     => $p->post_name,
            'price'    => (float) get_post_meta($p->ID, '_lagos_price', true),
            'currency' => 'BRL',
            'cycle'    => get_post_meta($p->ID, '_lagos_cycle', true) ?: 'monthly',
            'category' => ($terms && !is_wp_error($terms)) ? $terms[0]->name : null,
            'url'      => get_permalink($p->ID),
        ];
    }
    return rest_ensure_response(['success' => true, 'data' => $out]);
}

function lagos_api_me() {
    $u = lagos_api_user();
    if (!$u) return lagos_api_err();
    return rest_ensure_response(['success' => true, 'data' => [
        'id'          => $u->ID,
        'name'        => $u->display_name,
        'email'       => $u->user_email,
        'balance'     => lagos_balance($u->ID),
        'member_since' => $u->user_registered,
    ]]);
}

function lagos_api_services() {
    $u = lagos_api_user();
    if (!$u) return lagos_api_err();
    $out = [];
    foreach (lagos_user_services($u->ID) as $s) {
        $out[] = [
            'id'       => $s->ID,
            'name'     => $s->post_title,
            'status'   => get_post_meta($s->ID, '_lagos_status', true),
            'price'    => (float) get_post_meta($s->ID, '_lagos_price', true),
            'cycle'    => get_post_meta($s->ID, '_lagos_cycle', true),
            'next_due' => get_post_meta($s->ID, '_lagos_next_due', true),
        ];
    }
    return rest_ensure_response(['success' => true, 'data' => $out]);
}

function lagos_api_invoices() {
    $u = lagos_api_user();
    if (!$u) return lagos_api_err();
    $out = [];
    foreach (lagos_user_invoices($u->ID) as $i) {
        $out[] = [
            'id'      => $i->ID,
            'ref'     => lagos_invoice_ref($i->ID),
            'status'  => get_post_meta($i->ID, '_lagos_status', true),
            'amount'  => (float) get_post_meta($i->ID, '_lagos_amount', true),
            'due'     => get_post_meta($i->ID, '_lagos_due', true),
            'coupon'  => get_post_meta($i->ID, '_lagos_coupon', true) ?: null,
        ];
    }
    return rest_ensure_response(['success' => true, 'data' => $out]);
}

function lagos_api_tickets() {
    $u = lagos_api_user();
    if (!$u) return lagos_api_err();
    $out = [];
    foreach (lagos_user_tickets($u->ID) as $t) {
        $out[] = [
            'id'       => $t->ID,
            'subject'  => $t->post_title,
            'status'   => get_post_meta($t->ID, '_lagos_status', true),
            'dept'     => get_post_meta($t->ID, '_lagos_dept', true),
            'priority' => get_post_meta($t->ID, '_lagos_priority', true),
            'created'  => $t->post_date,
        ];
    }
    return rest_ensure_response(['success' => true, 'data' => $out]);
}

function lagos_api_tickets_create($request) {
    $u = lagos_api_user();
    if (!$u) return lagos_api_err();

    $subject  = sanitize_text_field($request->get_param('subject'));
    $message  = sanitize_textarea_field($request->get_param('message'));
    $dept     = $request->get_param('dept');
    $priority = $request->get_param('priority');

    if (!$subject || !$message) {
        return new WP_Error('lagos_invalid', 'Os campos subject e message são obrigatórios.', ['status' => 400]);
    }
    $dept     = in_array($dept, ['general', 'billing', 'tech'], true) ? $dept : 'general';
    $priority = in_array($priority, ['low', 'medium', 'high'], true) ? $priority : 'medium';

    $tid = wp_insert_post([
        'post_type'    => 'lagos_ticket',
        'post_status'  => 'publish',
        'post_title'   => $subject,
        'post_content' => $message,
    ]);
    if (!$tid || is_wp_error($tid)) {
        return new WP_Error('lagos_error', 'Não foi possível criar o ticket.', ['status' => 500]);
    }
    update_post_meta($tid, '_lagos_user', $u->ID);
    update_post_meta($tid, '_lagos_status', 'open');
    update_post_meta($tid, '_lagos_dept', $dept);
    update_post_meta($tid, '_lagos_priority', $priority);

    return rest_ensure_response(['success' => true, 'data' => ['id' => $tid, 'status' => 'open', 'dept' => $dept, 'priority' => $priority]]);
}
