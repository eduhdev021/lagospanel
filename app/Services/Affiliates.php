<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class Affiliates
{
    public const MIN_WITHDRAWAL_MINOR = 2000; // R$ 20,00

    public function enroll(User $user): Affiliate
    {
        return DB::transaction(function () use ($user) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($existing = Affiliate::where('user_id', $user->id)->first()) {
                return $existing;
            }
            do {
                $code = 'REF-'.strtoupper(Str::random(8));
            } while (Affiliate::where('code', $code)->exists());

            $affiliate = Affiliate::create([
                'user_id' => $user->id,
                'code' => $code,
                'rate_percent' => 10,
                'active' => true,
            ]);
            Audit::record('affiliate.enrolled', 'affiliate:'.$affiliate->id, ['code' => $code], $user->id);

            return $affiliate;
        }, 5);
    }

    public function trackClick(string $code): ?Affiliate
    {
        $code = strtoupper(trim($code));
        if ($code === '' || strlen($code) > 40) {
            return null;
        }
        $affiliate = Affiliate::where('code', $code)->where('active', true)->first();
        if ($affiliate) {
            $affiliate->increment('clicks');
        }

        return $affiliate;
    }

    public function creditForInvoice(Invoice $invoice): ?AffiliateCommission
    {
        if ($invoice->type === 'deposit' || $invoice->total_minor <= 0) {
            return null;
        }
        $customer = $invoice->user;
        if (! $customer || ! $customer->referred_by_id || $customer->referred_by_id === $customer->id) {
            return null;
        }
        $affiliate = Affiliate::where('user_id', $customer->referred_by_id)->where('active', true)->lockForUpdate()->first();
        if (! $affiliate) {
            return null;
        }
        if ($existing = AffiliateCommission::where('invoice_id', $invoice->id)->first()) {
            return $existing;
        }
        $commissionMinor = intdiv($invoice->total_minor * $affiliate->rate_percent, 100);
        if ($commissionMinor <= 0) {
            return null;
        }
        $commission = AffiliateCommission::create([
            'affiliate_id' => $affiliate->id,
            'referred_user_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount_minor' => $commissionMinor,
            'status' => 'credited',
        ]);
        $affiliate->increment('available_minor', $commissionMinor);
        $affiliate->increment('total_earned_minor', $commissionMinor);
        Audit::record('affiliate.commission_credited', 'affiliate:'.$affiliate->id, ['invoice_id' => $invoice->id, 'amount_minor' => $commissionMinor]);

        return $commission;
    }

    public function withdrawToWallet(User $user, ?int $amountMinor = null): int
    {
        return DB::transaction(function () use ($user, $amountMinor) {
            $affiliate = Affiliate::where('user_id', $user->id)->lockForUpdate()->first();
            if (! $affiliate || ! $affiliate->active) {
                throw ValidationException::withMessages(['affiliate' => 'Conta de afiliado não encontrada ou inativa.']);
            }
            $withdraw = $amountMinor ?? $affiliate->available_minor;
            if ($withdraw < self::MIN_WITHDRAWAL_MINOR || $withdraw > $affiliate->available_minor) {
                throw ValidationException::withMessages(['affiliate' => 'Saldo disponível insuficiente para resgate (mínimo de R$ 20,00).']);
            }
            $affiliate->decrement('available_minor', $withdraw);
            $affiliate->increment('total_withdrawn_minor', $withdraw);
            app(Billing::class)->wallet(
                $user->id,
                $withdraw,
                'affiliate-withdrawal:'.$affiliate->id.':'.$affiliate->total_withdrawn_minor,
                'Resgate de comissões de afiliado ('.$affiliate->code.')'
            );
            Audit::record('affiliate.withdrawn', 'affiliate:'.$affiliate->id, ['amount_minor' => $withdraw], $user->id);

            return $withdraw;
        }, 5);
    }
}
