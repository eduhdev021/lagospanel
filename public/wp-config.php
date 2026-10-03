<?php
/**
 * LagosPanel — wp-config
 * Um produto da companhia Lagos.
 */

// ===== Banco de dados =====
define('DB_NAME', 'lagospanel');
define('DB_USER', 'lagos');
define('DB_PASSWORD', 'Lgx2026Panel!DB');
define('DB_HOST', '127.0.0.1:3306');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
$table_prefix = 'lagos_';

// ===== URL dinâmica (funciona em qualquer domínio / preview) =====
if (isset($_SERVER['HTTP_HOST']) && preg_match('/^[a-zA-Z0-9.\-]+$/', $_SERVER['HTTP_HOST'])) {
    $lagos_scheme = 'https';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $lagos_scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ? 'https' : 'http';
    }
    define('WP_HOME', $lagos_scheme . '://' . $_SERVER['HTTP_HOST']);
    define('WP_SITEURL', WP_HOME);
} else {
    define('WP_HOME', 'http://localhost:8080');
    define('WP_SITEURL', 'http://localhost:8080');
}
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

// ===== Ambiente de desenvolvimento =====
define('WP_DEBUG', true);
define('DISALLOW_FILE_EDIT', true); // produção: sem editor de arquivos no admin
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
@ini_set('display_errors', 0);
define('WP_CACHE', false);
define('FS_METHOD', 'direct');
define('WP_AUTO_UPDATE_CORE', false);
define('DISABLE_WP_CRON', true);
define('AUTOSAVE_INTERVAL', 300);
define('WP_POST_REVISIONS', 5);

// ===== Salts (gerados na instalação — troque em produção) =====
define('AUTH_KEY',         '0AGX1xP41fEy7gxnMZNYxIqPsVADjZCHR+97M79pRO/N5C9fGgB1W/bBY4Xau3vi');
define('SECURE_AUTH_KEY',  'JzoIiIACMJtBYpdh9714RrUEzpI2XiCH4tTtqUIWW8EoYBXTdwV55nFAHGLxqngH');
define('LOGGED_IN_KEY',    '8Zs+wZ9UL+xdXtSGwKLFah7fdXL6eb81/3UOnJ/JsyhHgWPxWVrEqXuNrsrvLOs0');
define('NONCE_KEY',        'tCr+DjBuLUXbLoT5WiyUVUVTp+des0AMYR50bFS3IvhGyIPLy4WYW8ckbWqsSWl0');
define('AUTH_SALT',        'M6C1BjrKcLflMWm0uQNrUWO0576oZXAIy4JmD/O4TW35TXzCa8z4T7g+pygXZWqo');
define('SECURE_AUTH_SALT', 'GPSP5/jsGBX/ztud03iLcrvs4W8NnKIiTS5lwdQbhKK9MPzfvAiA80hcaB1S+JrF');
define('LOGGED_IN_SALT',   'SCiwMUrTVglyrXVBtf2LOdjDE2C6GqvinPCNOlewkDP+YvpH3gKlwXS0YUMiMKmU');
define('NONCE_SALT',       'Rh39Bp2666JWS0PWVfvm359bTggbIrHhXzUDPXf3NoYDJ35NOI4ea2uwplaItrpf');

/* Isso é tudo, pode parar de editar! :) */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
require_once ABSPATH . 'wp-settings.php';
