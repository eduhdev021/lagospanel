<?php

namespace App\Services;

use App\Models\DomainRegistration;
use App\Models\DomainTld;
use App\Models\Invoice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class Domains
{
    public static function normalize(string $raw): string
    {
        $d = strtolower(trim($raw));
        $d = preg_replace('~^https?://~i', '', $d);
        $d = rtrim(explode('/', $d)[0] ?? '', '.');

        return $d;
    }

    public function matchTld(string $domain): ?DomainTld
    {
        $tlds = DomainTld::where('active', true)->get()->sortByDesc(fn ($t) => strlen($t->tld));
        foreach ($tlds as $tld) {
            $suffix = '.'.ltrim(strtolower($tld->tld), '.');
            if (str_ends_with($domain, $suffix) && strlen($domain) > strlen($suffix)) {
                $label = substr($domain, 0, -strlen($suffix));
                if (! str_contains($label, '.') && preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $label)) {
                    return $tld;
                }
            }
        }

        return null;
    }

    public function lookup(string $rawDomain): array
    {
        $domain = self::normalize($rawDomain);
        if ($domain === '' || strlen($domain) > 190 || ! filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return ['valid' => false, 'domain' => $domain, 'available' => false, 'tld' => null];
        }
        $tld = $this->matchTld($domain);
        if (! $tld) {
            return ['valid' => true, 'domain' => $domain, 'available' => false, 'tld' => null];
        }
        $taken = DomainRegistration::where('domain', $domain)->where('status', '!=', 'cancelled')->exists();

        return [
            'valid' => true,
            'domain' => $domain,
            'available' => ! $taken,
            'tld' => $tld,
        ];
    }

    public function validateNameservers(array $nameservers): array
    {
        $clean = [];
        foreach ($nameservers as $ns) {
            $ns = strtolower(trim((string) $ns));
            if ($ns === '') {
                continue;
            }
            if (strlen($ns) > 190 || ! str_contains($ns, '.') || ! filter_var($ns, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || filter_var($ns, FILTER_VALIDATE_IP)) {
                throw ValidationException::withMessages(['nameservers' => 'Informe nameservers válidos (ex.: ns1.seudominio.com).']);
            }
            $clean[] = $ns;
        }
        $clean = array_values(array_unique($clean));
        if (count($clean) < 2 || count($clean) > 4) {
            throw ValidationException::withMessages(['nameservers' => 'Informe entre 2 e 4 servidores DNS (nameservers).']);
        }

        return $clean;
    }

    public function order(User $user, string $rawDomain, string $operationType, int $years, array $nameservers, ?string $eppCode = null): DomainRegistration
    {
        if (! in_array($operationType, ['register', 'transfer'], true) || $years < 1 || $years > 10) {
            throw ValidationException::withMessages(['domain' => 'Operação ou período (1 a 10 anos) inválido.']);
        }
        $check = $this->lookup($rawDomain);
        if (! $check['valid'] || ! $check['tld']) {
            throw ValidationException::withMessages(['domain' => 'Domínio inválido ou extensão (TLD) não disponível no catálogo.']);
        }
        if ($operationType === 'register' && ! $check['available']) {
            throw ValidationException::withMessages(['domain' => 'Este domínio já está registrado ou reservado.']);
        }
        if ($operationType === 'transfer' && trim((string) $eppCode) === '') {
            throw ValidationException::withMessages(['epp_code' => 'Informe o código de autorização (EPP / Auth Code) para transferência.']);
        }
        $ns = $this->validateNameservers($nameservers);
        $tld = $check['tld'];
        $domainName = $check['domain'];

        return DB::transaction(function () use ($user, $tld, $domainName, $operationType, $years, $ns, $eppCode) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (DomainRegistration::where('domain', $domainName)->where('status', '!=', 'cancelled')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['domain' => 'Este domínio já consta registrado ou em processamento.']);
            }
            $unit = $operationType === 'transfer' ? $tld->transfer_minor : $tld->register_minor;
            $total = $unit + ($years > 1 ? ($years - 1) * $tld->renew_minor : 0);
            $expiry = now()->addMinutes((int) config('lagos.reservation_minutes', 1440));
            $invoice = Invoice::create([
                'user_id' => $user->id,
                'type' => 'domain',
                'total_minor' => $total,
                'due_date' => $expiry->toDateString(),
                'expires_at' => $expiry,
                'snapshot' => [[
                    'name' => ($operationType === 'transfer' ? 'Transferência de domínio ' : 'Registro de domínio ').$domainName.' ('.$years.' '.($years === 1 ? 'ano' : 'anos').')',
                    'quantity' => 1,
                    'unit_minor' => $total,
                ]],
            ]);
            $registration = DomainRegistration::create([
                'user_id' => $user->id,
                'domain_tld_id' => $tld->id,
                'invoice_id' => $invoice->id,
                'domain' => $domainName,
                'operation_type' => $operationType,
                'years' => $years,
                'renew_minor' => $tld->renew_minor,
                'registrar' => $tld->registrar,
                'status' => 'pending',
                'transfer_lock' => true,
                'epp_code' => $eppCode ?: ('EPP-'.strtoupper(Str::random(12))),
                'nameservers' => $ns,
            ]);
            Audit::record('domain.ordered', 'domain:'.$registration->id, ['domain' => $domainName, 'type' => $operationType, 'invoice_id' => $invoice->id], $user->id);

            return $registration;
        }, 5);
    }

    public function renew(User $user, DomainRegistration $domain, int $years = 1): Invoice
    {
        abort_unless($domain->user_id === $user->id, 404);
        if ($domain->status !== 'active' || $years < 1 || $years > 10) {
            throw ValidationException::withMessages(['domain' => 'Somente domínios ativos podem ser renovados (1 a 10 anos).']);
        }

        return DB::transaction(function () use ($user, $domain, $years) {
            $domain = DomainRegistration::lockForUpdate()->findOrFail($domain->id);
            $total = $domain->renew_minor * $years;
            $invoice = Invoice::create([
                'user_id' => $user->id,
                'type' => 'domain_renewal',
                'total_minor' => $total,
                'due_date' => today()->addDays(7)->toDateString(),
                'snapshot' => [[
                    'name' => 'Renovação de domínio '.$domain->domain.' (+'.$years.' '.($years === 1 ? 'ano' : 'anos').')',
                    'quantity' => 1,
                    'unit_minor' => $total,
                    'domain_registration_id' => $domain->id,
                    'years' => $years,
                ]],
            ]);
            Audit::record('domain.renewal_requested', 'domain:'.$domain->id, ['invoice_id' => $invoice->id, 'years' => $years], $user->id);

            return $invoice;
        }, 5);
    }

    public function completeInvoice(Invoice $invoice): void
    {
        if ($invoice->type === 'domain') {
            $reg = DomainRegistration::where('invoice_id', $invoice->id)->lockForUpdate()->first();
            if ($reg && $reg->status !== 'active') {
                $base = $reg->expires_at && $reg->expires_at->isFuture() ? CarbonImmutable::instance($reg->expires_at) : CarbonImmutable::today();
                $reg->update([
                    'status' => 'active',
                    'expires_at' => $base->addYears($reg->years),
                ]);
                Audit::record('domain.activated', 'domain:'.$reg->id, ['domain' => $reg->domain, 'invoice_id' => $invoice->id]);
            }
        } elseif ($invoice->type === 'domain_renewal') {
            $line = $invoice->snapshot[0] ?? [];
            $regId = (int) ($line['domain_registration_id'] ?? 0);
            $years = max(1, (int) ($line['years'] ?? 1));
            if ($regId && ($reg = DomainRegistration::lockForUpdate()->find($regId))) {
                $base = $reg->expires_at && $reg->expires_at->isFuture() ? CarbonImmutable::instance($reg->expires_at) : CarbonImmutable::today();
                $reg->update([
                    'status' => 'active',
                    'expires_at' => $base->addYears($years),
                ]);
                Audit::record('domain.renewed', 'domain:'.$reg->id, ['years' => $years, 'invoice_id' => $invoice->id]);
            }
        }
    }
}
