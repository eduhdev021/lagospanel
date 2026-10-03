<?php

namespace Tests;

use App\Provisioning\PleskXml;
use Illuminate\Support\Facades\Http;

final class FakePleskCustomers
{
    public static function install(string $file): void
    {
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($file) {
            if ($request->url() !== 'https://plesk-customers.invalid:8443/enterprise/control/agent.php') {
                throw new \RuntimeException('Unexpected endpoint');
            }
            $doc = new \DOMDocument;
            $doc->loadXML($request->body());
            $xp = new \DOMXPath($doc);
            $node = $doc->documentElement->firstElementChild;
            if ($node->nodeName !== 'customer') {
                throw new \RuntimeException('Subscription HTTP before preparation');
            }$verb = $node->firstElementChild->nodeName;
            $h = fopen($file, 'c+');
            flock($h, LOCK_EX);
            $state = json_decode(stream_get_contents($h), true) ?: ['posts' => 0, 'customer' => null];
            if ($verb === 'add') {
                if ($state['customer']) {
                    throw new \RuntimeException('Duplicate customer POST');
                }
                $state['posts']++;
                $state['customer'] = [];
                foreach (['login', 'email', 'external-id'] as $key) {
                    $state['customer'][$key] = $xp->evaluate('string(/packet/customer/add/gen_info/'.$key.')');
                }
                $result = '<status>ok</status><id>29</id>';
            } elseif ($verb === 'get') {
                $login = $xp->evaluate('string(/packet/customer/get/filter/login)');
                $id = $xp->evaluate('string(/packet/customer/get/filter/id)');
                if (! $state['customer']) {
                    $result = '<status>error</status><errcode>1013</errcode>';
                } else {
                    if (($login !== '' && $login !== $state['customer']['login']) || ($id !== '' && $id !== '29') || ($login === '' && $id === '')) {
                        throw new \RuntimeException('Unscoped lookup');
                    }
                    $result = '<status>ok</status><id>29</id><data><gen_info><status>0</status>';
                    foreach ($state['customer'] as $key => $value) {
                        $result .= '<'.$key.'>'.PleskXml::esc($value).'</'.$key.'>';
                    }
                    $result .= '</gen_info></data>';
                }
            } else {
                throw new \RuntimeException('Unexpected operation');
            }
            ftruncate($h, 0);
            rewind($h);
            fwrite($h, json_encode($state));
            fflush($h);
            flock($h, LOCK_UN);
            fclose($h);

            return Http::response('<packet><customer><'.$verb.'><result>'.$result.'</result></'.$verb.'></customer></packet>', 200, ['Content-Type' => 'text/xml']);
        });
    }
}
