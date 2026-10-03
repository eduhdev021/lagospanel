<?php

use App\Jobs\AnswerAiTurn;
use App\Jobs\PreparePterodactylAccount;
use App\Jobs\RunOperation;
use App\Models\AiThread;
use App\Models\Connector;
use App\Models\Operation;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AiChat;
use App\Services\Billing;
use App\Services\Checkout;
use App\Services\OrderLifecycle;
use App\Services\Provisioning;
use App\Services\PterodactylUsers;
use App\Services\SupportDesk;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\FakeAaPanel;
use Tests\FakeOllama;
use Tests\FakePterodactyl;
use Tests\FakePterodactylUsers;
use Tests\FakeWhm;

if (getenv('LAGOS_TEST_MODE') !== '1') {
    fwrite(STDERR, "Apenas teste isolado.\n");
    exit(1);
}
require __DIR__.'/../.cache/vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
try {
    if ($argv[1] === 'ptero-purchase-account') {
        require __DIR__.'/FakePterodactylUsers.php';
        config(['lagos.native_provisioning' => true]);
        FakePterodactylUsers::install(base_path('.cache/concurrent-purchase-users.json'));
        $op = Operation::findOrFail((int) $argv[2]);
        if ($op->status === 'processing') {
            (new PreparePterodactylAccount($op->id, $op->execution_token))->handle();
        }
    } elseif ($argv[1] === 'ptero-user') {
        require __DIR__.'/FakePterodactylUsers.php';
        config(['lagos.native_provisioning' => true]);
        FakePterodactylUsers::install(base_path('.cache/concurrent-ptero-users.json'));
        try {
            $r = app(PterodactylUsers::class)->create(Connector::findOrFail((int) $argv[2]), User::findOrFail(1), ['first_name' => 'Concurrent', 'last_name' => 'User'], 1);
            if ($r->status !== 'done') {
                exit(3);
            }
        } catch (HttpExceptionInterface $e) {
            if ($e->getStatusCode() !== 409) {
                throw $e;
            }echo "BUSY\n";
            exit(2);
        }
    } elseif ($argv[1] === 'ptero-run') {
        require __DIR__.'/FakePterodactyl.php';
        config(['lagos.native_provisioning' => true]);
        FakePterodactyl::install(base_path('.cache/concurrent-ptero.json'), 41, 'concurrent@example.test');
        (new RunOperation((int) $argv[2]))->handle();
    } elseif ($argv[1] === 'ai-send') {
        $t = AiThread::findOrFail((int) $argv[2]);
        app(AiChat::class)->send($t->user, $t, 'Concurrent message', $argv[3]);
    } elseif ($argv[1] === 'ai-answer') {
        require __DIR__.'/FakeOllama.php';
        FakeOllama::install(base_path('.cache/concurrent-ollama.json'));
        (new AnswerAiTurn((int) $argv[2]))->handle();
    } elseif ($argv[1] === 'aapanel-run') {
        require __DIR__.'/FakeAaPanel.php';
        config(['lagos.native_provisioning' => true]);
        FakeAaPanel::install(base_path('.cache/concurrent-aa.json'), 'concurrent-aa-key');
        (new RunOperation((int) $argv[2]))->handle();
    } elseif ($argv[1] === 'native-run') {
        require __DIR__.'/FakeWhm.php';
        config(['lagos.native_provisioning' => true]);
        FakeWhm::install(base_path('.cache/concurrent-whm.json'), 'concurrent-native-token');
        (new RunOperation((int) $argv[2]))->handle();
    } elseif ($argv[1] === 'native-enqueue') {
        app(Provisioning::class)->enqueue(Service::findOrFail((int) $argv[2]), 'suspend', 'native-suspend:'.$argv[3]);
    } elseif ($argv[1] === 'support-account-upload') {
        config(['support.account_attachment_bytes' => 2097152]);
        $bytes = str_repeat('B', 1048576);
        app(SupportDesk::class)->reply(Ticket::findOrFail((int) $argv[2]), User::findOrFail(2), 'Account quota race', [['filename' => 'race.txt', 'mime' => 'text/plain', 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'payload' => base64_encode($bytes)]]);
    } elseif ($argv[1] === 'support-upload') {
        $bytes = str_repeat('A', 1048576);
        app(SupportDesk::class)->reply(Ticket::findOrFail(1), User::findOrFail(1), 'Concurrent upload', [['filename' => 'race.txt', 'mime' => 'text/plain', 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'payload' => base64_encode($bytes)]]);
    } elseif ($argv[1] === 'support-close') {
        app(SupportDesk::class)->state(Ticket::findOrFail(2), User::findOrFail(1), 'closed');
    } elseif ($argv[1] === 'support-reply') {
        app(SupportDesk::class)->reply(Ticket::findOrFail(2), User::findOrFail(1), 'Concurrent reply');
    } elseif ($argv[1] === 'checkout') {
        app(Checkout::class)->cart(User::findOrFail((int) $argv[2]), [['product_id' => (int) $argv[3], 'quantity' => 1]], $argv[4]);
    } elseif ($argv[1] === 'cancel') {
        app(OrderLifecycle::class)->cancel((int) $argv[2], null, 'Isolated race test');
    } elseif ($argv[1] === 'expire') {
        app(OrderLifecycle::class)->expire();
    } elseif ($argv[1] === 'pay') {
        app(Billing::class)->payWithWallet(User::findOrFail(1), (int) $argv[2]);
    } else {
        app(Billing::class)->settle((int) $argv[2], 'test', $argv[3], 100, 'BRL');
    }echo "OK\n";
} catch (HttpExceptionInterface $e) {
    if ($e->getStatusCode() !== 422) {
        throw $e;
    }
    echo "REJECTED\n";
    exit(2);
} catch (ValidationException $e) {
    echo "REJECTED\n";
    exit(2);
}
