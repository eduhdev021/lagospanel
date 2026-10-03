<?php

use App\Models\Service;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

// Real HTTP kernel + CSRF, with only outbound provider HTTP simulated.
$root = dirname(__DIR__);
if (getenv('LAGOS_TEST_MODE') !== '1' || ! str_contains($root, '/.cache/')) {
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
if (! app()->environment('local')) {
    http_response_code(404);
    exit;
}
config(['lagos.native_provisioning' => true]);
Http::preventStrayRequests();
Http::fake(function ($r) use ($root) {
    $f = json_decode(file_get_contents($root.'/.cache/controls-browser.json'), true);
    $mode = trim(@file_get_contents($root.'/.cache/controls-mode'));
    $path = parse_url($r->url(), PHP_URL_PATH);
    file_put_contents($root.'/.cache/controls-calls.jsonl', json_encode(['method' => $r->method(), 'path' => $path])."\n", FILE_APPEND | LOCK_EX);
    $s = Service::findOrFail(str_starts_with($path, '/json-api') ? $f['cpanel'] : $f['pterodactyl']);
    $p = $s->provisioning;
    if ($path === '/api/client/account') {
        return Http::response(['object' => 'user', 'attributes' => ['id' => $mode === 'wronguser' ? 42 : 41, 'admin' => false, 'email' => $p['email']]]);
    }
    if ($path === '/api/application/users/41') {
        return Http::response(['object' => 'user', 'attributes' => ['id' => 41, 'root_admin' => false, 'email' => $p['email']]]);
    }
    if (str_starts_with($path, '/api/application/servers/external/')) {
        return Http::response(['object' => 'server', 'attributes' => ['id' => 71, 'external_id' => $p['external_id'], 'user' => 41, 'egg' => $p['egg'], 'limits' => array_intersect_key($p, array_flip(['memory', 'disk', 'cpu', 'swap', 'io'])), 'feature_limits' => array_intersect_key($p, array_flip(['databases', 'allocations', 'backups'])), 'container' => ['image' => $p['docker_image']], 'status' => null, 'suspended' => false, 'uuid' => '01234567-89ab-4cde-8123-456789abcdef', 'identifier' => '01234567']]);
    }
    if ($path === '/api/client/servers/01234567-89ab-4cde-8123-456789abcdef/power') {
        return $mode === 'timeout' ? Http::failedConnection() : Http::response('', 204);
    }
    if ($path === '/json-api/listaccts') {
        $data = ['acct' => [['user' => $p['username'], 'domain' => $p['domain'], 'owner' => $p['whm_user'], 'plan' => $p['plan'], 'email' => $p['email'], 'suspended' => 0]]];
    } elseif ($path === '/json-api/create_user_session') {
        $session = $p['username'].':'.str_repeat('X', 48);
        $data = ['service' => 'cpaneld', 'cp_security_token' => '/cpsess1234567890', 'expires' => time() + 900, 'session' => $session, 'url' => ($mode === 'unsafe' ? 'https://evil.invalid' : $p['client_url']).'/cpsess1234567890/login/?session='.rawurlencode($session)];
    } else {
        throw new RuntimeException('Unexpected fixture request');
    }

    return Http::response(['metadata' => ['command' => basename($path), 'result' => 1, 'version' => 1], 'data' => $data]);
});
$app->handleRequest(Request::capture());
