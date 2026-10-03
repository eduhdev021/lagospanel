#!/usr/bin/env bash
# LagosPanel — monta o pacote de deploy (arquivos + banco + ferramentas)
# Uso: bash scripts/deploy/build-deploy.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
OUT="$ROOT/deploy"
SOCK="${LAGOS_DB_SOCKET:-/tmp/lagos.sock}"
DB="${LAGOS_DB_NAME:-lagospanel}"

mkdir -p "$OUT"

echo "→ Dump do banco ($DB via $SOCK)..."
mysqldump --socket="$SOCK" -u root --single-transaction --quick "$DB" | gzip > "$OUT/db.sql.gz"

echo "→ Compactando arquivos do WordPress..."
rm -f "$OUT/lagospanel-files.zip"
( cd "$ROOT/public" && zip -qr "$OUT/lagospanel-files.zip" . -x "wp-content/debug.log" -x "wp-content/cache/*" )

echo "→ Copiando ferramentas..."
cp "$ROOT/scripts/deploy/replace-url.php" "$OUT/"
cp "$ROOT/scripts/deploy/clean-demo.php"  "$OUT/"
cp "$ROOT/scripts/deploy/INSTALL.md"      "$OUT/"

echo ""
echo "═══ Pacote pronto em: $OUT ═══"
ls -lh "$OUT" | awk '{print $5, $9}'
