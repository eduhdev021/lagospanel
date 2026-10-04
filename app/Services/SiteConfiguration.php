<?php

namespace App\Services;

use App\Models\AdminPreference;
use App\Models\SiteSetting;
use App\Support\OperationalSettings;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;

final class SiteConfiguration
{
    public const SOCIAL_PLATFORMS = [
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'x' => 'X / Twitter',
        'youtube' => 'YouTube',
        'linkedin' => 'LinkedIn',
        'tiktok' => 'TikTok',
        'whatsapp' => 'WhatsApp',
        'discord' => 'Discord',
    ];

    private array $baseline;

    private array $operationalBaseline = [];

    private array $operationalApplied = [];

    public function __construct()
    {
        $this->baseline = [];
        foreach (['mail', 'app.name', 'app.url', 'site.registration_enabled', 'site.support_email', 'admin_access.require_two_factor'] as $key) {
            $this->baseline[$key] = config($key);
        }
        foreach (OperationalSettings::keys() as $key) {
            $this->operationalBaseline[$key] = config($key);
        }
    }

    public static function isSafeSocialLink(mixed $url): bool
    {
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && ! empty($parts['host'])
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }

    public static function safeSocialLinks(mixed $links): array
    {
        if (! is_array($links)) {
            return [];
        }

        $safe = [];
        foreach (self::SOCIAL_PLATFORMS as $key => $label) {
            $url = $links[$key] ?? null;
            if (self::isSafeSocialLink($url)) {
                $safe[$key] = $url;
            }
        }

        return $safe;
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
        foreach ($this->operationalApplied as $key) {
            config([$key => $this->operationalBaseline[$key]]);
        }
        $this->operationalApplied = [];
        URL::forceRootUrl(null);
        View::share('siteLogo', null);
        View::share('siteFooterDescription', 'Um lugar para seus projetos. Um painel para acompanhar cada passo.');
        View::share('siteFooterCopyright', 'Todos os direitos reservados.');
        View::share('siteFooterTagline', 'Feito para conectar suas ideias.');
        View::share('siteSocialLinks', []);
        try {
            if (is_file(config('setup.state_path')) && ! is_file(config('setup.lock_path'))) {
                return;
            }
            $this->operationalApplied = OperationalSettings::apply();
            if (Schema::hasTable('admin_preferences') && ($preference = AdminPreference::find(1)) && $preference->require_two_factor !== null) {
                config(['admin_access.require_two_factor' => $preference->require_two_factor]);
            }
            if (! Schema::hasTable('site_settings') || ! ($s = SiteSetting::find(1))) {
                return;
            }
            config(['app.name' => $s->name, 'app.url' => $s->url, 'site.registration_enabled' => $s->registration_enabled, 'site.support_email' => $s->support_email]);
            URL::forceRootUrl($s->url);
            View::share('siteLogo', $s->logo_mime ? route('brand.logo', ['v' => $s->version]) : null);
            View::share('siteFooterDescription', $s->footer_description ?? 'Um lugar para seus projetos. Um painel para acompanhar cada passo.');
            View::share('siteFooterCopyright', $s->footer_copyright ?? 'Todos os direitos reservados.');
            View::share('siteFooterTagline', $s->footer_tagline ?? 'Feito para conectar suas ideias.');
            View::share('siteSocialLinks', self::safeSocialLinks($s->social_links));
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
