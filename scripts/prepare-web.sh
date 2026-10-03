#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
command -v composer >/dev/null || { echo 'Instale Composer 2 antes de continuar.' >&2; exit 1; }
[[ -f .env ]] || cp .env.example .env
mkdir -p bootstrap/cache storage/framework/{cache/data,sessions,views} storage/logs storage/app/private .cache
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan lagos:setup
