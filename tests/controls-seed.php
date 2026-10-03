<?php

use App\Models\Connector;
use App\Models\Product;
use App\Models\PterodactylAccount;
use App\Models\User;
use App\Services\Billing;
use App\Services\Checkout;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

// Only an isolated local copy; never a production or developer database.
$root = dirname(__DIR__);
if (getenv('LAGOS_TEST_MODE') !== '1' || ! str_contains($root, '/.cache/')) {
    throw new RuntimeException('Isolated fixture only');
}
require $root.'/.cache/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local')) {
    throw new RuntimeException('Local fixture only');
}
config(['lagos.native_provisioning' => true]);
$password = bin2hex(random_bytes(20)).'Aa1!';
$u = User::create(['name' => 'Cliente controles', 'email' => 'controls-browser@example.test', 'password' => $password]);
$u->forceFill(['email_verified_at' => now()])->save();
$ids = [];
foreach (['cpanel', 'pterodactyl'] as $driver) {
    $c = Connector::create(['name' => 'Browser '.$driver, 'driver' => $driver, 'endpoint' => $driver === 'cpanel' ? 'https://whm-browser.invalid:2087' : 'https://ptero-browser.invalid', 'token' => 'application-browser-fixture-key', 'active' => true, 'settings' => ['username' => 'root', 'prefix' => 'br', 'client_url' => 'https://whm-browser.invalid:2083']]);
    $plan = $driver === 'cpanel' ? ['driver' => 'cpanel', 'plan' => 'basic', 'domain_suffix' => 'clients.example.test'] : ['driver' => 'pterodactyl', 'egg' => 1, 'location' => 1, 'docker_image' => 'fixture:image', 'startup' => 'run', 'environment' => [], 'memory' => 1024, 'disk' => 10240, 'cpu' => 100, 'swap' => 0, 'io' => 500, 'databases' => 0, 'allocations' => 1, 'backups' => 1];
    if ($driver === 'pterodactyl') {
        PterodactylAccount::create(['user_id' => $u->id, 'connector_id' => $c->id, 'remote_user_id' => 41]);
    }
    $p = Product::create(['name' => 'Browser '.$driver, 'slug' => 'browser-'.$driver, 'price_minor' => 100, 'setup_minor' => 0, 'cycle' => 'monthly', 'active' => true, 'connector_id' => $c->id, 'provisioning' => $plan]);
    $i = app(Checkout::class)->create($u, $p->id, 1, (string) Str::uuid());
    app(Billing::class)->settle($i->id, 'fixture', 'browser-'.$driver, 100, 'BRL');
    $s = $i->services->sole();
    $s->operations()->update(['status' => 'done']);
    $s->update(['status' => 'active', 'remote_id' => $driver === 'cpanel' ? $s->native_username : '71']);
    $ids[$driver] = $s->id;
}
file_put_contents($root.'/.cache/controls-browser.json', json_encode($ids + ['email' => $u->email, 'password' => $password]));
chmod($root.'/.cache/controls-browser.json', 0600);
echo "Isolated controls fixtures prepared.\n";
