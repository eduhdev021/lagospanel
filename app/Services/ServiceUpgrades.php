<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceUpgrade;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ServiceUpgrades
{
    public const CYCLE_DAYS = [
        'daily' => 1,
        'weekly' => 7,
        'monthly' => 30,
        'quarterly' => 90,
        'semiannual' => 180,
        'annual' => 365,
        'biennial' => 730,
        'triennial' => 1095,
        'once' => 30,
    ];

    public function quote(Service $service, Product $target): array
    {
        $totalDays = self::CYCLE_DAYS[$service->cycle] ?? 30;
        $today = CarbonImmutable::today();
        $due = $service->next_due ? CarbonImmutable::instance($service->next_due) : $today->addDays($totalDays);
        $remainingDays = max(1, min($totalDays, (int) $today->diffInDays($due, false)));

        $currentRemaining = intdiv($service->price_minor * $remainingDays, $totalDays);
        $targetTotalDays = self::CYCLE_DAYS[$target->cycle] ?? 30;
        $targetRemaining = intdiv($target->price_minor * $remainingDays, $targetTotalDays);
        $delta = $targetRemaining - $currentRemaining;

        return [
            'remaining_days' => $remainingDays,
            'total_days' => $totalDays,
            'current_remaining_minor' => $currentRemaining,
            'target_remaining_minor' => $targetRemaining,
            'delta_minor' => $delta,
        ];
    }

    public function request(User $user, Service $service, Product $target, string $requestKey): ServiceUpgrade
    {
        return DB::transaction(function () use ($user, $service, $target, $requestKey) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($existing = ServiceUpgrade::where('user_id', $user->id)->where('request_key', $requestKey)->first()) {
                return $existing;
            }
            $service = Service::lockForUpdate()->findOrFail($service->id);
            abort_unless($service->user_id === $user->id, 404);
            if ($service->status !== 'active') {
                throw ValidationException::withMessages(['service' => 'Somente serviços ativos podem realizar upgrade ou downgrade.']);
            }
            if (! $target->active || ! $target->allow_upgrade || $service->product_id === $target->id) {
                throw ValidationException::withMessages(['product_id' => 'Plano de destino indisponível para mudança proporcional.']);
            }
            if (ServiceUpgrade::where('service_id', $service->id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['service' => 'Já existe uma solicitação de mudança pendente para este serviço.']);
            }

            $q = $this->quote($service, $target);
            $delta = $q['delta_minor'];

            if ($delta > 0) {
                $expiry = now()->addMinutes((int) config('lagos.reservation_minutes', 1440));
                $invoice = Invoice::create([
                    'user_id' => $user->id,
                    'type' => 'upgrade',
                    'total_minor' => $delta,
                    'due_date' => $expiry->toDateString(),
                    'expires_at' => $expiry,
                    'snapshot' => [[
                        'name' => 'Upgrade proporcional — '.$service->name.' para '.$target->name.' ('.$q['remaining_days'].' dias restantes)',
                        'quantity' => 1,
                        'unit_minor' => $delta,
                    ]],
                ]);
                $invoice->services()->attach($service->id);
                $upgrade = ServiceUpgrade::create([
                    'user_id' => $user->id,
                    'service_id' => $service->id,
                    'from_product_id' => $service->product_id,
                    'to_product_id' => $target->id,
                    'invoice_id' => $invoice->id,
                    'delta_minor' => $delta,
                    'status' => 'pending',
                    'request_key' => $requestKey,
                ]);
                Audit::record('service.upgrade_requested', 'service:'.$service->id, ['upgrade_id' => $upgrade->id, 'delta_minor' => $delta, 'invoice_id' => $invoice->id], $user->id);

                return $upgrade;
            }

            $upgrade = ServiceUpgrade::create([
                'user_id' => $user->id,
                'service_id' => $service->id,
                'from_product_id' => $service->product_id,
                'to_product_id' => $target->id,
                'delta_minor' => $delta,
                'status' => 'completed',
                'request_key' => $requestKey,
            ]);
            $service->update([
                'product_id' => $target->id,
                'name' => $target->name,
                'price_minor' => $target->price_minor,
                'cycle' => $target->cycle,
            ]);
            if ($delta < 0) {
                app(Billing::class)->wallet($user->id, abs($delta), 'downgrade-credit:'.$upgrade->id, 'Crédito proporcional de downgrade #'.$upgrade->id);
            }
            Audit::record('service.downgraded', 'service:'.$service->id, ['upgrade_id' => $upgrade->id, 'delta_minor' => $delta], $user->id);

            return $upgrade;
        }, 5);
    }

    public function completeInvoice(Invoice $invoice): void
    {
        $upgrade = ServiceUpgrade::where('invoice_id', $invoice->id)->lockForUpdate()->first();
        if (! $upgrade || $upgrade->status === 'completed') {
            return;
        }
        $service = Service::lockForUpdate()->find($upgrade->service_id);
        $target = Product::find($upgrade->to_product_id);
        if ($service && $target) {
            $service->update([
                'product_id' => $target->id,
                'name' => $target->name,
                'price_minor' => $target->price_minor,
                'cycle' => $target->cycle,
            ]);
        }
        $upgrade->update(['status' => 'completed']);
        Audit::record('service.upgraded', 'service:'.($service?->id ?? 0), ['upgrade_id' => $upgrade->id, 'invoice_id' => $invoice->id]);
    }
}
