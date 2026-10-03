<?php
/**
 * LagosPanel Core — Sistema de Módulos / Conexões (v0.3)
 * Provisionamento integrado com: Pterodactyl, cPanel/WHM, aaPanel,
 * Virtualizor e Cloudflare DNS — com modo simulado para demonstrações.
 *
 * Ciclo: pagamento → create · admin suspende → suspend ·
 *        admin reativa → unsuspend · admin cancela → terminate
 */
if (!defined('ABSPATH')) exit;

/* =========================================================
   CATÁLOGO DE TIPOS DE MÓDULO
   ========================================================= */
function lagos_module_types() {
    return [
        'pterodactyl' => [
            'label'   => 'Pterodactyl',
            'desc'    => 'Servidores de jogo (Minecraft, CS2, Rust...)',
            'icon'    => 'gamepad',
            'fields'  => ['host' => 'URL do painel', 'api_key' => 'Application API Key (ptlc_...)'],
            'actions' => ['create', 'suspend', 'unsuspend', 'terminate'],
        ],
        'cpanel' => [
            'label'   => 'cPanel / WHM',
            'desc'    => 'Hospedagem compartilhada (contas cPanel)',
            'icon'    => 'server',
            'fields'  => ['host' => 'URL do WHM (https://host:2087)', 'whm_user' => 'Usuário WHM', 'api_token' => 'API Token'],
            'actions' => ['create', 'suspend', 'unsuspend', 'terminate'],
        ],
        'aapanel' => [
            'label'   => 'aaPanel',
            'desc'    => 'Hospedagem com aaPanel (sites, FTP, banco)',
            'icon'    => 'monitor',
            'fields'  => ['host' => 'URL (http://host:8888)', 'token' => 'API Token (Configurações → API)'],
            'actions' => ['create', 'suspend', 'unsuspend', 'terminate'],
        ],
        'virtualizor' => [
            'label'   => 'Virtualizor',
            'desc'    => 'VPS / virtualização (KVM, LXC)',
            'icon'    => 'cloud',
            'fields'  => ['host' => 'URL (https://host:4085)', 'api_key' => 'API Key', 'api_pass' => 'API Password'],
            'actions' => ['create', 'suspend', 'unsuspend', 'terminate'],
        ],
        'cloudflare_dns' => [
            'label'   => 'DNS (Cloudflare)',
            'desc'    => 'Zonas e registros DNS ao contratar domínios',
            'icon'    => 'globe',
            'fields'  => ['api_token' => 'API Token', 'account_id' => 'Account ID (criar zonas)'],
            'actions' => ['create', 'terminate'],
        ],
        'directadmin' => [
            'label'   => 'DirectAdmin',
            'desc'    => 'Hospedagem compartilhada (contas DirectAdmin)',
            'icon'    => 'monitor',
            'fields'  => ['host' => 'URL (https://host:2223)', 'api_token' => 'API Token (Login as → API Keys)', 'package' => 'Package padrão (ex.: lagos_start)'],
            'actions' => ['create', 'suspend', 'unsuspend', 'terminate'],
        ],
        'plesk' => [
            'label'   => 'Plesk',
            'desc'    => 'Hospedagem com Plesk (webspaces/domínios)',
            'icon'    => 'server',
            'fields'  => ['host' => 'URL (https://host:8443)', 'username' => 'Admin Plesk', 'password' => 'Senha admin (ou Secret Key)', 'plan' => 'Service Plan (ex.: lagos_start)'],
            'actions' => ['create', 'suspend', 'unsuspend', 'terminate'],
        ],
        'proxmox' => [
            'label'   => 'Proxmox VE',
            'desc'    => 'VPS / virtualização (KVM/LXC) via API de tokens',
            'icon'    => 'cloud',
            'fields'  => ['host' => 'URL (https://host:8006)', 'token_id' => 'Token (user@pam!nome)', 'token_secret' => 'Secret do token (UUID)', 'node' => 'Node (ex.: pve1)'],
            'actions' => ['create', 'suspend', 'unsuspend', 'terminate'],
        ],
        'virtfusion' => [
            'label'   => 'VirtFusion',
            'desc'    => 'VPS modernos (API v1, usado por vários provedores)',
            'icon'    => 'cloud',
            'fields'  => ['host' => 'URL (https://api.host)', 'api_key' => 'API Key', 'package_id' => 'ID do pacote VirtFusion'],
            'actions' => ['create', 'suspend', 'unsuspend', 'terminate'],
        ],
        'custom_api' => [
            'label'   => 'API Personalizada',
            'desc'    => 'Endpoint próprio (webhooks de provisionamento)',
            'icon'    => 'wrench',
            'fields'  => ['host' => 'URL base da API', 'api_key' => 'Chave (enviada como Bearer)'],
            'actions' => ['create', 'suspend', 'unsuspend', 'terminate'],
        ],
    ];
}

/* =========================================================
   INSTÂNCIAS (armazenadas em option — estilo "servers" do WHMCS)
   ========================================================= */
function lagos_modules() {
    $m = get_option('lagos_modules', []);
    return is_array($m) ? $m : [];
}
function lagos_module_get($id) {
    $m = lagos_modules();
    return isset($m[$id]) ? $m[$id] : null;
}
function lagos_modules_save($modules) {
    update_option('lagos_modules', $modules);
}

/* =========================================================
   LOG DE PROVISIONAMENTO
   ========================================================= */
function lagos_module_log($type, $action, $service_id, $ok, $message) {
    $log = get_option('lagos_module_log', []);
    array_unshift($log, [
        'time'    => current_time('mysql'),
        'module'  => $type,
        'action'  => $action,
        'service' => (int) $service_id,
        'ok'      => (bool) $ok,
        'message' => $message,
    ]);
    update_option('lagos_module_log', array_slice($log, 0, 200));
}
function lagos_module_logs() {
    $l = get_option('lagos_module_log', []);
    return is_array($l) ? $l : [];
}

/* =========================================================
   HTTP helper
   ========================================================= */
function lagos_module_http($method, $url, $args = [], $headers = []) {
    $resp = wp_remote_request($url, [
        'method'  => $method,
        'timeout' => 15,
        'headers' => $headers,
        'body'    => $args,
        'sslverify' => false, // ambientes self-signed comuns em painéis
    ]);
    if (is_wp_error($resp)) return [false, 0, $resp->get_error_message()];
    $code = (int) wp_remote_retrieve_response_code($resp);
    $body = wp_remote_retrieve_body($resp);
    $ok   = ($code >= 200 && $code < 300);
    return [$ok, $code, $body];
}

/* =========================================================
   MOTOR DE EXECUÇÃO
   ========================================================= */
function lagos_module_run($action, $module_id, $service_id) {
    $module  = lagos_module_get($module_id);
    $service = get_post($service_id);
    if (!$module || !$service || $service->post_type !== 'lagos_service') return false;
    $types = lagos_module_types();
    if (!isset($types[$module['type']])) return false;
    if (!in_array($action, (array) $types[$module['type']]['actions'], true)) return false;

    $product = get_post((int) get_post_meta($service_id, '_lagos_product', true));
    $extra   = json_decode((string) get_post_meta($product ? $product->ID : 0, '_lagos_module_extra', true), true) ?: [];
    $user    = get_userdata((int) get_post_meta($service_id, '_lagos_user', true));

    $simulate = !empty($module['simulate']);

    // Parâmetros padrão enviados a todos os módulos
    $params = array_merge($extra, [
        'service_id' => $service_id,
        'service'    => $service->post_title,
        'domain'     => (string) get_post_meta($service_id, '_lagos_domain', true),
        'username'   => $user ? $user->user_login : 'cliente',
        'email'      => $user ? $user->user_email : '',
        'name'       => $user ? $user->display_name : '',
    ]);

    update_post_meta($service_id, '_lagos_module_status', 'provisioning');

    if ($simulate) {
        $remote = 'demo-' . substr(md5($service_id . $action . microtime()), 0, 10);
        lagos_module_log($module['type'], $action, $service_id, true, sprintf('[SIMULADO] %s "%s" → %s', $action, $service->post_title, $remote));
        lagos_module_finish($service_id, $action, $remote);
        return true;
    }

    // ===== Chamadas reais =====
    $result = [false, '', 'Módulo sem implementação de conexão real ainda.'];
    switch ($module['type']) {
        case 'pterodactyl':   $result = lagos_real_pterodactyl($action, $module, $params); break;
        case 'cpanel':        $result = lagos_real_cpanel($action, $module, $params); break;
        case 'aapanel':       $result = lagos_real_aapanel($action, $module, $params); break;
        case 'virtualizor':   $result = lagos_real_virtualizor($action, $module, $params); break;
        case 'cloudflare_dns':$result = lagos_real_cloudflare($action, $module, $params); break;
        case 'custom_api':    $result = lagos_real_custom($action, $module, $params); break;
        case 'directadmin':   $result = lagos_real_directadmin($action, $module, $params); break;
        case 'plesk':         $result = lagos_real_plesk($action, $module, $params); break;
        case 'proxmox':       $result = lagos_real_proxmox($action, $module, $params); break;
        case 'virtfusion':    $result = lagos_real_virtfusion($action, $module, $params); break;
    }
    [$ok, $remote, $msg] = $result;

    lagos_module_log($module['type'], $action, $service_id, $ok, $msg . ($remote ? " → {$remote}" : ''));
    lagos_audit('module_run', 'módulo ' . $module['type'] . ' → ' . $action . ' no serviço #' . $service_id . ($ok ? ' (ok)' : ' (falhou)'));
    lagos_module_finish($service_id, $action, $ok ? $remote : '', !$ok && $action === 'create' ? 'error' : null);
    return $ok;
}

function lagos_module_finish($service_id, $action, $remote, $force_status = null) {
    if ($force_status) {
        update_post_meta($service_id, '_lagos_module_status', $force_status);
        return;
    }
    $map = ['create' => 'active', 'unsuspend' => 'active', 'suspend' => 'suspended', 'terminate' => 'terminated'];
    update_post_meta($service_id, '_lagos_module_status', $map[$action] ?? 'active');
    if ($remote) update_post_meta($service_id, '_lagos_module_remote', $remote);
}

/* =========================================================
   INTEGRAÇÕES REAIS (usadas quando "modo simulado" está OFF)
   ========================================================= */
function lagos_real_pterodactyl($action, $m, $p) {
    $base = rtrim($m['host'], '/');
    $h = ['Authorization' => 'Bearer ' . $m['api_key'], 'Content-Type' => 'application/json', 'Accept' => 'application/json'];
    if ($action === 'create') {
        $body = wp_json_encode([
            'name'        => $p['service'],
            'user'        => (int) ($p['user_id'] ?? 1),
            'egg'         => (int) ($p['egg'] ?? 5),
            'docker_image'=> $p['docker_image'] ?? 'ghcr.io/pterodactyl/yolks:java_21',
            'startup'     => $p['startup'] ?? 'java -Xms128M -Xmx {{SERVER_MEMORY}}M -jar server.jar',
            'environment' => (array) ($p['environment'] ?? ['SERVER_JARFILE' => 'server.jar']),
            'limits'      => ['memory' => (int) ($p['memory'] ?? 2048), 'disk' => (int) ($p['disk'] ?? 10240), 'cpu' => (int) ($p['cpu'] ?? 100)],
            'feature_limits' => ['databases' => 1, 'backups' => 1],
        ]);
        [$ok, $code, $resp] = lagos_module_http('POST', $base . '/api/application/servers', $body, $h);
        $id = $ok ? (json_decode($resp, true)['attributes']['id'] ?? '') : '';
        return [$ok, $id, "Pterodactyl HTTP $code"];
    }
    $sid = $p['remote_id'] ?? '';
    $ep  = ['suspend' => 'POST', 'unsuspend' => 'POST', 'terminate' => 'DELETE'][$action] ?? 'POST';
    [$ok, $code, $resp] = lagos_module_http($ep, $base . '/api/application/servers/' . $sid . ($action === 'terminate' ? '' : '/' . $action), null, $h);
    return [$ok, $sid, "Pterodactyl HTTP $code"];
}

/* ── DirectAdmin (WHMCS-compatible) ── */
function lagos_real_directadmin($action, $m, $p) {
    $base = rtrim($m['host'], '/');
    $h = ['Authorization' => 'Bearer ' . $m['api_token']];
    $user = $p['remote_id'] ?: ('lg' . $p['service_id']);
    if ($action === 'create') {
        $q = http_build_query([
            'action'  => 'create',
            'add'     => 'Submit',
            'username'=> $user,
            'email'   => $p['email'],
            'passwd'  => wp_generate_password(16, false),
            'domain'  => $p['domain'] ?: ('site' . $p['service_id'] . '.lagos.app'),
            'package' => $p['plan'] ?? ($m['package'] ?? 'lagos_start'),
            'ip'      => 'shared',
        ]);
        [$ok, $code, $resp] = lagos_module_http('POST', $base . '/CMD_API_ACCOUNT', $q, $h);
        return [$ok, $user, "DirectAdmin create HTTP $code"];
    }
    if ($action === 'terminate') {
        $q = http_build_query(['confirmed' => 'Confirm', 'delete' => 'yes', 'select0' => $user]);
        [$ok, $code, $resp] = lagos_module_http('POST', $base . '/CMD_API_SELECT_USERS', $q, $h);
        return [$ok, $user, "DirectAdmin delete HTTP $code"];
    }
    $field = $action === 'suspend' ? 'suspend' : 'unsuspend';
    $q = http_build_query([$field => 'yes', 'select0' => $user]);
    [$ok, $code, $resp] = lagos_module_http('POST', $base . '/CMD_API_SELECT_USERS', $q, $h);
    return [$ok, $user, "DirectAdmin $field HTTP $code"];
}

/* ── Plesk (XML-RPC, WHMCS-compatible) ── */
function lagos_real_plesk($action, $m, $p) {
    $base = rtrim($m['host'], '/');
    $h = ['Content-Type' => 'text/xml; charset=UTF-8', 'HTTP_AUTH_LOGIN' => $m['username'], 'HTTP_AUTH_PASSWD' => $m['password']];
    $domain = $p['domain'] ?: ('site' . $p['service_id'] . '.lagos.app');
    $remote = $p['remote_id'] ?: $domain;

    if ($action === 'create') {
        $xml = '<packet><webspace><add><gen_setup><name>' . esc_html($domain) . '</name><owner-login>' . esc_html($m['username']) . '</owner-login><htype>vrt_hst</htype><ip_address>' . ($p['ip'] ?? 'shared') . '</ip_address></gen_setup>'
             . '<plan-name>' . esc_html($p['plan'] ?? ($m['plan'] ?? 'lagos_start')) . '</plan-name></add></webspace></packet>';
        [$ok, $code, $resp] = lagos_module_http('POST', $base . '/enterprise/control/agent.php', $xml, $h);
        return [$ok, $remote, "Plesk webspace.add HTTP $code"];
    }
    if ($action === 'terminate') {
        $xml = '<packet><webspace><del><filter><name>' . esc_html($remote) . '</name></filter></del></webspace></packet>';
        [$ok, $code, $resp] = lagos_module_http('POST', $base . '/enterprise/control/agent.php', $xml, $h);
        return [$ok, $remote, "Plesk webspace.del HTTP $code"];
    }
    $enabled = $action === 'suspend' ? 'false' : 'true';
    $xml = '<packet><site><set><filter><name>' . esc_html($remote) . '</name></filter><values><gen_setup><status>' . ($enabled === 'true' ? '0' : '16') . '</status></gen_setup></values></set></site></packet>';
    [$ok, $code, $resp] = lagos_module_http('POST', $base . '/enterprise/control/agent.php', $xml, $h);
    return [$ok, $remote, "Plesk site.set HTTP $code"];
}

/* ── Proxmox VE (tokens, WHMCS-compatible) ── */
function lagos_real_proxmox($action, $m, $p) {
    $base = rtrim($m['host'], '/');
    $h = ['Authorization' => 'PVEAPIToken=' . $m['token_id'] . '=' . $m['token_secret']];
    $node = $m['node'] ?: 'pve1';
    $vmid = (int) ($p['remote_id'] ?: (6000 + (int) $p['service_id']));

    if ($action === 'create') {
        $q = http_build_query([
            'vmid'   => $vmid,
            'name'   => sanitize_title($p['service']),
            'ostemplate' => $p['ostemplate'] ?? 'local:vztmpl/debian-12-standard_amd64.tar.zst',
            'memory' => (int) ($p['memory'] ?? 1024),
            'cores'  => (int) ($p['cores'] ?? 1),
            'net0'   => 'name=eth0,bridge=vmbr0,dhcp=1',
        ]);
        [$ok, $code, $resp] = lagos_module_http('POST', $base . '/api2/json/nodes/' . rawurlencode($node) . '/lxc', $q, $h);
        return [$ok, $vmid, "Proxmox lxc.create HTTP $code"];
    }
    $ep = ['suspend' => 'status/stop', 'unsuspend' => 'status/start', 'terminate' => ''][$action];
    if ($action === 'terminate') {
        [$ok, $code, $resp] = lagos_module_http('DELETE', $base . '/api2/json/nodes/' . rawurlencode($node) . '/lxc/' . $vmid, null, $h);
        return [$ok, $vmid, "Proxmox lxc.delete HTTP $code"];
    }
    [$ok, $code, $resp] = lagos_module_http('POST', $base . '/api2/json/nodes/' . rawurlencode($node) . '/lxc/' . $vmid . '/' . $ep, null, $h);
    return [$ok, $vmid, "Proxmox $ep HTTP $code"];
}

/* ── VirtFusion (API v1) ── */
function lagos_real_virtfusion($action, $m, $p) {
    $base = rtrim($m['host'], '/');
    $h = ['Authorization' => 'Bearer ' . $m['api_key'], 'Content-Type' => 'application/json', 'Accept' => 'application/json'];
    $iid = (int) ($p['remote_id'] ?: 0);

    if ($action === 'create') {
        $body = wp_json_encode([
            'package_id' => (int) ($p['package_id'] ?? ($m['package_id'] ?? 1)),
            'hostname'   => sanitize_title($p['service']),
            'user'       => ['email' => $p['email'], 'name' => $p['name']],
        ]);
        [$ok, $code, $resp] = lagos_module_http('POST', $base . '/api/v1/instances', $body, $h);
        $id = $ok ? (json_decode($resp, true)['instance']['id'] ?? '') : '';
        return [$ok, $id, "VirtFusion instances.create HTTP $code"];
    }
    $ep = ['suspend' => 'suspend', 'unsuspend' => 'unsuspend', 'terminate' => ''][$action];
    if ($action === 'terminate') {
        [$ok, $code, $resp] = lagos_module_http('DELETE', $base . '/api/v1/instances/' . $iid, null, $h);
        return [$ok, $iid, "VirtFusion instances.delete HTTP $code"];
    }
    [$ok, $code, $resp] = lagos_module_http('POST', $base . '/api/v1/instances/' . $iid . '/' . $ep, null, $h);
    return [$ok, $iid, "VirtFusion instances.$ep HTTP $code"];
}

function lagos_real_cpanel($action, $m, $p) {
    $base = rtrim($m['host'], '/');
    $h = ['Authorization' => 'WHM ' . $m['whm_user'] . ':' . $m['api_token']];
    if ($action === 'create') {
        $q = http_build_query([
            'username'    => 'lg' . $p['service_id'],
            'domain'      => $p['domain'] ?: ('site' . $p['service_id'] . '.lagos.app'),
            'plan'        => $p['plan'] ?? 'lagos_start',
            'contactemail'=> $p['email'],
            'featurelist' => 'default',
        ]);
        [$ok, $code, $resp] = lagos_module_http('GET', $base . '/json-api/createacct?' . $q, null, $h);
        return [$ok, 'lg' . $p['service_id'], "WHM createacct HTTP $code"];
    }
    $map = ['suspend' => 'suspendacct', 'unsuspend' => 'unsuspendacct', 'terminate' => 'removeacct'];
    $user = $p['remote_id'] ?: ('lg' . $p['service_id']);
    [$ok, $code, $resp] = lagos_module_http('GET', $base . '/json-api/' . $map[$action] . '?user=' . urlencode($user), null, $h);
    return [$ok, $user, "WHM {$map[$action]} HTTP $code"];
}

function lagos_real_aapanel($action, $m, $p) {
    $base  = rtrim($m['host'], '/');
    $time  = time();
    $token = md5($time . md5($m['token']));
    $h     = ['Content-Type' => 'application/x-www-form-urlencoded'];
    $common = ['request_time' => $time, 'request_token' => $token];
    if ($action === 'create') {
        $args = array_merge($common, [
            'webname'  => json_encode(['domain' => $p['domain'] ?: ('site' . $p['service_id'] . '.com'), 'domainlist' => [], 'count' => 0]),
            'path'     => '/www/wwwroot/' . ($p['domain'] ?: 'site' . $p['service_id']),
            'type_id'  => (int) ($p['type_id'] ?? 0),
            'type'     => 'PHP',
            'version'  => $p['php_version'] ?? '74',
            'port'     => '80',
            'ps'       => 'LagosPanel ' . $p['service_id'],
            'ftp'      => 'false', 'sql' => 'false', 'code' => 'utf8mb4',
        ]);
        [$ok, $code, $resp] = lagos_module_http('POST', $base . '/site?action=AddSite', $args, $h);
        return [$ok, $p['domain'] ?: 'site' . $p['service_id'], "aaPanel AddSite HTTP $code"];
    }
    $site = $p['remote_id'] ?: $p['domain'];
    $map  = ['suspend' => 'SiteStop', 'unsuspend' => 'SiteStart', 'terminate' => 'DeleteSite'];
    $args = array_merge($common, ['id' => $p['site_id'] ?? 0, 'webname' => $site]);
    [$ok, $code, $resp] = lagos_module_http('POST', $base . '/site?action=' . $map[$action], $args, $h);
    return [$ok, $site, "aaPanel {$map[$action]} HTTP $code"];
}

function lagos_real_virtualizor($action, $m, $p) {
    $base = rtrim($m['host'], '/');
    $h = ['Content-Type' => 'application/x-www-form-urlencoded'];
    $auth = ['api_key' => $m['api_key'], 'api_pass' => $m['api_pass'], 'apidata' => 1];
    if ($action === 'create') {
        $args = array_merge($auth, [
            'act' => 'addvs',
            'serid' => (int) ($p['serid'] ?? 0),
            'osid' => (int) ($p['osid'] ?? 3),
            'hostname' => $p['service'],
            'user_email' => $p['email'],
            'root_pass' => wp_generate_password(16, false),
            'space' => (int) ($p['disk'] ?? 20), 'ram' => (int) ($p['ram'] ?? 2048),
            'cores' => (int) ($p['cores'] ?? 2), 'bandwidth' => (int) ($p['bandwidth'] ?? 1000),
            'ipv4' => 1,
        ]);
        [$ok, $code, $resp] = lagos_module_http('POST', $base . '/index.php', $args, $h);
        $vid = '';
        if ($ok) { $j = json_decode($resp, true); $vid = $j['vsids'][0] ?? ($j['vpsid'] ?? ''); }
        return [$ok, $vid, "Virtualizor addvs HTTP $code"];
    }
    $map = ['suspend' => 'suspend', 'unsuspend' => 'unsuspend', 'terminate' => 'delete'];
    $vid = $p['remote_id'] ?? '';
    $args = array_merge($auth, ['act' => $map[$action], 'vpsid' => $vid]);
    [$ok, $code, $resp] = lagos_module_http('POST', $base . '/index.php', $args, $h);
    return [$ok, $vid, "Virtualizor {$map[$action]} HTTP $code"];
}

function lagos_real_cloudflare($action, $m, $p) {
    $h = ['Authorization' => 'Bearer ' . $m['api_key'], 'Content-Type' => 'application/json'];
    if ($action === 'create') {
        $domain = $p['domain'] ?: ('dominio' . $p['service_id'] . '.com');
        [$ok, $code, $resp] = lagos_module_http('POST', 'https://api.cloudflare.com/client/v4/zones', wp_json_encode([
            'name' => $domain, 'account' => ['id' => $m['account_id'] ?? ''], 'type' => 'full',
        ]), $h);
        $zid = '';
        if ($ok) { $j = json_decode($resp, true); $zid = $j['result']['id'] ?? ''; }
        return [$ok, $zid, "Cloudflare create zone HTTP $code"];
    }
    if ($action === 'terminate' && !empty($p['remote_id'])) {
        [$ok, $code, $resp] = lagos_module_http('DELETE', 'https://api.cloudflare.com/client/v4/zones/' . $p['remote_id'], null, $h);
        return [$ok, $p['remote_id'], "Cloudflare delete zone HTTP $code"];
    }
    return [true, $p['remote_id'] ?? '', 'DNS: nada a fazer'];
}

function lagos_real_custom($action, $m, $p) {
    $base = rtrim($m['host'], '/');
    $h = ['Authorization' => 'Bearer ' . $m['api_key'], 'Content-Type' => 'application/json'];
    [$ok, $code, $resp] = lagos_module_http('POST', $base . '/' . $action, wp_json_encode($p), $h);
    return [$ok, '', "API custom ({$action}) HTTP $code"];
}

/* =========================================================
   HOOKS DO CICLO DE VIDA
   ========================================================= */

/** Pagamento confirmado → provisiona todos os serviços da fatura */
function lagos_modules_provision_invoice($invoice_id) {
    $sids = [];
    $single = (int) get_post_meta($invoice_id, '_lagos_service', true);
    if ($single) $sids[] = $single;
    $multi = get_post_meta($invoice_id, '_lagos_services', true);
    if ($multi) $sids = array_merge($sids, array_map('intval', explode(',', $multi)));

    foreach (array_unique(array_filter($sids)) as $sid) {
        $product = get_post((int) get_post_meta($sid, '_lagos_product', true));
        $module  = $product ? get_post_meta($product->ID, '_lagos_module', true) : '';
        if ($module && lagos_module_get($module)) {
            lagos_module_run('create', $module, $sid);
        }
    }
}
add_action('lagos_invoice_paid', 'lagos_modules_provision_invoice');

/** Admin alterou status do serviço → sincroniza com o servidor remoto */
add_action('save_post_lagos_service', function ($post_id, $post, $update) {
    if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $product = get_post((int) get_post_meta($post_id, '_lagos_product', true));
    $module  = $product ? get_post_meta($product->ID, '_lagos_module', true) : '';
    if (!$module || !lagos_module_get($module)) return;

    $status = get_post_meta($post_id, '_lagos_status', true);
    $mstat  = get_post_meta($post_id, '_lagos_module_status', true);
    $remote = get_post_meta($post_id, '_lagos_module_remote', true);
    if (!$remote) return; // nunca foi provisionado

    if ($status === 'suspended' && $mstat === 'active') {
        lagos_module_run('suspend', $module, $post_id);
    } elseif ($status === 'active' && $mstat === 'suspended') {
        lagos_module_run('unsuspend', $module, $post_id);
    } elseif ($status === 'cancelled' && in_array($mstat, ['active', 'suspended'], true)) {
        lagos_module_run('terminate', $module, $post_id);
    }
}, 20, 3);

/* =========================================================
   BADGE (lado do cliente)
   ========================================================= */
function lagos_module_badge($service_id) {
    $st = get_post_meta($service_id, '_lagos_module_status', true);
    if (!$st) return '<span class="t-muted">—</span>';
    $cls = [
        'provisioning' => 'badge-pending',  'active'   => 'badge-active',
        'suspended'    => 'badge-pending',  'error'    => 'badge-suspended',
        'terminated'   => 'badge-cancelled',
    ][$st] ?? 'badge-cancelled';
    return '<span class="badge ' . $cls . '"><span class="badge-dot"></span>' . esc_html(lagos_t('prov_' . $st)) . '</span>';
}

/* =========================================================
   ADMIN — página "Conexões"
   ========================================================= */
add_action('admin_menu', function () {
    // prioridade 11: roda APÓS admin.php registrar o menu pai 'lagospanel',
    // senão o hook é registrado como 'admin_page_...' e a página dá 403.
    add_submenu_page('lagospanel', 'Conexões & Módulos', 'Conexões', 'manage_options', 'lagos-modules', 'lagos_admin_modules_page');
}, 11);

function lagos_admin_modules_url() {
    return admin_url('admin.php?page=lagos-modules');
}

function lagos_admin_modules_page() {
    $types   = lagos_module_types();
    $modules = lagos_modules();
    $logs    = array_slice(lagos_module_logs(), 0, 25);
    $edit    = isset($_GET['edit']) ? lagos_module_get(sanitize_key($_GET['edit'])) : null;
    $edit_id = $edit ? sanitize_key($_GET['edit']) : '';
    $saved   = !empty($_GET['lagos_saved']);
    $tested  = isset($_GET['lagos_test']) ? sanitize_text_field($_GET['lagos_test']) : '';
    ?>
    <div class="wrap">
        <h1><span class="dashicons dashicons-flash"></span> Conexões & Módulos</h1>
        <p class="text-muted">Integrações de provisionamento — quando uma fatura é paga, o LagosPanel cria o serviço automaticamente no painel remoto. <strong>Modo simulado</strong> registra a operação sem chamadas externas (ideal para demos).</p>

        <?php if ($saved) : ?><div class="notice notice-success is-dismissible"><p>Conexão salva.</p></div><?php endif; ?>
        <?php if ($tested !== '') : ?><div class="notice <?php echo $tested === 'ok' ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo $tested === 'ok' ? 'Conexão testada com sucesso!' : 'Falha no teste de conexão (modo real). Verifique credenciais/host.'; ?></p></div><?php endif; ?>

        <h2 style="margin-top:24px">Conexões ativas</h2>
        <table class="widefat striped" style="max-width:960px">
            <thead><tr><th>Nome</th><th>Tipo</th><th>Host</th><th>Modo</th><th style="width:220px">Ações</th></tr></thead>
            <tbody>
            <?php if ($modules) : foreach ($modules as $id => $m) : ?>
                <tr>
                    <td><strong><?php echo esc_html($m['name']); ?></strong></td>
                    <td><?php echo lagos_icon($types[$m['type']]['icon'] ?? 'api', 16); ?> <?php echo esc_html($types[$m['type']]['label'] ?? $m['type']); ?></td>
                    <td><code><?php echo esc_html($m['host'] ?? 'api.cloudflare.com'); ?></code></td>
                    <td><?php echo !empty($m['simulate']) ? 'Simulado (demo)' : 'Real'; ?></td>
                    <td>
                        <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=lagos_module_test&id=' . $id), 'lagos_module_test_' . $id)); ?>">Testar</a>
                        <a class="button button-small" href="<?php echo esc_url(add_query_arg('edit', $id, lagos_admin_modules_url())); ?>">Editar</a>
                        <a class="button button-small button-link-delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=lagos_module_delete&id=' . $id), 'lagos_module_del_' . $id)); ?>">Excluir</a>
                    </td>
                </tr>
            <?php endforeach; else : ?>
                <tr><td colspan="5">Nenhuma conexão cadastrada — crie a primeira abaixo. </td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <h2 style="margin-top:32px"><?php echo $edit ? 'Editar conexão' : 'Adicionar conexão'; ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:640px">
            <input type="hidden" name="action" value="lagos_module_save">
            <input type="hidden" name="id" value="<?php echo esc_attr($edit_id); ?>">
            <?php wp_nonce_field('lagos_modules_admin'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th>Nome da conexão</th>
                    <td><input type="text" name="name" class="regular-text" required value="<?php echo esc_attr($edit['name'] ?? ''); ?>" placeholder="Ex.: Pterodactyl — Cluster BR"></td>
                </tr>
                <tr>
                    <th>Tipo</th>
                    <td>
                        <select name="type" id="lagosModuleType">
                            <?php foreach ($types as $k => $t) : ?>
                                <option value="<?php echo esc_attr($k); ?>" <?php selected($edit['type'] ?? '', $k); ?>><?php echo esc_html($t['label'] . ' — ' . $t['desc']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <?php foreach ($types as $tk => $t) : $style = ($edit['type'] ?? '') === $tk ? '' : 'display:none'; ?>
                    <tr class="lagos-type-fields" data-type="<?php echo esc_attr($tk); ?>" style="<?php echo esc_attr($style); ?>">
                        <th>Credenciais</th>
                        <td>
                            <?php foreach ($t['fields'] as $fk => $fl) : ?>
                                <p><label><?php echo esc_html($fl); ?></label>
                                <input type="text" class="regular-text" name="creds[<?php echo esc_attr($tk); ?>][<?php echo esc_attr($fk); ?>]" value="<?php echo esc_attr($edit['creds'][$fk] ?? ''); ?>"></p>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <th>Modo simulado</th>
                    <td><label><input type="checkbox" name="simulate" value="1" <?php checked(!empty($edit['simulate']) || !$edit, true); ?>> Registrar operações sem chamar a API externa (demo)</label></td>
                </tr>
            </table>
            <?php submit_button($edit ? 'Salvar alterações' : 'Adicionar conexão'); ?>
            <?php if ($edit) : ?><a class="button" href="<?php echo esc_url(lagos_admin_modules_url()); ?>">Cancelar</a><?php endif; ?>
        </form>

        <h2 style="margin-top:32px"><span class="dashicons dashicons-list-view"></span> Log de provisionamento</h2>
        <table class="widefat striped" style="max-width:960px">
            <thead><tr><th style="width:150px">Data</th><th style="width:120px">Módulo</th><th style="width:90px">Ação</th><th>Detalhes</th></tr></thead>
            <tbody>
            <?php if ($logs) : foreach ($logs as $l) : ?>
                <tr>
                    <td><?php echo esc_html(mysql2date('d/m/Y H:i:s', $l['time'])); ?></td>
                    <td><?php echo esc_html($l['module']); ?></td>
                    <td><?php echo esc_html($l['action']); ?></td>
                    <td><?php echo $l['ok'] ? '&#10003;' : '&#10007;'; ?> <?php echo esc_html($l['message']); ?></td>
                </tr>
            <?php endforeach; else : ?>
                <tr><td colspan="4">Sem eventos ainda — pague uma fatura de produto com módulo vinculado e veja aqui. </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <script>
    jQuery(function($){
        function lagosShowFields(){
            var t = $('#lagosModuleType').val();
            $('.lagos-type-fields').hide();
            $('.lagos-type-fields[data-type="'+t+'"]').show();
        }
        $('#lagosModuleType').on('change', lagosShowFields); lagosShowFields();
    });
    </script>
    <?php
}

/* ===== CRUD / ações (admin_post) ===== */
add_action('admin_post_lagos_module_save', function () {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');
    check_admin_referer('lagos_modules_admin');

    $id     = sanitize_key($_POST['id'] ?? '');
    $type   = sanitize_key($_POST['type'] ?? '');
    $types  = lagos_module_types();
    if (!isset($types[$type])) wp_die('Tipo inválido.');

    $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $creds = [];
    foreach ($types[$type]['fields'] as $fk => $fl) {
        $creds[$fk] = sanitize_text_field(wp_unslash($_POST['creds'][$type][$fk] ?? ''));
    }
    $inst = [
        'name'     => $name ?: 'Conexão ' . $types[$type]['label'],
        'type'     => $type,
        'creds'    => $creds,
        'host'     => $creds['host'] ?? '',
        'simulate' => !empty($_POST['simulate']) ? 1 : 0,
    ];
    if ($type === 'cloudflare_dns') $inst['host'] = 'api.cloudflare.com';
    if ($type === 'custom_api')     $inst['host'] = $creds['host'] ?? '';

    $modules = lagos_modules();
    if (!$id) $id = 'm' . substr(uniqid(), -6);
    $modules[$id] = $inst;
    lagos_modules_save($modules);
    wp_redirect(add_query_arg('lagos_saved', 1, lagos_admin_modules_url()));
    exit;
});

add_action('admin_post_lagos_module_delete', function () {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');
    $id = sanitize_key($_GET['id'] ?? '');
    check_admin_referer('lagos_module_del_' . $id);
    $modules = lagos_modules();
    unset($modules[$id]);
    lagos_modules_save($modules);
    wp_redirect(lagos_admin_modules_url());
    exit;
});

add_action('admin_post_lagos_module_test', function () {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');
    $id = sanitize_key($_GET['id'] ?? '');
    check_admin_referer('lagos_module_test_' . $id);
    $m = lagos_module_get($id);
    if (!$m) wp_die('Conexão não encontrada.');
    $ok = true;
    if (empty($m['simulate'])) {
        $url = $m['type'] === 'cloudflare_dns' ? 'https://api.cloudflare.com/client/v4/user/tokens/verify' : rtrim($m['host'], '/') . '/';
        $h = ['Authorization' => 'Bearer ' . ($m['creds']['api_key'] ?? '')];
        [$ok, $code, $body] = lagos_module_http('GET', $url, null, $h);
    }
    lagos_module_log($m['type'], 'test', 0, $ok, 'Teste de conexão ' . ($ok ? 'OK' : 'FALHOU') . (empty($m['simulate']) ? '' : ' (simulado)'));
    wp_redirect(add_query_arg('lagos_test', $ok ? 'ok' : 'fail', lagos_admin_modules_url()));
    exit;
});

/** Ações manuais na tela de edição do serviço */
add_action('admin_post_lagos_module_action', function () {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');
    $sid = absint($_GET['service'] ?? 0);
    $act = sanitize_key($_GET['do'] ?? '');
    check_admin_referer('lagos_module_act_' . $sid . '_' . $act);
    $product = get_post((int) get_post_meta($sid, '_lagos_product', true));
    $module  = $product ? get_post_meta($product->ID, '_lagos_module', true) : '';
    if ($module) lagos_module_run($act, $module, $sid);
    wp_redirect(add_query_arg('lagos_msg', 'module_ran', admin_url('post.php?post=' . $sid . '&action=edit')));
    exit;
});

/* ===== Meta box no serviço (status do módulo + ações manuais) ===== */
add_action('add_meta_boxes', function () {
    add_meta_box('lagos_module_box', ' Provisionamento (módulo)', 'lagos_module_service_box', 'lagos_service', 'side', 'high');
});

function lagos_module_service_box($post) {
    $product = get_post((int) get_post_meta($post->ID, '_lagos_product', true));
    $mid     = $product ? get_post_meta($product->ID, '_lagos_module', true) : '';
    if (!$mid || !lagos_module_get($mid)) {
        echo '<p class="text-muted">Este serviço não tem módulo vinculado (configure no produto).</p>';
        return;
    }
    $m      = lagos_module_get($mid);
    $types  = lagos_module_types();
    $status = get_post_meta($post->ID, '_lagos_module_status', true) ?: '—';
    $remote = get_post_meta($post->ID, '_lagos_module_remote', true);
    echo '<p><strong>Conexão:</strong> ' . esc_html($m['name']) . '<br>';
    echo '<strong>Tipo:</strong> ' . esc_html($types[$m['type']]['label']) . '<br>';
    echo '<strong>Status:</strong> ' . esc_html($status) . '<br>';
    echo '<strong>ID remoto:</strong> <code>' . esc_html($remote ?: '—') . '</code></p>';
    echo '<p>';
    foreach (['create' => 'Criar', 'suspend' => 'Suspender', 'unsuspend' => 'Reativar', 'terminate' => 'Excluir'] as $act => $lbl) {
        if (!in_array($act, (array) $types[$m['type']]['actions'], true)) continue;
        $url = wp_nonce_url(admin_url('admin-post.php?action=lagos_module_action&service=' . $post->ID . '&do=' . $act), 'lagos_module_act_' . $post->ID . '_' . $act);
        echo '<a class="button button-small" style="margin:0 4px 4px 0" href="' . esc_url($url) . '">' . esc_html($lbl) . '</a>';
    }
    echo '</p>';
}

/* ===== Coluna na listagem de serviços (admin) ===== */
add_filter('manage_lagos_service_posts_columns', function ($cols) {
    $cols['lagos_module'] = 'Provisionamento';
    return $cols;
});
add_action('manage_lagos_service_posts_custom_column', function ($col, $post_id) {
    if ($col !== 'lagos_module') return;
    $st = get_post_meta($post_id, '_lagos_module_status', true);
    echo esc_html($st ?: '—');
}, 10, 2);

/* ══════════════════════════════════════════════════════════
   SSO — login com 1 clique no painel de provisionamento (v0.10)
   Pterodactyl: usuário vinculado por external_id + senha rotativa
   de uso único submetida ao /auth/login do painel.
   cPanel/WHM: sessão temporária via create_user_session.
   ══════════════════════════════════════════════════════════ */
function lagos_module_sso_supported($service_id) {
    if (!function_exists('lagos_module_get')) return false;
    $product = get_post((int) get_post_meta($service_id, '_lagos_product', true));
    if (!$product) return false;
    $m = lagos_module_get(get_post_meta($product->ID, '_lagos_module', true));
    if (!$m || !empty($m['simulate'])) return false;
    return in_array($m['type'], ['pterodactyl', 'cpanel'], true) ? $m : false;
}

function lagos_module_sso($service_id) {
    $m = lagos_module_sso_supported($service_id);
    if (!$m) return new WP_Error('sso_unsupported', 'SSO nao disponivel para este servico.');
    $svc  = get_post($service_id);
    $user = get_userdata((int) get_post_meta($service_id, '_lagos_user', true));
    if (!$user) return new WP_Error('sso_user', 'Cliente nao encontrado.');
    $uid = (int) $user->ID;

    if ($m['type'] === 'pterodactyl') {
        $base = rtrim($m['host'], '/');
        $h = ['Authorization' => 'Bearer ' . $m['api_key'], 'Content-Type' => 'application/json', 'Accept' => 'application/json'];
        $ext = 'lagos-' . $uid;
        [$ok, $code, $resp] = lagos_module_http('GET', $base . '/api/application/users/external/' . rawurlencode($ext), null, $h);
        $puid = $ok ? (int) (json_decode($resp, true)['attributes']['id'] ?? 0) : 0;
        if (!$puid) {
            $body = wp_json_encode(['email' => $user->user_email, 'username' => $user->user_login, 'name_first' => mb_substr($user->display_name, 0, 24), 'name_last' => 'Lagos', 'external_id' => $ext]);
            [$ok, $code, $resp] = lagos_module_http('POST', $base . '/api/application/users', $body, $h);
            $puid = $ok ? (int) (json_decode($resp, true)['attributes']['id'] ?? 0) : 0;
        }
        if (!$puid) return new WP_Error('sso_user_panel', 'Nao foi possivel localizar/criar o usuario no painel (HTTP ' . $code . ').');
        update_post_meta($service_id, '_lagos_sso_remote_user', $puid);
        // senha rotativa de uso unico
        $pass = wp_generate_password(24, false);
        [$ok, $code, $resp] = lagos_module_http('PATCH', $base . '/api/application/users/' . $puid, wp_json_encode(['password' => $pass]), $h);
        if (!$ok) return new WP_Error('sso_pass', 'Falha ao gerar credencial temporaria (HTTP ' . $code . ').');
        lagos_audit('module_run', 'SSO pterodactyl no servico #' . $service_id, $uid);
        return ['type' => 'form', 'url' => $base . '/auth/login', 'fields' => ['user' => $user->user_email, 'password' => $pass]];
    }

    if ($m['type'] === 'cpanel') {
        $base = rtrim($m['host'], '/');
        $h = ['Authorization' => 'Bearer ' . $m['api_key']];
        $cpuser = (string) get_post_meta($service_id, '_lagos_module_remote', true);
        if ($cpuser === '') $cpuser = 'lg' . $service_id;
        [$ok, $code, $resp] = lagos_module_http('POST', $base . '/json-api/create_user_session', http_build_query(['api.version' => 1, 'user' => $cpuser, 'service' => 'cpaneld']), $h);
        $data = $ok ? json_decode($resp, true) : [];
        $url = (string) ($data['data']['session_url'] ?? '');
        if ($url === '') return new WP_Error('sso_cpanel', 'Sessao nao criada (HTTP ' . $code . ').');
        lagos_audit('module_run', 'SSO cPanel no servico #' . $service_id, $uid);
        return ['type' => 'redirect', 'url' => $url];
    }
    return new WP_Error('sso_unsupported', 'SSO nao disponivel para este modulo.');
}

add_action('admin_post_lagos_sso', function () {
    if (!is_user_logged_in()) { wp_safe_redirect(home_url('/entrar/')); exit; }
    $sid = absint($_GET['service'] ?? 0);
    check_admin_referer('lagos_sso_' . $sid);
    $svc = get_post($sid);
    $back = function ($err) use ($sid) {
        wp_safe_redirect(add_query_arg('lagos_err', $err, add_query_arg('view', $sid, lagos_panel_url('servicos'))));
        exit;
    };
    if (!$svc || $svc->post_type !== 'lagos_service' || (int) get_post_meta($sid, '_lagos_user', true) !== get_current_user_id()) $back('sso_err');
    if (get_post_meta($sid, '_lagos_status', true) !== 'active') $back('sso_err_status');
    $r = lagos_module_sso($sid);
    if (is_wp_error($r)) $back('sso_err');
    if (($r['type'] ?? '') === 'redirect') { wp_redirect($r['url']); exit; }
    // formulario auto-submit (ex.: Pterodactyl /auth/login)
    $fields = '';
    foreach ((array) $r['fields'] as $k => $v) $fields .= '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr($v) . '">';
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>LagosPanel</title><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<style>body{margin:0;font:15px/1.5 -apple-system,Segoe UI,Roboto,sans-serif;background:#120B24;color:#EDE9F7;display:flex;min-height:100vh;align-items:center;justify-content:center}'
       . '.c{text-align:center}.s{width:44px;height:44px;margin:0 auto 14px;border-radius:50%;border:3px solid rgba(255,255,255,.25);border-top-color:#C040E0;animation:g 1s linear infinite}@keyframes g{to{transform:rotate(360deg)}}</style></head>'
       . '<body><div class="c"><div class="s"></div>Entrando no painel&hellip;</div>'
       . '<form method="post" action="' . esc_url($r['url']) . '" id="f">' . $fields . '</form>'
       . '<script>document.getElementById("f").submit()</script></body></html>';
    exit;
});
