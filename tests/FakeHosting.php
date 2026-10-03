<?php

namespace Tests;

use App\Models\Service;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class FakeHosting
{
    public static function install(string $file, Service $service, array $options = [], ?callable $hook = null): void
    {
        $p = $service->provisioning;
        $driver = $p['driver'];
        Http::preventStrayRequests();
        Http::fake(function ($r) use ($file, $service, $p, $driver, $options, $hook) {
            if (! str_starts_with($r->url(), $p['endpoint'].'/')) {
                throw new RuntimeException('Unexpected fixture destination');
            }
            $h = fopen($file, 'c+');
            flock($h, LOCK_EX);
            $state = json_decode(stream_get_contents($h), true) ?: ['exists' => false, 'suspended' => false, 'calls' => []];
            $action = 'get';
            $request = null;
            if ($driver === 'directadmin') {
                if ($r->header('Authorization') !== ['Basic '.base64_encode($p['creator'].':'.$service->connector->token)]) {
                    throw new RuntimeException('Bad DA auth');
                }
                $path = parse_url($r->url(), PHP_URL_PATH);
                if ($r->method() === 'GET') {
                    if ($path !== '/api/users/'.$p['username'].'/config') {
                        throw new RuntimeException('Bad DA path');
                    }
                } else {
                    if ($r['json'] !== 'yes') {
                        throw new RuntimeException('Expected JSON mode');
                    }
                    if ($path === '/CMD_API_ACCOUNT_USER') {
                        $action = 'create';
                        foreach (['username' => 'username', 'domain' => 'domain', 'package' => 'plan', 'email' => 'email', 'ip' => 'ip'] as $remote => $local) {
                            if ($r[$remote] !== $p[$local]) {
                                throw new RuntimeException('Bad creation field');
                            }
                        }
                        if ($r['action'] !== 'create' || $r['passwd'] !== $r['passwd2'] || strlen($r['passwd']) < 20 || $r['notify'] !== 'no') {
                            throw new RuntimeException('Bad secret/action');
                        }
                    } elseif ($path === '/CMD_API_SELECT_USERS') {
                        if ($r['select0'] !== $p['username'] || isset($r['select1']) || isset($r['suspend'])) {
                            throw new RuntimeException('Unsafe selection');
                        }
                        $action = isset($r['dosuspend']) ? 'suspend' : (isset($r['dounsuspend']) ? 'unsuspend' : 'terminate');
                        if ($action === 'terminate' && ($r['confirmed'] !== 'Confirm' || $r['delete'] !== 'yes')) {
                            throw new RuntimeException('Unsafe delete');
                        }
                    } else {
                        throw new RuntimeException('Unexpected DA path');
                    }
                }
            } else {
                if ($r->method() !== 'POST' || $r->header('KEY') !== [$service->connector->token] || ! str_ends_with($r->url(), '/enterprise/control/agent.php')) {
                    throw new RuntimeException('Bad Plesk transport');
                }
                $request = new \DOMDocument;
                $request->loadXML($r->body(), LIBXML_NONET);
                $xp = new \DOMXPath($request);
                $verb = $xp->query('/packet/webspace/*')->item(0)->nodeName;
                if ($verb === 'add') {
                    $action = 'create';
                    foreach (['gen_setup/name' => 'domain', 'gen_setup/owner-id' => 'owner_id', 'gen_setup/external-id' => 'external_id', 'plan-guid' => 'plan_guid'] as $path => $key) {
                        if ($xp->evaluate('string(/packet/webspace/add/'.$path.')') !== (string) $p[$key]) {
                            throw new RuntimeException('Bad Plesk create field');
                        }
                    }
                    if (strlen($xp->evaluate('string(/packet/webspace/add/hosting/vrt_hst/property[name="ftp_password"]/value)')) < 20) {
                        throw new RuntimeException('Missing FTP password');
                    }
                } elseif ($verb === 'set' || $verb === 'del') {
                    if ($xp->evaluate('string(/packet/webspace/'.$verb.'/filter/id)') !== '71') {
                        throw new RuntimeException('Wrong Plesk ID');
                    }
                    $action = $verb === 'del' ? 'terminate' : ($xp->evaluate('string(/packet/webspace/set/values/gen_setup/status)') === '16' ? 'suspend' : 'unsuspend');
                } elseif ($verb !== 'get' || $xp->evaluate('string(/packet/webspace/get/filter/name)') !== $p['domain']) {
                    throw new RuntimeException('Unscoped Plesk lookup');
                }
            }
            $state['calls'][] = $action;
            if ($action !== 'get') {
                if ($action === 'create' && $state['exists']) {
                    throw new RuntimeException('Duplicate creation');
                }
                $state['exists'] = $action !== 'terminate';
                $state['suspended'] = $action === 'suspend';
            }
            ftruncate($h, 0);
            rewind($h);
            fwrite($h, json_encode($state));
            fflush($h);
            flock($h, LOCK_UN);
            fclose($h);
            if ($hook) {
                $hook($action, $r);
            }
            if ($action !== 'get' && ($options['timeout'] ?? false)) {
                return Http::failedConnection();
            }
            if (isset($options['raw'])) {
                return Http::response($options['raw'], $options['status'] ?? 200);
            }
            if ($driver === 'directadmin') {
                if ($action !== 'get') {
                    return Http::response(['error' => 0]);
                }
                if (! $state['exists']) {
                    return Http::response(['type' => 'NOT_FOUND'], 404);
                }

                return Http::response(array_replace(['username' => $p['username'], 'creator' => $p['creator'], 'domain' => $p['domain'], 'email' => $p['email'], 'package' => $p['plan'], 'ip' => $p['ip'], 'userType' => 'user', 'suspended' => $state['suspended']], $options['fields'] ?? []));
            }
            if ($action !== 'get') {
                $result = '<status>ok</status><id>71</id>';
            } elseif (! $state['exists']) {
                $result = '<status>error</status><errcode>1013</errcode><errtext>Subscription missing</errtext>';
            } else {
                $gen = array_replace(['name' => $p['domain'], 'owner-id' => (string) $p['owner_id'], 'external-id' => $p['external_id'], 'dns_ip_address' => $p['ip'], 'htype' => 'vrt_hst', 'status' => $state['suspended'] ? '16' : '0'], $options['fields'] ?? []);
                $result = '<status>ok</status><id>71</id><data><gen_info>';
                foreach ($gen as $key => $value) {
                    $result .= '<'.$key.'>'.htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</'.$key.'>';
                }
                $result .= '</gen_info><hosting><vrt_hst><property><name>ftp_login</name><value>'.$p['username'].'</value></property></vrt_hst></hosting><subscriptions><subscription><locked>false</locked><synchronized>true</synchronized><plan><plan-guid>'.$p['plan_guid'].'</plan-guid></plan></subscription></subscriptions></data>';
            }

            return Http::response('<packet><webspace><'.$verb.'><result>'.$result.'</result></'.$verb.'></webspace></packet>', 200, ['Content-Type' => 'text/xml']);
        });
    }
}
