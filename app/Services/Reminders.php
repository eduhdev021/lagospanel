<?php

namespace App\Services;

use App\Models\Invoice;
use App\Notifications\InvoiceReminder;
use Illuminate\Support\Facades\DB;

final class Reminders
{
    public function run(): int
    {
        if (! config('automation.reminders_enabled', true)) {
            return 0;
        }
        $count = 0;
        Invoice::whereIn('status', ['unpaid', 'overdue'])->where('created_at', '<=', now()->subHours(6))->whereDate('due_date', '<=', today()->addDays((int) config('automation.reminder_days', 1)))->chunkById(100, function ($invoices) use (&$count) {
            foreach ($invoices as $invoice) {
                $count += DB::transaction(function () use ($invoice) {
                    $i = Invoice::lockForUpdate()->findOrFail($invoice->id);
                    if (! in_array($i->status, ['unpaid', 'overdue'])) {
                        return 0;
                    }$stage = $i->due_date->lte(today()->subDays(7)) ? 'overdue_7' : ($i->due_date->lt(today()) ? 'overdue_1' : 'due_soon');
                    if (DB::table('invoice_reminders')->where('invoice_id', $i->id)->where('stage', $stage)->exists()) {
                        return 0;
                    }DB::table('invoice_reminders')->insert(['invoice_id' => $i->id, 'stage' => $stage, 'created_at' => now(), 'updated_at' => now()]);
                    $i->user->notify(new InvoiceReminder($i->id, $stage));

                    return 1;
                }, 5);
            }
        });

        return $count;
    }
}
