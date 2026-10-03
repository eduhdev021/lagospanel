<?php

namespace Tests;

use Illuminate\Support\Facades\Http;

final class FakeAaPanel
{
    public static function install(string $path, string $key): void
    {
        Http::preventStrayRequests();
        Http::fake(function ($r) use ($path, $key) {
            if (! str_starts_with($r->url(), 'https://aapanel-fixture.invalid:7800/') || $r->method() !== 'POST' || $r['request_token'] !== md5((string) $r['request_time'].md5($key))) {
                throw new \RuntimeException('Unexpected aaPanel test request.');
            }
            parse_str(parse_url($r->url(), PHP_URL_QUERY), $q);
            $action = $q['action'];
            $h = fopen($path, 'c+');
            chmod($path, 0600);
            flock($h, LOCK_EX);
            $raw = stream_get_contents($h);
            $d = $raw ? json_decode($raw, true) : ['sites' => [], 'calls' => []];
            $d['calls'][] = $action;
            $response = ['status' => true];
            switch ($action) {
                case 'getData':$response = ['data' => array_values(array_filter($d['sites'], fn ($s) => str_contains($s['name'], $r['search'])))];
                    break;
                case 'GetPHPVersion':$response = [['version' => '00'], ['version' => '82']];
                    break;
                case 'AddSite':$domain = json_decode($r['webname'], true)['domain'];
                    if (isset($d['sites'][$domain])) {
                        $response = ['siteStatus' => false];
                        break;
                    }$id = ($d['sequence'] ?? 40) + 1;
                    $d['sequence'] = $id;
                    $d['sites'][$domain] = ['id' => $id, 'name' => $domain, 'path' => $r['path'], 'ps' => $r['ps'], 'status' => '1'];
                    $response = ['siteStatus' => true, 'ftpStatus' => false, 'databaseStatus' => false];
                    break;
                case 'SiteStop':case 'SiteStart':$name = $r['name'];
                    if (! isset($d['sites'][$name]) || (string) $d['sites'][$name]['id'] !== (string) $r['id']) {
                        $response = ['status' => false];
                        break;
                    }$d['sites'][$name]['status'] = $action === 'SiteStop' ? '0' : '1';
                    break;
                case 'DeleteSite':$name = $r['webname'];
                    if (! isset($d['sites'][$name]) || (string) $d['sites'][$name]['id'] !== (string) $r['id']) {
                        $response = ['status' => false];
                        break;
                    }unset($d['sites'][$name]);
                    break;
                default:$response = ['status' => false];
            }
            rewind($h);
            ftruncate($h, 0);
            fwrite($h, json_encode($d));
            fflush($h);
            flock($h, LOCK_UN);
            fclose($h);

            return Http::response($response);
        });
    }
}
