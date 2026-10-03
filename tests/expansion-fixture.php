<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../.cache/vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (getenv('LAGOS_TEST_MODE') !== '1' || ! app()->environment('local')) {
    fwrite(STDERR, "Somente demonstração local isolada.\n");
    exit(1);
}
$password = bin2hex(random_bytes(12)).'Aa1';
$u = User::create(['name' => 'Operador Browser', 'email' => 'operator-'.bin2hex(random_bytes(5)).'@example.test', 'password' => $password]);
$u->forceFill(['email_verified_at' => now()])->save();
echo json_encode(['id' => $u->id, 'email' => $u->email, 'password' => $password]);
