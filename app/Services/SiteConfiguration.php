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

    public const DEFAULT_FOOTER_LINKS = [
        ['group' => 'explore', 'label' => 'Planos e serviços', 'url' => '/loja'],
        ['group' => 'explore', 'label' => 'Área do cliente', 'url' => '/entrar'],
        ['group' => 'explore', 'label' => 'Central de ajuda', 'url' => '/conhecimento'],
        ['group' => 'information', 'label' => 'Condições da instalação', 'url' => '/termos'],
        ['group' => 'information', 'label' => 'Privacidade e exclusão', 'url' => '/privacidade'],
        ['group' => 'information', 'label' => 'Avisos e status', 'url' => '/avisos'],
    ];

    public const DEFAULT_META_DESCRIPTION = 'Planos de hospedagem, domínios e suporte para manter seus projetos online.';

    public const DEFAULT_BRAND_COLOR = '#7C3AED';

    public const DEFAULT_ACCENT_COLOR = '#C040E0';

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

    public static function isSafeFooterUrl(mixed $url): bool
    {
        if (! is_string($url) || $url === '' || strlen($url) > 255 || preg_match('/[\x00-\x1f\x7f]/', $url) || str_contains($url, '\\')) {
            return false;
        }
        if (str_starts_with($url, 'tel:')) {
            return (bool) preg_match('/^tel:\+?[0-9(). -]{5,32}$/D', $url);
        }
        if (str_contains($url, ' ')) {
            return false;
        }
        if (self::isSafeSocialLink($url)) {
            return true;
        }
        if (str_starts_with($url, 'mailto:')) {
            return filter_var(substr($url, 7), FILTER_VALIDATE_EMAIL) !== false;
        }
        if (! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return false;
        }
        $parts = parse_url($url);

        return is_array($parts)
            && ! isset($parts['scheme'])
            && ! isset($parts['host'])
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }

    public static function safeFooterLinks(mixed $links): array
    {
        if (! is_array($links)) {
            return [];
        }

        $safe = [];
        foreach (array_slice($links, 0, 10) as $link) {
            if (! is_array($link)) {
                continue;
            }
            $group = $link['group'] ?? null;
            $label = $link['label'] ?? null;
            $url = $link['url'] ?? null;
            if (! in_array($group, ['explore', 'information'], true)
                || ! is_string($label)
                || trim($label) === ''
                || mb_strlen($label) > 60
                || ! self::isSafeFooterUrl($url)) {
                continue;
            }
            $safe[] = ['group' => $group, 'label' => trim($label), 'url' => $url];
        }

        return $safe;
    }

    public static function safeColor(mixed $color, string $fallback): string
    {
        return is_string($color) && preg_match('/^#[a-f0-9]{6}$/iD', $color) ? strtoupper($color) : $fallback;
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
        View::share('siteFavicon', null);
        View::share('siteOpenGraphImage', null);
        View::share('siteMetaDescription', self::DEFAULT_META_DESCRIPTION);
        View::share('siteBrandColor', self::DEFAULT_BRAND_COLOR);
        View::share('siteAccentColor', self::DEFAULT_ACCENT_COLOR);
        View::share('siteFooterExploreTitle', 'Explore');
        View::share('siteFooterInfoTitle', 'Informações');
        View::share('siteFooterLinks', self::DEFAULT_FOOTER_LINKS);
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
            View::share('siteFavicon', $s->favicon_mime ? route('brand.favicon', ['v' => $s->version]) : null);
            View::share('siteOpenGraphImage', $s->og_image_mime ? route('brand.social-card', ['v' => $s->version]) : null);
            View::share('siteMetaDescription', trim($s->meta_description ?? '') ?: self::DEFAULT_META_DESCRIPTION);
            View::share('siteBrandColor', self::safeColor($s->brand_color, self::DEFAULT_BRAND_COLOR));
            View::share('siteAccentColor', self::safeColor($s->accent_color, self::DEFAULT_ACCENT_COLOR));
            View::share('siteFooterExploreTitle', trim($s->footer_explore_title ?? '') ?: 'Explore');
            View::share('siteFooterInfoTitle', trim($s->footer_info_title ?? '') ?: 'Informações');
            View::share('siteFooterLinks', $s->footer_links === null ? self::DEFAULT_FOOTER_LINKS : self::safeFooterLinks($s->footer_links));
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
