<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Service;
use App\Notifications\InvoiceNotice;
use Illuminate\Support\Facades\DB;

final class Maintenance
{
    public function run(): array
    {
        $expired = app(OrderLifecycle::class)->expire();
        $made = 0;
        Service::where('status', 'active')->where('auto_renew', true)->whereNull('cancellation_requested_at')->whereNotNull('next_due')->whereDate('next_due', '<=', today()->addDays(5))->chunkById(100, function ($services) use (&$made) {
            foreach ($services as $service) {
                $made += DB::transaction(function () use ($service) {
                    $s = Service::lockForUpdate()->findOrFail($service->id);
                    if ($s->cycle === 'one_time' || $s->price_minor <= 0 || $s->invoices()->whereIn('status', ['unpaid', 'overdue'])->exists() || Invoice::where('renewal_service_id', $s->id)->whereDate('period_start', $s->next_due)->exists()) {
                        return 0;
                    }
                    $invoice = Invoice::create(['user_id' => $s->user_id, 'type' => 'renewal', 'total_minor' => $s->price_minor, 'snapshot' => [['name' => 'Renovação — '.$s->name, 'quantity' => 1, 'unit_minor' => $s->price_minor]], 'due_date' => $s->next_due, 'renewal_service_id' => $s->id, 'period_start' => $s->next_due]);
                    $invoice->services()->attach($s);
                    $s->user->notify(new InvoiceNotice($invoice->id));

                    return 1;
                }, 5);
            }
        });
        $overdue = Invoice::where('status', 'unpaid')->whereDate('due_date', '<', today())->update(['status' => 'overdue']);
        $queued = 0;
        Invoice::with('services')->where('status', 'overdue')->whereDate('due_date', '<=', today()->subDays(3))->chunkById(100, function ($invoices) use (&$queued) {
            foreach ($invoices as $invoice) {
                foreach ($invoice->services as $s) {
                    if ($s->status === 'active' && $s->connector_id) {
                        app(Provisioning::class)->enqueue($s, 'suspend', 'overdue:'.$invoice->id.':'.$s->id);
                        $queued++;
                    }
                }
            }
        });

        return ['reminders' => app(Reminders::class)->run(), 'expired_orders' => $expired, 'renewals' => $made, 'overdue' => $overdue, 'suspension_jobs_considered' => $queued];
    }
}
