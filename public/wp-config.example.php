<?php
/**
 * LagosPanel — exemplo de wp-config.php
 * O install.sh gera este arquivo automaticamente; use como referência.
 */
define('DB_NAME', 'lagospanel');
define('DB_USER', 'lagos');
define('DB_PASSWORD', 'SENHA_DO_BANCO');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

$table_prefix = 'lagos_';

define('WP_HOME', 'https://painel.seudominio.com.br');
define('WP_SITEURL', 'https://painel.seudominio.com.br');

/* chaves: https://api.wordpress.org/secret-key/1.1/salt/ */
define('AUTH_KEY',         'coloque-aqui');
define('SECURE_AUTH_KEY',  'coloque-aqui');
define('LOGGED_IN_KEY',    'coloque-aqui');
define('NONCE_KEY',        'coloque-aqui');
define('AUTH_SALT',        'coloque-aqui');
define('SECURE_AUTH_SALT', 'coloque-aqui');
define('LOGGED_IN_SALT',   'coloque-aqui');
define('NONCE_SALT',       'coloque-aqui');

define('WP_DEBUG', false);
define('DISALLOW_FILE_EDIT', true);
define('DISABLE_WP_CRON', true);
define('FS_METHOD', 'direct');

if ( !defined('ABSPATH') ) define('ABSPATH', __DIR__ . '/');
require_once ABSPATH . 'wp-settings.php';
