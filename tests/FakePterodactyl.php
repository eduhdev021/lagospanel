<?php

namespace Tests;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class FakePterodactyl
{
    public static function install(string $file, int $user, string $email, bool $installing = false): void
    {
        Http::preventStrayRequests();
        Http::fake(function ($r) use ($file, $user, $email, $installing) {
            if (! str_starts_with($r->url(), 'https://ptero-fixture.invalid/api/application/')) {
                throw new RuntimeException('Unexpected fixture host');
            }
            $h = fopen($file, 'c+');
            flock($h, LOCK_EX);
            $s = json_decode(stream_get_contents($h), true) ?: ['calls' => [], 'server' => null];
            $path = parse_url($r->url(), PHP_URL_PATH);
            $s['calls'][] = $r->method().' '.$path;
            $code = 200;
            $body = [];
            if ($r->method() === 'GET' && $path === '/api/application/users/'.$user) {
                $body = ['object' => 'user', 'attributes' => ['id' => $user, 'email' => $email, 'root_admin' => false]];
            } elseif ($r->method() === 'GET' && str_contains($path, '/servers/external/')) {
                if ($s['server']) {
                    $body = ['object' => 'server', 'attributes' => $s['server']];
                } else {
                    $code = 404;
                    $body = ['errors' => [['code' => 'NotFoundHttpException']]];
                }
            } elseif ($r->method() === 'POST' && $path === '/api/application/servers') {
                if ($s['server']) {
                    $code = 409;
                    $body = ['errors' => [['code' => 'Duplicate']]];
                } else {
                    $s['server'] = ['id' => 71, 'external_id' => $r['external_id'], 'user' => $r['user'], 'egg' => $r['egg'], 'limits' => $r['limits'], 'feature_limits' => $r['feature_limits'], 'container' => ['image' => $r['docker_image']], 'status' => $installing ? 'installing' : null, 'suspended' => false];
                    $body = ['object' => 'server', 'attributes' => $s['server']];
                    $code = 201;
                }
            } elseif ($r->method() === 'POST' && $path === '/api/application/servers/71/suspend') {
                $s['server']['status'] = 'suspended';
                $s['server']['suspended'] = true;
                $code = 204;
            } elseif ($r->method() === 'POST' && $path === '/api/application/servers/71/unsuspend') {
                $s['server']['status'] = null;
                $s['server']['suspended'] = false;
                $code = 204;
            } elseif ($r->method() === 'DELETE' && $path === '/api/application/servers/71') {
                $s['server'] = null;
                $code = 204;
            } else {
                throw new RuntimeException('Unexpected fixture request');
            }
            ftruncate($h, 0);
            rewind($h);
            fwrite($h, json_encode($s));
            fflush($h);
            flock($h, LOCK_UN);
            fclose($h);

            return Http::response($code === 204 ? '' : $body, $code);
        });
    }
}
