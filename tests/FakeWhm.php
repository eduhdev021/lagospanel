<?php

namespace Tests;

use Illuminate\Support\Facades\Http;

/** In-process test transport. It cannot forward requests to an actual WHM. */
final class FakeWhm
{
    public static function install(string $path, string $token): void
    {
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($path, $token) {
            if (! str_starts_with($request->url(), 'https://whm-fixture.invalid:2087/json-api/') || $request->method() !== 'POST' || ! $request->hasHeader('Authorization', 'whm root:'.$token)) {
                throw new \RuntimeException('Unexpected test request; network access blocked.');
            }
            $method = basename(parse_url($request->url(), PHP_URL_PATH));
            $p = $request->data();
            if (($p['api.version'] ?? null) !== 1) {
                throw new \RuntimeException('Missing WHM API version.');
            }
            $h = fopen($path, 'c+');
            chmod($path, 0600);
            flock($h, LOCK_EX);
            $raw = stream_get_contents($h);
            $state = $raw ? json_decode($raw, true) : ['accounts' => [], 'calls' => []];
            $result = 1;
            $data = [];
            $state['calls'][] = $method;
            switch ($method) {
                case 'listaccts':$row = $state['accounts'][$p['search']] ?? null;
                    $data = ['acct' => $row ? [$row] : []];
                    break;
                case 'createacct':
                    if (isset($state['accounts'][$p['username']])) {
                        $result = 0;
                        break;
                    }
                    $state['accounts'][$p['username']] = ['user' => $p['username'], 'domain' => $p['domain'], 'owner' => $p['owner'], 'plan' => $p['plan'], 'email' => $p['contactemail'], 'suspended' => 0];
                    $state['password_hashes'][$p['username']] = hash('sha256', $p['password']);
                    break;
                case 'suspendacct':case 'unsuspendacct':
                    if (! isset($state['accounts'][$p['user']])) {
                        $result = 0;
                        break;
                    }
                    $state['accounts'][$p['user']]['suspended'] = $method === 'suspendacct' ? 1 : 0;
                    break;
                case 'removeacct':unset($state['accounts'][$p['username']]);
                    break;
                default:$result = 0;
            }
            rewind($h);
            ftruncate($h, 0);
            fwrite($h, json_encode($state));
            fflush($h);
            flock($h, LOCK_UN);
            fclose($h);

            return Http::response(['metadata' => ['result' => $result, 'version' => 1, 'command' => $method], 'data' => $data]);
        });
    }
}
