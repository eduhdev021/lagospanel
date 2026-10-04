<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use App\Services\Audit;
use App\Services\SiteConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class SiteSettingsController extends Controller
{
    public function index(Request $r)
    {
        return view('admin.site-settings', ['setting' => SiteSetting::find(1), 'section' => $r->route('section') ?? 'general']);
    }

    public function save(Request $r)
    {
        $section = $r->route('section') ?? 'all';
        $rules = [
            'name' => 'required|string|max:80',
            'url' => 'required|string|max:255',
            'support_email' => 'nullable|email|max:254',
            'registration_enabled' => 'sometimes|boolean',
            'version' => 'required|integer|min:0',
            'logo' => 'nullable|file|mimes:png,jpg,jpeg,webp|max:200',
            'remove_logo' => 'sometimes|boolean',
            'footer_description' => 'nullable|string|max:500',
            'footer_copyright' => 'nullable|string|max:240',
            'footer_tagline' => 'nullable|string|max:180',
            'social_links' => 'sometimes|array:instagram,facebook,x,youtube,linkedin,tiktok,whatsapp,discord',
            'social_links.instagram' => ['nullable', 'string', 'url', 'max:255', $this->httpsSocialLinkRule()],
            'social_links.facebook' => ['nullable', 'string', 'url', 'max:255', $this->httpsSocialLinkRule()],
            'social_links.x' => ['nullable', 'string', 'url', 'max:255', $this->httpsSocialLinkRule()],
            'social_links.youtube' => ['nullable', 'string', 'url', 'max:255', $this->httpsSocialLinkRule()],
            'social_links.linkedin' => ['nullable', 'string', 'url', 'max:255', $this->httpsSocialLinkRule()],
            'social_links.tiktok' => ['nullable', 'string', 'url', 'max:255', $this->httpsSocialLinkRule()],
            'social_links.whatsapp' => ['nullable', 'string', 'url', 'max:255', $this->httpsSocialLinkRule()],
            'social_links.discord' => ['nullable', 'string', 'url', 'max:255', $this->httpsSocialLinkRule()],
            'mailer' => 'required|in:inherit,log,smtp',
            'smtp_host' => 'nullable|required_if:mailer,smtp|string|max:253',
            'smtp_port' => 'required|integer|min:1|max:65535',
            'smtp_scheme' => 'required|in:smtp,smtps',
            'smtp_username' => 'nullable|string|max:200',
            'smtp_password' => 'nullable|string|max:2000',
            'clear_smtp_password' => 'sometimes|boolean',
            'mail_from_address' => 'nullable|required_if:mailer,smtp|email|max:254',
        ];
        $general = ['name', 'url', 'support_email', 'registration_enabled', 'version', 'logo', 'remove_logo', 'footer_description', 'footer_copyright', 'footer_tagline', 'social_links', 'social_links.instagram', 'social_links.facebook', 'social_links.x', 'social_links.youtube', 'social_links.linkedin', 'social_links.tiktok', 'social_links.whatsapp', 'social_links.discord'];
        if ($section === 'general') {
            $rules = array_intersect_key($rules, array_flip($general));
        }
        if ($section === 'email') {
            $rules = array_diff_key($rules, array_flip(array_diff($general, ['version'])));
        }
        $v = $r->validate($rules);
        if (isset($v['social_links'])) {
            $v['social_links'] = array_filter($v['social_links'], fn ($url) => filled($url));
            if ($v['social_links'] === []) {
                $v['social_links'] = null;
            }
        }
        if (isset($v['url'])) {
            $v['url'] = SiteConfiguration::origin($v['url']);
        }
        if (! empty($v['smtp_host']) && (! preg_match('/^[A-Za-z0-9.:-]+$/D', $v['smtp_host']) || str_contains($v['smtp_host'], '://'))) {
            throw ValidationException::withMessages(['smtp_host' => 'Host SMTP inválido.']);
        }
        $logo = null;
        $mime = null;
        if ($section !== 'email' && ($file = $r->file('logo'))) {
            $logo = $file->get();
            $size = @getimagesizefromstring($logo);
            if (! $size || $size[0] > 1600 || $size[1] > 1600 || ! in_array($size['mime'] ?? '', ['image/png', 'image/jpeg', 'image/webp'], true)) {
                throw ValidationException::withMessages(['logo' => 'Use PNG, JPEG ou WebP válido, até 1600 × 1600 pixels e 200 KiB.']);
            }$mime = $size['mime'];
        }
        DB::transaction(function () use ($r, $v, $logo, $mime, $section) {
            DB::table('site_settings')->insertOrIgnore(['id' => 1, 'name' => 'LagosPanel', 'url' => config('app.url'), 'version' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $s = SiteSetting::lockForUpdate()->findOrFail(1);
            abort_unless($s->version === (int) $v['version'], 409, 'Configuração alterada. Recarregue a página.');
            $data = array_intersect_key($v, array_flip(['name', 'url', 'support_email', 'footer_description', 'footer_copyright', 'footer_tagline', 'social_links', 'mailer', 'smtp_host', 'smtp_port', 'smtp_scheme', 'smtp_username', 'mail_from_address']));
            $data['version'] = $s->version + 1;
            if ($section !== 'email') {
                $data['registration_enabled'] = $r->boolean('registration_enabled');
            }
            if ($section !== 'general' && $r->boolean('clear_smtp_password')) {
                $data['smtp_password'] = null;
            } elseif ($section !== 'general' && ! empty($v['smtp_password'])) {
                $data['smtp_password'] = $v['smtp_password'];
            }
            if ($section !== 'email' && $r->boolean('remove_logo')) {
                $data += ['logo_content' => null, 'logo_mime' => null];
            } elseif ($logo !== null) {
                $data += ['logo_content' => base64_encode($logo), 'logo_mime' => $mime];
            }
            $s->update($data);
            Audit::record('site.settings_updated', 'site:1', ['version' => $s->version], $r->user()->id);
        }, 5);

        return back()->with('status', 'Configuração salva. Se mudou a URL, acesse o endereço novo; DNS/TLS devem estar preparados.');
    }

    private function httpsSocialLinkRule(): \Closure
    {
        return static function ($attribute, $value, $fail): void {
            if (! SiteConfiguration::isSafeSocialLink($value)) {
                $fail('Use um link público HTTPS, sem usuário ou senha na URL.');
            }
        };
    }

    public function testMail(Request $r)
    {
        try {
            Mail::raw('Teste de configuração de e-mail do '.config('app.name').'.', fn ($m) => $m->to($r->user()->email)->subject('Teste de e-mail do painel'));
        } catch (\Throwable) {
            return back()->withErrors(['mail' => 'Falha no envio de teste. Confira SMTP/TLS e credenciais; detalhes sensíveis não são exibidos.']);
        }
        Audit::record('site.mail_test', 'site:1', [], $r->user()->id);

        return back()->with('status', 'Teste entregue ao transport configurado. Modo log grava localmente; SMTP aceito não comprova entrega na caixa de entrada.');
    }

    public function logo()
    {
        $s = SiteSetting::find(1);
        abort_unless($s?->logo_content && in_array($s->logo_mime, ['image/png', 'image/jpeg', 'image/webp'], true), 404);

        return response(base64_decode($s->logo_content, true), 200, ['Content-Type' => $s->logo_mime, 'Cache-Control' => 'public, max-age=300', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox"]);
    }
}
