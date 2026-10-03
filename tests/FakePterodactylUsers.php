<?php

namespace Tests;

use Illuminate\Support\Facades\Http;

final class FakePterodactylUsers
{
    public static function install(string $file, bool $timeoutAfterPost = false): void
    {
        Http::preventStrayRequests();
        Http::fake(function ($r) use ($file, $timeoutAfterPost) {
            if (! str_starts_with($r->url(), 'https://ptero-users.invalid/api/application/users')) {
                throw new \RuntimeException('Unexpected fixture host');
            }
            $h = fopen($file, 'c+');
            flock($h, LOCK_EX);
            try {
                $state = json_decode(stream_get_contents($h), true) ?: ['users' => [], 'posts' => 0, 'calls' => []];
                $path = parse_url($r->url(), PHP_URL_PATH);
                $state['calls'][] = $r->method().' '.$path;
                $code = 200;
                $body = null;
                if ($r->method() === 'POST' && $path === '/api/application/users') {
                    $state['posts']++;
                    if ($r['root_admin'] !== false || array_key_exists('password', $r->data())) {
                        throw new \RuntimeException('Unsafe creation payload');
                    }
                    foreach (['external_id', 'username', 'email', 'first_name', 'last_name'] as $key) {
                        if (! is_string($r[$key] ?? null) || $r[$key] === '') {
                            throw new \RuntimeException('Incomplete creation payload');
                        }
                    }
                    $duplicate = isset($state['users'][$r['external_id']]);
                    foreach ($state['users'] as $u) {
                        if ($u['email'] === $r['email'] || $u['username'] === $r['username']) {
                            $duplicate = true;
                        }
                    }
                    if ($duplicate) {
                        $code = 422;
                        $body = ['errors' => [['code' => 'ValidationException']]];
                    } else {
                        $user = ['id' => 41 + count($state['users'])] + $r->data();
                        $state['users'][$r['external_id']] = $user;
                        $body = ['object' => 'user', 'attributes' => $user];
                        $code = 201;
                    }
                } elseif ($r->method() === 'GET') {
                    foreach ($state['users'] as $external => $user) {
                        if ($path === '/api/application/users/external/'.$external || $path === '/api/application/users/'.$user['id']) {
                            $body = ['object' => 'user', 'attributes' => $user];
                            break;
                        }
                    }
                    if (! $body) {
                        $code = 404;
                        $body = ['errors' => [['code' => 'NotFoundHttpException']]];
                    }
                } else {
                    throw new \RuntimeException('Unexpected fixture operation');
                }
                rewind($h);
                ftruncate($h, 0);
                fwrite($h, json_encode($state));
                fflush($h);
            } finally {
                flock($h, LOCK_UN);
                fclose($h);
            }

            return $timeoutAfterPost && $r->method() === 'POST' ? Http::failedConnection() : Http::response($body, $code);
        });
    }
}
