#!/bin/bash
# =========================================================
# LagosPanel — Setup do ambiente
# Rode este script se o sandbox foi reiniciado (pacotes não
# persistem entre sessões). Os DADOS e CÓDIGO persistem.
# Uso: bash scripts/setup.sh
# =========================================================
set -e

echo "→ Instalando pacotes (PHP + MariaDB)..."
sudo apt-get update -qq 2>/dev/null || true
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
    php-cli php-mysql php-sqlite3 php-mbstring php-xml php-curl php-gd php-zip \
    mariadb-server mariadb-client >/dev/null 2>&1
echo "✓ Pacotes ok"

# Inicializa o datadir se estiver vazio (primeira vez) — SEM InnoDB (Aria):
# datadir fica em ~5MB (vs ~65MB com InnoDB), cabendo no limite de snapshot.
if [ ! -d /home/user/lagospanel/data/mysql/mysql ]; then
    echo "→ Inicializando banco de dados (primeira vez)..."
    mkdir -p /home/user/lagospanel/data/mysql
    mariadb-install-db --datadir=/home/user/lagospanel/data/mysql \
        --user=user --auth-root-authentication-method=normal \
        --skip-innodb --default-storage-engine=Aria >/dev/null 2>&1
fi
echo "✓ Setup concluído!"
echo ""
echo "Agora inicie os serviços:"
echo "  1. MariaDB:  /usr/sbin/mariadbd --datadir=/home/user/lagospanel/data/mysql --socket=/tmp/lagos.sock --port=3306 --bind-address=127.0.0.1 --user=user --skip-name-resolve --pid-file=/home/user/lagospanel/data/mysqld.pid --skip-innodb --default-storage-engine=Aria"
echo "  2. Banco+WP: mariadb --socket=/tmp/lagos.sock -u root -e \"CREATE DATABASE IF NOT EXISTS lagospanel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS 'lagos'@'localhost' IDENTIFIED BY 'Lgx2026Panel!DB'; CREATE USER IF NOT EXISTS 'lagos'@'127.0.0.1' IDENTIFIED BY 'Lgx2026Panel!DB'; GRANT ALL PRIVILEGES ON lagospanel.* TO 'lagos'@'localhost'; GRANT ALL PRIVILEGES ON lagospanel.* TO 'lagos'@'127.0.0.1'; FLUSH PRIVILEGES;\" && php scripts/install-wp.php"
echo "  3. Site:     php -S 0.0.0.0:8080 -t /home/user/lagospanel/public /home/user/lagospanel/router.php"
