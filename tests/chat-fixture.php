<?php

use App\Jobs\AnswerAiTurn;
use App\Models\AiSetting;
use App\Models\AiThread;
use App\Models\AiTurn;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

require __DIR__.'/../.cache/vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || config('database.connections.sqlite.database') !== base_path('.cache/experience.sqlite') || getenv('LAGOS_TEST_MODE') !== '1') {
    exit(1);
}
if (($argv[1] ?? '') === 'prepare') {
    $u = User::where('email', 'client@experience.invalid')->firstOrFail();
    AiThread::where('user_id', $u->id)->delete();
    AiSetting::updateOrCreate(['id' => 1], ['endpoint' => 'https://ollama-fixture.invalid', 'token' => 'fixture-token', 'model' => 'fixture-model', 'models' => ['fixture-model'], 'version' => 1, 'active' => true, 'web_enabled' => false]);
    echo 'prepared';
    exit;
}
$s = AiSetting::findOrFail(1);
if ($s->endpoint !== 'https://ollama-fixture.invalid') {
    exit(2);
}
$turn = AiTurn::whereKey((int) ($argv[2] ?? 0))->firstOrFail();
if ($turn->thread->user->email !== 'client@experience.invalid') {
    exit(3);
}
if (($argv[1] ?? '') === 'fail') {
    $turn->update(['status' => 'failed']);
    echo 'failed';
    exit;
}
Http::preventStrayRequests();
Http::fake(['https://ollama-fixture.invalid/api/chat' => Http::response(['done' => true, 'message' => ['role' => 'assistant', 'content' => "Claro! **Domínio** é o endereço do seu site. A hospedagem é o espaço onde os arquivos ficam.\n\n### Por onde começar\nConfira os registros de DNS informados pelo seu provedor e aguarde a propagação.\n\n```text\nexemplo.com → endereço da hospedagem\n```\n\n<script>window.chat_xss=1</script>\n\nSe precisar de uma alteração na sua conta, abra um chamado para a equipe."]])]);
(new AnswerAiTurn($turn->id))->handle();
echo $turn->fresh()->status;
