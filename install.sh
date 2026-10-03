#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════
#  LagosPanel — Instalador de produção (v0.11)
#  © 2026 Lagos Soluções — Todos os direitos reservados.
#
#  Layout do repositório (estilo Paymenter):
#    app/        → código do produto (plugin LagosPanel Core)
#    resources/  → tema LagosPanel
#    engine/     → base WordPress empacotada (1 zip — igual ao vendor/ do Laravel)
#    database/   → seed SQL + utilitários
#    public/     → docroot (montado por este instalador)
#
#  Instala em /var/www/lagospanel (mesmo padrão do Paymenter em /var/www).
#
#  Uso (como root):
#    sudo git clone https://github.com/eduhdev021/lagospanel.git /var/www/lagospanel
#    cd /var/www/lagospanel
#    sudo ./install.sh --domain painel.seudominio.com.br --ssl
#
#  Rodou o clone em outro diretório? O instalador copia sozinho para
#  /var/www/lagospanel. Diretório custom: --path /var/www/html
# ════════════════════════════════════════════════════════════════
set -euo pipefail

BOLD=$'\033[1m'; PURPLE=$'\033[38;5;135m'; GREEN=$'\033[38;5;72m'; RESET=$'\033[0m'
say() { echo "${PURPLE}▸${RESET} $*"; }
ok()  { echo "${GREEN}✓${RESET} $*"; }
die() { echo "ERRO: $*" >&2; exit 1; }

DOMAIN="" DBPASS="" SSL=0 SEED="database/seed.sql.gz" TARGET="/var/www/lagospanel"
while [[ $# -gt 0 ]]; do
  case "$1" in
    --domain)  DOMAIN="$2"; shift 2 ;;
    --db-pass) DBPASS="$2"; shift 2 ;;
    --ssl)     SSL=1; shift ;;
    --seed)    SEED="$2"; shift 2 ;;
    --path)    TARGET="$2"; shift 2 ;;
    *) die "opção desconhecida: $1" ;;
  esac
done

[[ -n "$DOMAIN" ]] || die "informe --domain painel.seudominio.com.br"
[[ $EUID -eq 0 ]] || die "rode como root (sudo ./install.sh)"
command -v apt-get > /dev/null || die "este instalador é para Ubuntu/Debian (apt)"
[[ -d app && -d resources ]] || die "execute na raiz do clone (com app/, resources/, engine/)"
[[ -f engine/lagospanel-engine.zip ]] || die "engine/lagospanel-engine.zip ausente"
[[ -f "$SEED" ]] || die "seed não encontrado: $SEED"

# reinstalação com wp-config existente → reutiliza a senha atual do banco
if [[ -z "$DBPASS" && -f "$TARGET/public/wp-config.php" ]]; then
  DBPASS="$(sed -n "s/^define( *'DB_PASSWORD', *'\([^']*\)'.*/\1/p" "$TARGET/public/wp-config.php" | head -1)"
fi
[[ -n "$DBPASS" ]] || DBPASS="$(head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 20)"
SCHEME="http"; [[ $SSL -eq 1 ]] && SCHEME="https"
DBESC="${DBPASS//\'/\\\'}"

# ── 0. Diretório de instalação (padrão Paymenter: /var/www) ─────
if [[ "$(pwd -P)" != "$TARGET" ]]; then
  say "Instalando em $TARGET (padrão /var/www, como o Paymenter)..."
  mkdir -p "$TARGET"
  tar -C . -cf - --exclude=./.git --exclude=./.dev --exclude=./public . | tar -C "$TARGET" -xf -
  cd "$TARGET"
fi
ok "diretório: $TARGET"

echo
echo "${BOLD}${PURPLE}  LagosPanel — instalação de produção${RESET}"
echo "  Domínio:    $SCHEME://$DOMAIN"
echo "  Diretório:  $TARGET"
echo

# ── 1. Dependências ─────────────────────────────────────────────
say "Instalando dependências (nginx, MariaDB, PHP-FPM)..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq nginx mariadb-server php-fpm php-mysql php-curl php-mbstring \
    php-xml php-zip unzip curl cron > /dev/null
PHPVER="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')"
systemctl enable --now mariadb php${PHPVER}-fpm nginx > /dev/null 2>&1 || true
ok "nginx + MariaDB + PHP $PHPVER"

# ── 2. Banco de dados ───────────────────────────────────────────
say "Criando banco 'lagospanel' e importando o seed..."
mysql -e "DROP DATABASE IF EXISTS lagospanel; CREATE DATABASE lagospanel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS 'lagos'@'localhost' IDENTIFIED BY '${DBESC}';"
mysql -e "ALTER USER 'lagos'@'localhost' IDENTIFIED BY '${DBESC}';" 2>/dev/null \
  || mysql -e "SET PASSWORD FOR 'lagos'@'localhost' = PASSWORD('${DBESC}');" 2>/dev/null || true
mysql -e "GRANT ALL PRIVILEGES ON lagospanel.* TO 'lagos'@'localhost'; FLUSH PRIVILEGES;"
zcat "$SEED" | mysql lagospanel
ok "banco pronto"

# ── 3. Montagem do public/ (engine + produto) ───────────────────
say "Montando public/ (engine WordPress + app + resources)..."
if [[ ! -f public/index.php ]]; then
  unzip -q engine/lagospanel-engine.zip -d public
fi
mkdir -p public/wp-content/plugins public/wp-content/themes public/wp-content/uploads
rm -rf public/wp-content/plugins/lagos-core public/wp-content/themes/lagospanel
cp -a app public/wp-content/plugins/lagos-core
cp -a resources public/wp-content/themes/lagospanel
rm -f public/readme.html public/wp-config-sample.php
ok "public/ montado"

# ── 4. wp-config.php (gerado, chaves locais) ────────────────────
if [[ ! -f public/wp-config.php ]]; then
  say "Gerando wp-config.php..."
  salt() { head -c 64 /dev/urandom | base64 | tr -d '+/=' | head -c 64; }
  cat > public/wp-config.php <<CFG
<?php
/** LagosPanel — gerado pelo install.sh em $(date '+%Y-%m-%d %H:%M') */
define('DB_NAME', 'lagospanel');
define('DB_USER', 'lagos');
define('DB_PASSWORD', '${DBPASS}');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

\$table_prefix = 'lagos_';

define('WP_HOME', '${SCHEME}://${DOMAIN}');
define('WP_SITEURL', '${SCHEME}://${DOMAIN}');

define('AUTH_KEY',         '$(salt)');
define('SECURE_AUTH_KEY',  '$(salt)');
define('LOGGED_IN_KEY',    '$(salt)');
define('NONCE_KEY',        '$(salt)');
define('AUTH_SALT',        '$(salt)');
define('SECURE_AUTH_SALT', '$(salt)');
define('LOGGED_IN_SALT',   '$(salt)');
define('NONCE_SALT',       '$(salt)');

define('WP_DEBUG', false);
define('DISALLOW_FILE_EDIT', true);
define('DISABLE_WP_CRON', true);      // cron real agendado no passo 7
define('FS_METHOD', 'direct');
define('WP_AUTO_UPDATE_CORE', false);
define('AUTOSAVE_INTERVAL', 300);
define('WP_POST_REVISIONS', 5);

if ( !defined('ABSPATH') ) define('ABSPATH', __DIR__ . '/');
require_once ABSPATH . 'wp-settings.php';
CFG
  ok "wp-config.php gerado (chaves de segurança próprias)"
else
  say "public/wp-config.php já existe — mantido"
fi

# ── 5. URLs do seed → domínio real (serializado-safe) ───────────
say "Ajustando URLs do seed para $SCHEME://$DOMAIN ..."
OLDURL="$(mysql -N lagospanel -e "SELECT option_value FROM lagos_options WHERE option_name='home' LIMIT 1;" 2>/dev/null || true)"
if [[ -n "$OLDURL" && "$OLDURL" != "$SCHEME://$DOMAIN" ]]; then
  cp database/replace-url.php public/replace-url.php
  php public/replace-url.php "$OLDURL" "$SCHEME://$DOMAIN" || true
  rm -f public/replace-url.php
fi
mysql -N lagospanel -e "UPDATE lagos_options SET option_value='${SCHEME}://${DOMAIN}' WHERE option_name IN ('home','siteurl');" || true
ok "URLs atualizadas"

# ── 6. Permissões ───────────────────────────────────────────────
chown -R www-data:www-data public/wp-content
find public -type d -exec chmod 755 {} \;
find public -type f -exec chmod 644 {} \;
ok "permissões aplicadas (www-data)"

# ── 7. Nginx vhost ──────────────────────────────────────────────
say "Criando vhost nginx..."
DOCROOT="$(cd public && pwd)"
cat > /etc/nginx/sites-available/lagospanel <<NGINX
server {
    listen 80;
    server_name ${DOMAIN};
    root ${DOCROOT};
    index index.php;

    charset utf-8;
    client_max_body_size 64m;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHPVER}-fpm.sock;
        fastcgi_read_timeout 120;
    }

    location ~* \.(js|css|png|jpg|jpeg|webp|svg|woff2?|ico)\$ {
        expires 30d;
        access_log off;
    }

    location = /wp-config.php { deny all; }
    location ~ /\.(?!well-known) { deny all; }
}
NGINX
ln -sf /etc/nginx/sites-available/lagospanel /etc/nginx/sites-enabled/lagospanel
rm -f /etc/nginx/sites-enabled/default
nginx -t > /dev/null 2>&1 || { nginx -t; die "config nginx inválida"; }
systemctl reload nginx 2>/dev/null || nginx -s reload 2>/dev/null || nginx 2>/dev/null || true
ok "nginx + PHP-FPM ativos"

# ── 8. Cron (WP-Cron real, fora do request) ─────────────────────
say "Agendando WP-Cron (a cada 5 min)..."
if command -v crontab > /dev/null 2>&1; then
  ( crontab -l 2>/dev/null | grep -v "wp-cron.php.*${DOMAIN}" || true; \
    echo "*/5 * * * * curl -fsS ${SCHEME}://${DOMAIN}/wp-cron.php > /dev/null 2>&1" ) | crontab -
  ok "cron instalado"
else
  say "crontab indisponível — agende manualmente: */5 * * * * curl -fsS ${SCHEME}://${DOMAIN}/wp-cron.php"
fi

# ── 9. HTTPS opcional ───────────────────────────────────────────
URL="$SCHEME://$DOMAIN"
if [[ $SSL -eq 1 ]]; then
  say "Instalando certificado Let's Encrypt..."
  apt-get install -y -qq certbot python3-certbot-nginx > /dev/null
  if certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos --register-unsafely-without-email -q; then
    ok "HTTPS ativo (Let's Encrypt)"
    URL="https://$DOMAIN"
    mysql -N lagospanel -e "UPDATE lagos_options SET option_value='https://${DOMAIN}' WHERE option_name IN ('home','siteurl');" || true
  else
    echo "  ⚠ certbot falhou (aponte o DNS antes de rodar com --ssl); painel no HTTP por enquanto"
  fi
fi

echo
echo "${BOLD}${GREEN}  Instalação concluída!${RESET}"
echo
echo "  Painel:      $URL"
echo "  Diretório:   $TARGET (docroot: $TARGET/public)"
echo "  Admin:       $URL/wp-admin/  (usuário: eduardo — troque a senha no 1º login!)"
echo "  Banco:       lagospanel · usuário lagos · senha: $DBPASS"
echo
echo "  Próximos passos:"
echo "   1. Trocar a senha do admin e configurar SMTP (Configurações)"
echo "   2. Gateways de pagamento (Mercado Pago, Stripe...) e módulos (Conexões)"
echo "   3. Opcional: zerar dados de demonstração com database/clean-demo.php"
echo
