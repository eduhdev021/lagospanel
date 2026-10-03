#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
command -v composer >/dev/null || { echo 'Instale Composer 2 pelo site oficial antes de continuar.' >&2; exit 1; }
[[ -f .env ]] || { cp .env.example .env; echo 'Arquivo .env criado. Configure domínio, banco, e-mail e cookies antes de continuar.'; exit 1; }
mkdir -p bootstrap/cache storage/framework/{cache/data,sessions,views} storage/logs .cache
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan config:clear
php artisan route:clear
php artisan view:clear
php -r 'require ".cache/vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); if(!config("app.key")) {Illuminate\Support\Facades\Artisan::call("key:generate",["--force"=>true]);echo "APP_KEY criada. Guarde uma cópia segura.\n";} if(config("database.default")==="sqlite"){$p=config("database.connections.sqlite.database");if(!str_starts_with($p,"/"))$p=base_path($p);if(!is_file($p)){if(!is_dir(dirname($p)))mkdir(dirname($p),0700,true);touch($p);chmod($p,0600);}}'
php artisan migrate --force
php artisan view:cache
php artisan lagos:doctor
printf '\nCrie seu administrador: php artisan lagos:admin seu-email@exemplo.com\n'
