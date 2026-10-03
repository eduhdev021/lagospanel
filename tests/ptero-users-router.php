<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Tests\FakePterodactylUsers;

// Browser-only harness. Never install this as a production router.
$root = dirname(__DIR__);
if (getenv('LAGOS_TEST_MODE') !== '1' || getenv('APP_ENV') !== 'local' || getenv('DB_DATABASE') !== $root.'/.cache/ptero-users-browser.sqlite') {
    http_response_code(404);
    exit;
}
$file = realpath($root.'/public'.parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($file && str_starts_with($file, $root.'/public/') && is_file($file) && in_array(pathinfo($file, PATHINFO_EXTENSION), ['css', 'js', 'png', 'jpg', 'svg'], true)) {
    return false;
}
require $root.'/.cache/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require_once __DIR__.'/FakePterodactylUsers.php';
FakePterodactylUsers::install($root.'/.cache/ptero-users-browser-remote.json', trim(@file_get_contents($root.'/.cache/ptero-users-browser-mode')) === 'timeout');
config(['lagos.native_provisioning' => true]);
$app->handleRequest(Request::capture());
