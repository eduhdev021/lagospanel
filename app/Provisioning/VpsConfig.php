<?php

namespace App\Provisioning;

use App\Models\Connector;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Validation\ValidationException;

final class VpsConfig
{
    public static function product(string $driver, array $input): array
    {
        $raw = trim((string) ($input['vps_config'] ?? ''));
        $json = json_decode($raw, true);
        if (! is_array($json)) {
            throw ValidationException::withMessages(['vps_config' => 'Informe um JSON válido para o plano VPS (Proxmox / Virtualizor).']);
        }
        $node = trim((string) ($json['node'] ?? ''));
        $template = trim((string) ($json['template'] ?? ''));
        $cores = (int) ($json['cores'] ?? 0);
        $memory = (int) ($json['memory_mb'] ?? 0);
        $disk = (int) ($json['disk_gb'] ?? 0);
        if (! preg_match('/^[a-zA-Z0-9_.-]{1,64}$/D', $node)) {
            throw ValidationException::withMessages(['vps_config' => 'Informe um nó (node) válido para o hipervisor.']);
        }
        if ($template === '' || strlen($template) > 160 || preg_match('/[\r\n]/', $template)) {
            throw ValidationException::withMessages(['vps_config' => 'Informe o template/ISO da imagem VPS.']);
        }
        if ($cores < 1 || $cores > 128 || $memory < 256 || $memory > 524288 || $disk < 5 || $disk > 10000) {
            throw ValidationException::withMessages(['vps_config' => 'Limites de CPU (1-128), RAM (256-524288 MiB) ou Disco (5-10000 GB) inválidos.']);
        }

        return [
            'driver' => $driver,
            'node' => $node,
            'template' => $template,
            'cores' => $cores,
            'memory_mb' => $memory,
            'disk_gb' => $disk,
        ];
    }

    public static function snapshot(Service $service, Product $product, Connector $connector): void
    {
        $cfg = self::product($connector->driver, ['vps_config' => json_encode($product->provisioning ?? [])]);
        $hostname = 'vps-'.$service->id.'.lagos.local';
        $service->update([
            'native_username' => 'vps'.$service->id,
            'provisioning' => $cfg + [
                'endpoint' => $connector->endpoint,
                'hostname' => $hostname,
                'email' => $service->user->email,
            ],
        ]);
    }
}
