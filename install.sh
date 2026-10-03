#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════
#  LagosPanel — Instalador de produção (Ubuntu/Debian)
#  © 2026 Lagos Soluções — Todos os direitos reservados.
#
#  Uso (dentro da pasta do projeto, como root):
#    ./install.sh --domain painel.seudominio.com.br
#
#  Opções:
#    --domain   DOMÍNIO        (obrigatório) domínio do painel
#    --db-pass  SENHA          senha do banco (gerada se omitida)
#    --ssl                     instala Let's Encrypt (certbot) ao final
#    --import   ARQUIVO.sql.gz importa um banco em vez do seed do deploy/
# ════════════════════════════════════════════════════════════════
set -euo pipefail

BOLD=$'\033[1m'; PURPLE=$'\033[38;5;135m'; GREEN=$'\033[38;5;72m'; RESET=$'\033[0m'
say() { echo "${PURPLE}▸${RESET} $*"; }
ok()  { echo "${GREEN}✓${RESET} $*"; }
die() { echo "ERRO: $*" >&2; exit 1; }

DOMAIN="" DBPASS="" SSL=0 IMPORT=""
while [[ $# -gt 0 ]]; do
  case "$1" in
    --domain)  DOMAIN="$2"; shift 2 ;;
    --db-pass) DBPASS="$2"; shift 2 ;;
    --ssl)     SSL=1; shift ;;
    --import)  IMPORT="$2"; shift 2 ;;
    *) die "opção desconhecida: $1" ;;
  esac
done

[[ -n "$DOMAIN" ]] || die "informe --domain painel.seudominio.com.br"
[[ $EUID -eq 0 ]] || die "rode como root (sudo ./install.sh)"
[[ -f public/wp-config.php ]] || die "execute dentro da pasta do projeto (com public/ e deploy/)"
command -v apt-get > /dev/null || die "este instalador é para Ubuntu/Debian (apt)"

[[ -n "$DBPASS" ]] || DBPASS="$(head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 20)"
SEED="${IMPORT:-deploy/db.sql.gz}"
[[ -f "$SEED" ]] || die "seed não encontrado: $SEED"

echo
echo "${BOLD}${PURPLE}  LagosPanel — instalação de produção${RESET}"
echo "  Domínio: $DOMAIN"
echo "  Seed:    $SEED"
echo

# ── 1. Dependências ─────────────────────────────────────────────
say "Instalando dependências (nginx, MariaDB, PHP-FPM)..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq nginx mariadb-server php-fpm php-mysql php-curl php-mbstring \
    php-xml php-zip php-imap unzip curl > /dev/null
PHPVER="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')"
ok "nginx + MariaDB + PHP $PHPVER"

# ── 2. Banco de dados ───────────────────────────────────────────
say "Criando banco 'lagospanel' e usuário 'lagos'..."
mysql -e "CREATE DATABASE IF NOT EXISTS lagospanel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS 'lagos'@'localhost' IDENTIFIED BY '${DBPASS//\'/\\\'}';"
mysql -e "GRANT ALL PRIVILEGES ON lagospanel.* TO 'lagos'@'localhost'; FLUSH PRIVILEGES;"
say "Importando seed (isso pode demorar um pouco)..."
zcat "$SEED" | mysql lagospanel
ok "banco importado"

# ── 3. URLs do seed → domínio real ──────────────────────────────
say "Ajustando URLs ($SEED → https://$DOMAIN)..."
if [[ -f deploy/replace-url.php ]]; then
  php deploy/replace-url.php "https://$DOMAIN" || true
else
  mysql lagospanel -e "UPDATE wp_options SET option_value='https://$DOMAIN' WHERE option_name IN ('home','siteurl');"
fi
ok "URLs atualizadas"

# ── 4. wp-config.php ────────────────────────────────────────────
say "Configurando wp-config.php..."
sed -i "s/^define( *'DB_USER'.*/define( 'DB_USER', 'lagos' );/" public/wp-config.php
sed -i "s/^define( *'DB_PASSWORD'.*/define( 'DB_PASSWORD', '$DBPASS' );/" public/wp-config.php
sed -i "s/^define( *'DB_HOST'.*/define( 'DB_HOST', 'localhost' );/" public/wp-config.php
sed -i "s/^define( *'WP_HOME'.*/define( 'WP_HOME', 'https:\/\/$DOMAIN' );/" public/wp-config.php
grep -q "DISABLE_WP_CRON" public/wp-config.php || \
  echo "define( 'DISABLE_WP_CRON', true );" >> public/wp-config.php
ok "wp-config.php pronto"

# ── 5. Chaves de segurança + dono dos arquivos ──────────────────
SALT="$(curl -fsS https://api.wordpress.org/secret-key/1.1/salt/ 2>/dev/null || true)"
[[ -n "$SALT" ]] && php -r '
$cfg = file_get_contents("public/wp-config.php");
$salt = file_get_contents("php://stdin");
if (strpos($cfg, "put your unique phrase here") !== false) {
    $cfg = preg_replace("/(define\(.AUTH_KEY.{0,30}put your unique phrase here.\);.*?define\(.NONCE_SALT[^\n]*\n)/s", $salt . "\n", $cfg, 1);
    file_put_contents("public/wp-config.php", $cfg);
    echo "chaves de segurança geradas\n";
}' <<< "$SALT" || true
chown -R www-data:www-data public/wp-content
find public -type d -exec chmod 755 {} \;
ok "permissões aplicadas (www-data)"

# ── 6. Nginx + PHP-FPM ──────────────────────────────────────────
say "Criando vhost nginx..."
DOCROOT="$(pwd)/public"
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

    location ~ \\.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHPVER}-fpm.sock;
        fastcgi_read_timeout 120;
    }

    location ~* \\.(js|css|png|jpg|jpeg|webp|svg|woff2?|ico)\$ {
        expires 30d;
        access_log off;
    }

    location = /wp-config.php { deny all; }
    location ~ /\\.(?!well-known) { deny all; }
}
NGINX
ln -sf /etc/nginx/sites-available/lagospanel /etc/nginx/sites-enabled/lagospanel
rm -f /etc/nginx/sites-enabled/default
nginx -t > /dev/null 2>&1 || { nginx -t; die "config nginx inválida"; }
systemctl reload nginx
systemctl enable --now php${PHPVER}-fpm mariadb nginx > /dev/null 2>&1 || true
ok "nginx + PHP-FPM ativos"

# ── 7. Cron (WP-Cron real, fora do request) ─────────────────────
say "Agendando WP-Cron (a cada 5 min)..."
( crontab -l 2>/dev/null | grep -v "wp-cron.php.*${DOMAIN}"; \
  echo "*/5 * * * * curl -fsS https://${DOMAIN}/wp-cron.php > /dev/null 2>&1" ) | crontab -
ok "cron instalado"

# ── 8. HTTPS opcional ───────────────────────────────────────────
URL="http://$DOMAIN"
if [[ $SSL -eq 1 ]]; then
  say "Instalando certificado Let's Encrypt..."
  apt-get install -y -qq certbot python3-certbot-nginx > /dev/null
  if certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos --register-unsafely-without-email -q; then
    ok "HTTPS ativo (Let's Encrypt)"
    URL="https://$DOMAIN"
    mysql lagospanel -e "UPDATE wp_options SET option_value='https://$DOMAIN' WHERE option_name IN ('home','siteurl');"
  else
    echo "  ⚠ certbot falhou (aponte o DNS antes de rodar com --ssl); painel no HTTP por enquanto"
  fi
fi

echo
echo "${BOLD}${GREEN}  Instalação concluída!${RESET}"
echo
echo "  Painel:      $URL"
echo "  Admin:       $URL/wp-admin/  (troque a senha no 1º login!)"
echo "  Banco:       lagospanel · usuário lagos · senha: $DBPASS"
echo
echo "  Próximos passos:"
echo "   1. Troque as senhas demo (admin e cliente@lagos.com)"
echo "   2. Configurações → SMTP + gateways (Mercado Pago, Stripe...)"
echo "   3. Conexões → módulos reais (Pterodactyl, cPanel...)"
echo "   4. Rodar deploy/clean-demo.php se quiser zerar os dados de demonstração"
echo
