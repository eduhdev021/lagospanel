#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════
#  LagosPanel — Ambiente de desenvolvimento
#
#  Monta public/ (engine + app + resources), gera o wp-config de dev
#  e sobe MariaDB (dados em .dev/data) + servidor PHP na :8080.
#
#  Uso:  bash scripts/dev-setup.sh          # prepara e sobe tudo
#        bash scripts/dev-setup.sh --mount  # só monta o public/
# ════════════════════════════════════════════════════════════════
set -euo pipefail
cd "$(dirname "$0")/.."

MOUNT_ONLY=0
[[ "${1:-}" == "--mount" ]] && MOUNT_ONLY=1

# ── 1. public/ (idempotente) ────────────────────────────────────
if [[ ! -f public/index.php ]]; then
  echo "▸ Montando public/ (engine + app + resources)..."
  unzip -qo engine/lagospanel-engine.zip -d public
fi
rm -rf public/wp-content/plugins/lagos-core public/wp-content/themes/lagospanel
cp -a app public/wp-content/plugins/lagos-core
cp -a resources public/wp-content/themes/lagospanel
rm -f public/readme.html
echo "  ✓ public/ montado"

# ── 2. wp-config de desenvolvimento ─────────────────────────────
if [[ ! -f public/wp-config.php ]]; then
  cat > public/wp-config.php <<'CFG'
<?php
/** LagosPanel — wp-config de desenvolvimento (NAO usar em producao) */
define('DB_NAME', 'lagospanel');
define('DB_USER', 'lagos');
define('DB_PASSWORD', 'Lgx2026Panel!DB');
define('DB_HOST', '127.0.0.1:3306');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
$table_prefix = 'lagos_';
define('WP_HOME', 'http://localhost:8080');
define('WP_SITEURL', 'http://localhost:8080');
define('AUTH_KEY',         'dev-dev-dev');
define('SECURE_AUTH_KEY',  'dev-dev-dev');
define('LOGGED_IN_KEY',    'dev-dev-dev');
define('NONCE_KEY',        'dev-dev-dev');
define('AUTH_SALT',        'dev-dev-dev');
define('SECURE_AUTH_SALT', 'dev-dev-dev');
define('LOGGED_IN_SALT',   'dev-dev-dev');
define('NONCE_SALT',       'dev-dev-dev');
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('DISALLOW_FILE_EDIT', true);
define('WP_CACHE', false);
define('FS_METHOD', 'direct');
define('WP_AUTO_UPDATE_CORE', false);
if ( !defined('ABSPATH') ) define('ABSPATH', __DIR__ . '/');
require_once ABSPATH . 'wp-settings.php';
CFG
  echo "  ✓ wp-config.php de dev criado"
fi

[[ $MOUNT_ONLY -eq 1 ]] && exit 0

# ── 3. MariaDB (datadir em .dev/data) ───────────────────────────
if ! mariadb --socket=/tmp/lagos.sock -e "SELECT 1" > /dev/null 2>&1; then
  echo "▸ Subindo MariaDB (.dev/data)..."
  if [[ ! -d .dev/data/mysql ]]; then
    echo "  ERRO: .dev/data ausente — banco de dev não encontrado." >&2
    exit 1
  fi
  mkdir -p .dev/data/mysql
  /usr/sbin/mariadbd --datadir="$PWD/.dev/data/mysql" --socket=/tmp/lagos.sock \
    --port=3306 --bind-address=127.0.0.1 --user="$USER" --skip-name-resolve \
    --pid-file="$PWD/.dev/data/mysqld.pid" --skip-innodb --default-storage-engine=Aria \
    > .dev/mariadb.log 2>&1 &
  for i in $(seq 1 15); do
    mariadb --socket=/tmp/lagos.sock -e "SELECT 1" > /dev/null 2>&1 && break
    sleep 1
  done
fi
echo "  ✓ MariaDB no ar"

# ── 4. Servidor PHP ─────────────────────────────────────────────
echo "▸ Painel dev: http://localhost:8080  (Ctrl+C para parar)"
exec php -S 0.0.0.0:8080 -t public scripts/dev-server.php
