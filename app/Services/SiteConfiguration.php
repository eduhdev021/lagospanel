<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;

final class SiteConfiguration
{
    private array $baseline;

    public function __construct()
    {
        $this->baseline = [];
        foreach (['mail', 'app.name', 'app.url', 'site.registration_enabled', 'site.support_email'] as $key) {
            $this->baseline[$key] = config($key);
        }
    }

    public static function origin(string $url): string
    {
        $p = parse_url($url);
        $scheme = $p['scheme'] ?? '';
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array($p['path'] ?? '', ['', '/'], true) || isset($p['query']) || isset($p['fragment']) || isset($p['user']) || isset($p['pass']) || ($scheme !== 'https' && ! (app()->environment(['local', 'testing']) && $scheme === 'http' && in_array($p['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true)))) {
            throw ValidationException::withMessages(['url' => 'Use a origem HTTPS do site, sem caminho, query ou credenciais.']);
        }

        return rtrim($url, '/');
    }

    public function apply(): void
    {
        $oldMail = config('mail');
        config($this->baseline);
        URL::forceRootUrl(null);
        View::share('siteLogo', null);
        try {
            if (is_file(config('setup.state_path')) && ! is_file(config('setup.lock_path'))) {
                return;
            }
            if (! Schema::hasTable('site_settings') || ! ($s = SiteSetting::find(1))) {
                return;
            }
            config(['app.name' => $s->name, 'app.url' => $s->url, 'site.registration_enabled' => $s->registration_enabled, 'site.support_email' => $s->support_email]);
            URL::forceRootUrl($s->url);
            View::share('siteLogo', $s->logo_mime ? route('brand.logo', ['v' => $s->version]) : null);
            if ($s->mailer !== 'inherit') {
                config(['mail.default' => $s->mailer]);
                if ($s->mail_from_address) {
                    config(['mail.from.address' => $s->mail_from_address, 'mail.from.name' => $s->name]);
                }
                if ($s->mailer === 'smtp') {
                    config(['mail.mailers.smtp' => ['transport' => 'smtp', 'scheme' => $s->smtp_scheme, 'host' => $s->smtp_host, 'port' => $s->smtp_port, 'username' => $s->smtp_username, 'password' => $s->smtp_password, 'timeout' => 15, 'require_tls' => true, 'auto_tls' => true]]);
                }

            }
        } finally {
            if ($oldMail !== config('mail')) {
                app('mail.manager')->forgetMailers();
            }
        }
    }
}
