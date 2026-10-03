<?php

use App\Services\Maintenance;
use App\Services\OutgoingWebhooks;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('lagos:maintenance', function () {
    $this->line(json_encode(app(Maintenance::class)->run(), JSON_PRETTY_PRINT));
})->purpose('Gera renovações, marca vencidas e agenda suspensões.');
Schedule::command('lagos:maintenance')->hourly()->withoutOverlapping();

Artisan::command('lagos:webhooks', function () {
    $this->line((string) app(OutgoingWebhooks::class)->dispatchDue());
})->purpose('Enfileira entregas e recupera leases interrompidos de webhooks.');
Schedule::command('lagos:webhooks')->everyMinute()->withoutOverlapping();
