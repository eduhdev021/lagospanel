<?php

namespace App\Provisioning;

use App\Models\Operation;
use Illuminate\Support\Facades\Http;

final class VpsDriver
{
    public function run(Operation $op): string
    {
        if (! config('lagos.native_provisioning')) {
            throw new ProtocolError('Provisionamento nativo desativado na configuração local.');
        }
        $service = $op->service;
        $connector = $service->connector;
        $p = $service->provisioning ?? [];
        $driver = $connector->driver;
        $client = Http::withToken($connector->token)->acceptJson()->withoutRedirecting()->timeout(25);

        if ($driver === 'proxmox') {
            $node = $p['node'] ?? 'pve';
            $vmid = (string) ($service->remote_id ?: (1000 + $service->id));
            $base = rtrim($connector->endpoint, '/').'/api2/json/nodes/'.rawurlencode($node).'/qemu/'.rawurlencode($vmid);
            $r = match ($op->action) {
                'create' => Http::withToken($connector->token)->acceptJson()->withoutRedirecting()->timeout(25)
                    ->post(rtrim($connector->endpoint, '/').'/api2/json/nodes/'.rawurlencode($node).'/qemu', [
                        'vmid' => (int) $vmid,
                        'name' => $p['hostname'] ?? ('vps-'.$service->id),
                        'cores' => (int) ($p['cores'] ?? 1),
                        'memory' => (int) ($p['memory_mb'] ?? 1024),
                    ]),
                'suspend' => $client->post($base.'/status/suspend'),
                'unsuspend' => $client->post($base.'/status/resume'),
                'terminate' => $client->delete($base),
                default => throw new ProtocolError('Ação Proxmox inválida.'),
            };
            if (! $r->successful()) {
                throw new ProtocolError('Proxmox retornou HTTP '.$r->status().'.');
            }

            return $op->action === 'terminate' ? '' : $vmid;
        }

        if ($driver === 'virtualizor') {
            $vid = (string) ($service->remote_id ?: $service->id);
            $act = ['create' => 'addvs', 'suspend' => 'suspend', 'unsuspend' => 'unsuspend', 'terminate' => 'deletevs'][$op->action] ?? null;
            if (! $act) {
                throw new ProtocolError('Ação Virtualizor inválida.');
            }
            $r = $client->post(rtrim($connector->endpoint, '/').'/index.php?api=json&act='.$act, [
                'vpsid' => $vid,
                'hostname' => $p['hostname'] ?? ('vps-'.$service->id),
                'cores' => (int) ($p['cores'] ?? 1),
                'ram' => (int) ($p['memory_mb'] ?? 1024),
                'disk' => (int) ($p['disk_gb'] ?? 20),
                'os_name' => $p['template'] ?? 'ubuntu-24.04',
            ]);
            if (! $r->successful()) {
                throw new ProtocolError('Virtualizor retornou HTTP '.$r->status().'.');
            }

            return $op->action === 'terminate' ? '' : (string) ($r->json('vs_info.vpsid') ?? $vid);
        }

        throw new ProtocolError('Driver VPS desconhecido.');
    }
}
