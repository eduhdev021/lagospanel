<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Support\Cycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PanelImporter
{
    public function import(string $source, array $payload, int $actorId): array
    {
        if (! in_array($source, ['whmcs', 'paymenter'], true)) {
            throw ValidationException::withMessages(['source' => 'Origem de importação inválida (use whmcs ou paymenter).']);
        }
        $clients = $payload['clients'] ?? [];
        $products = $payload['products'] ?? [];
        $services = $payload['services'] ?? [];
        $invoices = $payload['invoices'] ?? [];

        if (! is_array($clients) || ! is_array($products) || ! is_array($services) || ! is_array($invoices)) {
            throw ValidationException::withMessages(['payload' => 'Estrutura JSON inválida para importação.']);
        }
        if (count($clients) > 500 || count($products) > 200 || count($services) > 1000 || count($invoices) > 1000) {
            throw ValidationException::withMessages(['payload' => 'Lote excede o limite seguro por execução.']);
        }

        return DB::transaction(function () use ($source, $clients, $products, $services, $invoices, $actorId) {
            $counts = ['clients' => 0, 'products' => 0, 'services' => 0, 'invoices' => 0];
            $clientMap = [];
            $productMap = [];

            foreach ($clients as $c) {
                $extId = trim((string) ($c['id'] ?? ''));
                $email = strtolower(trim((string) ($c['email'] ?? '')));
                $name = trim((string) ($c['name'] ?? 'Cliente Importado'));
                if ($extId === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw ValidationException::withMessages(['clients' => 'Cliente importado sem ID externo ou e-mail válido.']);
                }
                $u = User::where('external_source', $source)->where('external_id', $extId)->first()
                    ?? User::where('email', $email)->first();

                if (! $u) {
                    $u = new User([
                        'name' => mb_substr($name, 0, 100),
                        'email' => $email,
                        'password' => Str::random(32).'Aa1!',
                        'tax_id' => isset($c['tax_id']) ? mb_substr((string) $c['tax_id'], 0, 32) : null,
                        'company_name' => isset($c['company_name']) ? mb_substr((string) $c['company_name'], 0, 180) : null,
                        'phone' => isset($c['phone']) ? mb_substr((string) $c['phone'], 0, 40) : null,
                        'external_source' => $source,
                        'external_id' => $extId,
                    ]);
                    $u->forceFill([
                        'email_verified_at' => now(),
                        'password_reset_required' => true,
                    ])->save();
                    $counts['clients']++;
                }
                $clientMap[$extId] = $u->id;
            }

            foreach ($products as $p) {
                $extId = trim((string) ($p['id'] ?? ''));
                $name = trim((string) ($p['name'] ?? ''));
                $priceMinor = max(0, (int) ($p['price_minor'] ?? 0));
                $cycle = in_array($p['cycle'] ?? '', array_keys(Cycle::LABELS), true) ? $p['cycle'] : 'monthly';
                if ($extId === '' || $name === '') {
                    throw ValidationException::withMessages(['products' => 'Produto importado sem ID ou nome.']);
                }
                $slug = Str::slug($source.'-'.$extId.'-'.$name);
                $prod = Product::firstOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => mb_substr($name, 0, 180),
                        'category' => isset($p['category']) ? mb_substr((string) $p['category'], 0, 100) : ucfirst($source),
                        'description' => (string) ($p['description'] ?? 'Importado de '.strtoupper($source)),
                        'price_minor' => $priceMinor,
                        'setup_minor' => 0,
                        'cycle' => $cycle,
                        'active' => (bool) ($p['active'] ?? true),
                    ]
                );
                if ($prod->wasRecentlyCreated) {
                    $counts['products']++;
                }
                $productMap[$extId] = $prod;
            }

            foreach ($services as $s) {
                $clientId = $clientMap[(string) ($s['client_id'] ?? '')] ?? null;
                $prod = $productMap[(string) ($s['product_id'] ?? '')] ?? null;
                if (! $clientId || ! $prod) {
                    continue;
                }
                $status = in_array($s['status'] ?? '', ['pending', 'active', 'suspended', 'cancelled'], true) ? $s['status'] : 'active';
                Service::create([
                    'user_id' => $clientId,
                    'product_id' => $prod->id,
                    'name' => $prod->name,
                    'cycle' => $prod->cycle,
                    'price_minor' => max(0, (int) ($s['price_minor'] ?? $prod->price_minor)),
                    'status' => $status,
                    'next_due' => $s['next_due'] ?? today()->addMonth()->toDateString(),
                ]);
                $counts['services']++;
            }

            foreach ($invoices as $inv) {
                $clientId = $clientMap[(string) ($inv['client_id'] ?? '')] ?? null;
                if (! $clientId) {
                    continue;
                }
                $total = max(0, (int) ($inv['total_minor'] ?? 0));
                $status = in_array($inv['status'] ?? '', ['unpaid', 'paid', 'overdue', 'cancelled'], true) ? $inv['status'] : 'unpaid';
                Invoice::create([
                    'user_id' => $clientId,
                    'type' => 'order',
                    'status' => $status,
                    'total_minor' => $total,
                    'due_date' => $inv['due_date'] ?? today()->addDays(5)->toDateString(),
                    'paid_at' => $status === 'paid' ? now() : null,
                    'snapshot' => [[
                        'name' => (string) ($inv['description'] ?? ('Fatura importada '.strtoupper($source))),
                        'quantity' => 1,
                        'unit_minor' => $total,
                    ]],
                ]);
                $counts['invoices']++;
            }

            Audit::record('panel.imported', 'import:'.$source, $counts, $actorId);

            return $counts;
        }, 5);
    }
}
