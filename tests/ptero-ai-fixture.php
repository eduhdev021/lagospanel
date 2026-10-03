<?php

use App\Http\Controllers\AiSettingsController;
use App\Jobs\AnswerAiTurn;
use App\Jobs\PollPterodactyl;
use App\Jobs\RunOperation;
use App\Models\AiSetting;
use App\Models\AiThread;
use App\Models\Service;
use App\Models\User;
use App\Services\Ollama;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Tests\FakeOllama;
use Tests\FakePterodactyl;

require __DIR__.'/../.cache/vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (getenv('LAGOS_TEST_MODE') !== '1' || ! app()->environment('local')) {
    exit(1);
}
$f = json_decode(file_get_contents(base_path('.cache/demo.json')), true);
if ($argv[1] === 'client') {
    echo User::where('email', $f['client'])->value('id');
    exit;
}
if (in_array($argv[1], ['ptero', 'installed'], true)) {
    $s = Service::with('connector', 'user')->findOrFail((int) $argv[2]);
    if ($s->connector?->endpoint !== 'https://ptero-fixture.invalid') {
        exit(2);
    }
    config(['lagos.native_provisioning' => true]);
    $file = base_path('.cache/ptero-browser-'.$s->id.'.json');
    require __DIR__.'/FakePterodactyl.php';
    FakePterodactyl::install($file, $s->provisioning['remote_user_id'], $s->provisioning['email'], true);
    $op = $s->operations()->latest('id')->firstOrFail();
    if ($argv[1] === 'installed') {
        $state = json_decode(file_get_contents($file), true);
        $state['server']['status'] = null;
        file_put_contents($file, json_encode($state));
        (new PollPterodactyl($op->id, $op->execution_token))->handle();
    } else {
        (new RunOperation($op->id))->handle();
    }
    echo json_encode(['status' => $s->fresh()->status, 'operation' => $op->fresh()->status, 'remote_id' => $s->fresh()->remote_id, 'calls' => json_decode(file_get_contents($file), true)['calls']]);
    exit;
}
$s = AiSetting::findOrFail(1);
if ($s->endpoint !== 'https://ollama-fixture.invalid') {
    exit(2);
}require __DIR__.'/FakeOllama.php';
FakeOllama::install(base_path('.cache/ollama-browser.json'));
if ($argv[1] === 'models') {
    $admin = User::where('email', $f['admin'])->firstOrFail();
    $r = Request::create('/admin/integracoes/ollama/modelos', 'POST');
    $r->setUserResolver(fn () => $admin);
    app(AiSettingsController::class)->models($r, app(Ollama::class));
    echo json_encode(['models' => $s->fresh()->models]);
} elseif ($argv[1] === 'answer') {
    $t = AiThread::findOrFail((int) $argv[2]);
    $turn = $t->turns()->latest('id')->firstOrFail();
    (new AnswerAiTurn($turn->id))->handle();
    echo json_encode(['status' => $turn->fresh()->status]);
} else {
    exit(2);
}
