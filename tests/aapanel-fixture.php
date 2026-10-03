<?php

use App\Jobs\RunOperation;
use App\Models\Service;
use Illuminate\Contracts\Console\Kernel;
use Tests\FakeAaPanel;

require __DIR__.'/../.cache/vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (getenv('LAGOS_TEST_MODE') !== '1' || ! app()->environment('local')) {
    exit(1);
}
$s = Service::findOrFail((int) ($argv[1] ?? 0));
if ($s->connector?->driver !== 'aapanel' || $s->connector->endpoint !== 'https://aapanel-fixture.invalid:7800') {
    exit(2);
}
require __DIR__.'/FakeAaPanel.php';
$path = base_path('.cache/aapanel-'.$s->id.'.json');
FakeAaPanel::install($path, $s->connector->token);
config(['lagos.native_provisioning' => true]);
$op = $s->operations()->where('status', 'pending')->latest('id')->firstOrFail();
(new RunOperation($op->id))->handle();
if ($op->fresh()->status !== 'done') {
    fwrite(STDERR, $op->fresh()->error);
    exit(3);
}
echo json_encode(['status' => $s->fresh()->status, 'remote_id' => $s->fresh()->remote_id, 'calls' => json_decode(file_get_contents($path), true)['calls']]);
