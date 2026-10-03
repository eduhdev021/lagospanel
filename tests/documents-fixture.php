<?php

use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SupportDesk;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../.cache/vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (getenv('LAGOS_TEST_MODE') !== '1' || ! app()->environment('local')) {
    exit(1);
}
if (($argv[1] ?? '') === 'count') {
    echo Ticket::findOrFail((int) $argv[2])->replies()->count();
    exit;
}
$f = json_decode(file_get_contents(base_path('.cache/demo.json')), true);
$u = User::where('email', $f['client'])->firstOrFail();
$ticket = app(SupportDesk::class)->open($u, ['subject' => 'Modelos '.bin2hex(random_bytes(4)), 'body' => 'Teste local de rascunho', 'department' => 'support']);
$items = [];
for ($i = 1; $i <= 60; $i++) {
    $items[] = ['name' => 'Serviço de teste '.$i.($i === 60 ? ' FIMDOSITENS' : ''), 'quantity' => 1, 'unit_minor' => 100, 'setup_minor' => 0];
}
$items[0]['name'] = 'Ação <script>window.pdf_xss=1</script> <img src="https://example.invalid/test">';
$invoice = Invoice::create(['user_id' => $u->id, 'total_minor' => 6000, 'status' => 'unpaid', 'type' => 'order', 'due_date' => today()->addDays(3), 'snapshot' => $items]);
$other = Invoice::create(['user_id' => User::where('email', $f['admin'])->firstOrFail()->id, 'total_minor' => 100, 'status' => 'unpaid', 'due_date' => today(), 'snapshot' => []]);
echo json_encode(['ticket' => $ticket->id, 'invoice' => $invoice->id, 'other' => $other->id]);
