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
    public function index()
    {
        return view('admin.site-settings', ['setting' => SiteSetting::find(1)]);
    }

    public function save(Request $r)
    {
        $v = $r->validate(['name' => 'required|string|max:80', 'url' => 'required|string|max:255', 'support_email' => 'nullable|email|max:254', 'registration_enabled' => 'sometimes|boolean', 'version' => 'required|integer|min:0', 'logo' => 'nullable|file|mimes:png,jpg,jpeg,webp|max:200', 'remove_logo' => 'sometimes|boolean', 'mailer' => 'required|in:inherit,log,smtp', 'smtp_host' => 'nullable|required_if:mailer,smtp|string|max:253', 'smtp_port' => 'required|integer|min:1|max:65535', 'smtp_scheme' => 'required|in:smtp,smtps', 'smtp_username' => 'nullable|string|max:200', 'smtp_password' => 'nullable|string|max:2000', 'clear_smtp_password' => 'sometimes|boolean', 'mail_from_address' => 'nullable|required_if:mailer,smtp|email|max:254']);
        $v['url'] = SiteConfiguration::origin($v['url']);
        if (! empty($v['smtp_host']) && (! preg_match('/^[A-Za-z0-9.:-]+$/D', $v['smtp_host']) || str_contains($v['smtp_host'], '://'))) {
            throw ValidationException::withMessages(['smtp_host' => 'Host SMTP inválido.']);
        }
        $logo = null;
        $mime = null;
        if ($file = $r->file('logo')) {
            $logo = $file->get();
            $size = @getimagesizefromstring($logo);
            if (! $size || $size[0] > 1600 || $size[1] > 1600 || ! in_array($size['mime'] ?? '', ['image/png', 'image/jpeg', 'image/webp'], true)) {
                throw ValidationException::withMessages(['logo' => 'Use PNG, JPEG ou WebP válido, até 1600 × 1600 pixels e 200 KiB.']);
            }$mime = $size['mime'];
        }
        DB::transaction(function () use ($r, $v, $logo, $mime) {
            DB::table('site_settings')->insertOrIgnore(['id' => 1, 'name' => 'LagosPanel', 'url' => config('app.url'), 'version' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $s = SiteSetting::lockForUpdate()->findOrFail(1);
            abort_unless($s->version === (int) $v['version'], 409, 'Configuração alterada. Recarregue a página.');
            $data = array_intersect_key($v, array_flip(['name', 'url', 'support_email', 'mailer', 'smtp_host', 'smtp_port', 'smtp_scheme', 'smtp_username', 'mail_from_address']));
            $data += ['registration_enabled' => $r->boolean('registration_enabled'), 'version' => $s->version + 1];
            if ($r->boolean('clear_smtp_password')) {
                $data['smtp_password'] = null;
            } elseif (! empty($v['smtp_password'])) {
                $data['smtp_password'] = $v['smtp_password'];
            }
            if ($r->boolean('remove_logo')) {
                $data += ['logo_content' => null, 'logo_mime' => null];
            } elseif ($logo !== null) {
                $data += ['logo_content' => base64_encode($logo), 'logo_mime' => $mime];
            }
            $s->update($data);
            Audit::record('site.settings_updated', 'site:1', ['version' => $s->version], $r->user()->id);
        }, 5);

        return back()->with('status', 'Configuração salva. Se mudou a URL, acesse o endereço novo; DNS/TLS devem estar preparados.');
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
