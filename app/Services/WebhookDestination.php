<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class WebhookDestination
{
    public static function validate(string $url): string
    {
        $p = parse_url($url);
        $host = strtolower($p['host'] ?? '');
        if (! WebResearch::publicUrl($url) || ($p['scheme'] ?? '') !== 'https' || ($p['port'] ?? 443) !== 443 || isset($p['query']) || isset($p['fragment']) || filter_var(trim($host, '[]'), FILTER_VALIDATE_IP)) {
            throw ValidationException::withMessages(['url' => 'Use URL HTTPS pública, porta 443, nome DNS, sem credenciais, query ou fragmento.']);
        }

        return $host;
    }

    public function resolve(string $host): array
    {
        return array_values(array_filter(array_column(@dns_get_record($host, DNS_A) ?: [], 'ip')));
    }

    public function pin(string $url): array
    {
        $host = self::validate($url);
        $ips = $this->resolve($host);
        if (! $ips) {
            throw new \RuntimeException('DNS unavailable');
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_GLOBAL_RANGE)) {
                throw new \RuntimeException('Nonpublic DNS');
            }
        }

        return [$host, $ips[0]];
    }
}
