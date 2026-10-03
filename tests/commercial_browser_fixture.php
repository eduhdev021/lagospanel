<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

// Only seed the isolated local browser database; never change .env or APP_KEY.
$expected = realpath(__DIR__.'/../.cache/commercial-browser.sqlite');
if (! $expected || getenv('DB_CONNECTION') !== 'sqlite' || getenv('LAGOS_TEST_MODE') !== '1' || getenv('APP_ENV') !== 'local' || realpath(getenv('DB_DATABASE')) !== realpath(__DIR__.'/../.cache/commercial-browser.sqlite')) {
    fwrite(STDERR, "Use the isolated local browser database.\n");
    exit(1);
}
require __DIR__.'/../.cache/vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$password = 'LocalBrowserFixture123!';
$admin = User::firstOrCreate(['email' => 'commercial-admin@example.test'], ['name' => 'Equipe fictícia', 'password' => $password]);
$admin->forceFill(['email_verified_at' => now(), 'is_admin' => true])->save();
$client = User::firstOrCreate(['email' => 'commercial-client@example.test'], ['name' => 'Cliente fictício', 'password' => $password]);
$client->forceFill(['email_verified_at' => now()])->save();
file_put_contents(__DIR__.'/../.cache/commercial-browser.json', json_encode(['admin' => $admin->email, 'client' => $client->email, 'client_id' => $client->id, 'password' => $password]));
echo "Fixture local pronta.\n";
