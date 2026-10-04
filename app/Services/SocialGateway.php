<?php

namespace App\Services;

use App\Models\SocialProvider;
use App\Services\OAuth\MicrosoftProvider;
use GuzzleHttp\Client;
use Laravel\Socialite\Two\FacebookProvider;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\XProvider;
use SocialiteProviders\Manager\Config;

class SocialGateway
{
    public function driver(SocialProvider $setting)
    {
        $classes = ['github' => GithubProvider::class, 'google' => GoogleProvider::class, 'facebook' => FacebookProvider::class, 'twitter' => XProvider::class, 'microsoft' => MicrosoftProvider::class];
        $callback = SiteConfiguration::origin(config('app.url')).route('social.callback', ['provider' => $setting->provider], false);
        $p = new $classes[$setting->provider](request(), $setting->client_id, $setting->client_secret, $callback);
        $p->setHttpClient(new Client(['timeout' => 15, 'connect_timeout' => 5, 'allow_redirects' => false, 'verify' => true, 'http_errors' => true]));
        if (in_array($setting->provider, ['google', 'microsoft', 'twitter'], true)) {
            $p->enablePKCE();
        }
        if ($setting->provider === 'github') {
            $p->setScopes(['read:user', 'user:email']);
        }
        if ($setting->provider === 'facebook') {
            $p->fields(['id', 'name', 'email'])->setScopes(['email']);
        }
        if ($setting->provider === 'microsoft') {
            $p->setConfig(new Config($setting->client_id, $setting->client_secret, $callback, ['tenant' => 'common', 'include_avatar' => false]));
        }

        return $p;
    }
}
