<?php

use App\Jobs\RunOperation;
use App\Models\Service;
use Illuminate\Contracts\Console\Kernel;
use Tests\FakeWhm;

require __DIR__.'/../.cache/vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (getenv('LAGOS_TEST_MODE') !== '1' || ! app()->environment('local')) {
    exit(1);
}
$s = Service::findOrFail((int) ($argv[1] ?? 0));
if ($s->connector?->endpoint !== 'https://whm-fixture.invalid:2087' || $s->connector->driver !== 'cpanel') {
    exit(2);
}
require __DIR__.'/FakeWhm.php';
$path = base_path('.cache/native-whm-'.$s->id.'.json');
FakeWhm::install($path, $s->connector->token);
config(['lagos.native_provisioning' => true]);
$op = $s->operations()->where('status', 'pending')->latest('id')->firstOrFail();
(new RunOperation($op->id))->handle();
if ($op->fresh()->status !== 'done') {
    fwrite(STDERR, 'Fixture failed: '.$op->fresh()->error);
    exit(3);
}
$state = json_decode(file_get_contents($path), true);
echo json_encode(['service_id' => $s->id, 'operation' => $op->id, 'status' => $s->fresh()->status, 'username' => $s->native_username, 'password_hash' => $state['password_hashes'][$s->native_username] ?? null, 'calls' => $state['calls']]);
