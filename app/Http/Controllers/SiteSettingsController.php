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
            'favicon' => 'nullable|file|mimes:png,webp|max:100',
            'og_image' => 'nullable|file|mimes:png,jpg,jpeg,webp|max:600',
            'remove_logo' => 'sometimes|boolean',
            'remove_favicon' => 'sometimes|boolean',
            'remove_og_image' => 'sometimes|boolean',
            'footer_description' => 'nullable|string|max:500',
            'footer_copyright' => 'nullable|string|max:240',
            'footer_tagline' => 'nullable|string|max:180',
            'footer_explore_title' => 'nullable|string|max:60',
            'footer_info_title' => 'nullable|string|max:60',
            'footer_links' => 'sometimes|array|max:10',
            'footer_links.*' => 'array:group,label,url,order',
            'footer_links.*.group' => 'nullable|in:explore,information',
            'footer_links.*.label' => 'nullable|string|max:60',
            'footer_links.*.url' => 'nullable|string|max:255',
            'footer_links.*.order' => 'nullable|integer|min:1|max:10',
            'social_links' => 'sometimes|array:instagram,facebook,x,youtube,linkedin,tiktok,whatsapp,discord',
            'social_links.instagram' => ['nullable', 'string', 'url', 'max:255', $this->httpsSocialLinkRule()],
            'social_links.facebook' => ['nullable', 'string', 'url', 'max:255', $this->httpsSocialLinkRule()],
            'social_links.x' => ['nullable', 'string', 'url', 'max:255', $this->httpsSocialLinkRule()],
            'social_links.youtube' => ['nullable', 'string', 'url', 'max:255', $this->httpsSocialLinkRule()],
            'social_links.linkedin' => ['nullable', 'string', 'url', 'max:255', $this->httpsSocialLinkRule()],
            'social_links.tiktok' => ['nullable', 'string', 'url', 'max:255', $this->httpsSocialLinkRule()],
            'social_links.whatsapp' => ['nullable', 'string', 'url', 'max:255', $this->httpsSocialLinkRule()],
            'social_links.discord' => ['nullable', 'string', 'url', 'max:255', $this->httpsSocialLinkRule()],
            'brand_color' => ['nullable', 'regex:/^#[a-fA-F0-9]{6}$/D'],
            'accent_color' => ['nullable', 'regex:/^#[a-fA-F0-9]{6}$/D'],
            'meta_description' => 'nullable|string|max:320',
            'mailer' => 'required|in:inherit,log,smtp',
            'smtp_host' => 'nullable|required_if:mailer,smtp|string|max:253',
            'smtp_port' => 'required|integer|min:1|max:65535',
            'smtp_scheme' => 'required|in:smtp,smtps',
            'smtp_username' => 'nullable|string|max:200',
            'smtp_password' => 'nullable|string|max:2000',
            'clear_smtp_password' => 'sometimes|boolean',
            'mail_from_address' => 'nullable|required_if:mailer,smtp|email|max:254',
        ];
        $general = [
            'name', 'url', 'support_email', 'registration_enabled', 'version', 'logo', 'favicon', 'og_image',
            'remove_logo', 'remove_favicon', 'remove_og_image', 'footer_description', 'footer_copyright',
            'footer_tagline', 'footer_explore_title', 'footer_info_title', 'footer_links', 'footer_links.*',
            'footer_links.*.group', 'footer_links.*.label', 'footer_links.*.url', 'footer_links.*.order', 'social_links',
            'social_links.instagram', 'social_links.facebook', 'social_links.x', 'social_links.youtube',
            'social_links.linkedin', 'social_links.tiktok', 'social_links.whatsapp', 'social_links.discord',
            'brand_color', 'accent_color', 'meta_description',
        ];
        if ($section === 'general') {
            $rules = array_intersect_key($rules, array_flip($general));
        }
        if ($section === 'email') {
            $rules = array_diff_key($rules, array_flip(array_diff($general, ['version'])));
        }

        $v = $r->validate($rules);
        $this->normalizeSocialLinks($v);
        $this->normalizeFooterLinks($v);
        if (isset($v['url'])) {
            $v['url'] = SiteConfiguration::origin($v['url']);
        }
        if (! empty($v['smtp_host']) && (! preg_match('/^[A-Za-z0-9.:-]+$/D', $v['smtp_host']) || str_contains($v['smtp_host'], '://'))) {
            throw ValidationException::withMessages(['smtp_host' => 'Host SMTP inválido.']);
        }

        $uploads = [];
        if ($section !== 'email') {
            $uploads['logo'] = $this->imageUpload($r, 'logo', ['image/png', 'image/jpeg', 'image/webp'], 1600, 1600, 200);
            $uploads['favicon'] = $this->imageUpload($r, 'favicon', ['image/png', 'image/webp'], 512, 512, 100, true);
            $uploads['og_image'] = $this->imageUpload($r, 'og_image', ['image/png', 'image/jpeg', 'image/webp'], 2400, 1260, 600);
        }

        DB::transaction(function () use ($r, $v, $uploads, $section) {
            DB::table('site_settings')->insertOrIgnore(['id' => 1, 'name' => 'LagosPanel', 'url' => config('app.url'), 'version' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $s = SiteSetting::lockForUpdate()->findOrFail(1);
            abort_unless($s->version === (int) $v['version'], 409, 'Configuração alterada. Recarregue a página.');
            $data = array_intersect_key($v, array_flip([
                'name', 'url', 'support_email', 'footer_description', 'footer_copyright', 'footer_tagline',
                'footer_explore_title', 'footer_info_title', 'footer_links', 'social_links', 'brand_color',
                'accent_color', 'meta_description', 'mailer', 'smtp_host', 'smtp_port', 'smtp_scheme',
                'smtp_username', 'mail_from_address',
            ]));
            $data['version'] = $s->version + 1;

            if ($section !== 'email') {
                $data['registration_enabled'] = $r->boolean('registration_enabled');
                foreach (['logo', 'favicon', 'og_image'] as $image) {
                    $remove = $r->boolean('remove_'.$image);
                    if ($remove) {
                        $data[$image.'_content'] = null;
                        $data[$image.'_mime'] = null;
                    } elseif ($uploads[$image] !== null) {
                        $data[$image.'_content'] = $uploads[$image]['content'];
                        $data[$image.'_mime'] = $uploads[$image]['mime'];
                    }
                }
            }
            if ($section !== 'general' && $r->boolean('clear_smtp_password')) {
                $data['smtp_password'] = null;
            } elseif ($section !== 'general' && ! empty($v['smtp_password'])) {
                $data['smtp_password'] = $v['smtp_password'];
            }

            $s->update($data);
            Audit::record('site.settings_updated', 'site:1', ['version' => $s->version], $r->user()->id);
        }, 5);

        return back()->with('status', 'Configuração salva. Se mudou a URL, acesse o endereço novo; DNS/TLS devem estar preparados.');
    }

    private function normalizeSocialLinks(array &$values): void
    {
        if (! isset($values['social_links'])) {
            return;
        }

        $values['social_links'] = array_filter($values['social_links'], fn ($url) => filled($url));
        if ($values['social_links'] === []) {
            $values['social_links'] = null;
        }
    }

    private function normalizeFooterLinks(array &$values): void
    {
        if (! isset($values['footer_links'])) {
            return;
        }

        $links = [];
        $position = 0;
        foreach ($values['footer_links'] as $index => $link) {
            $group = $link['group'] ?? '';
            $label = trim((string) ($link['label'] ?? ''));
            $url = trim((string) ($link['url'] ?? ''));
            if ($label === '' && $url === '') {
                $position++;
                continue;
            }
            if (! in_array($group, ['explore', 'information'], true)) {
                throw ValidationException::withMessages(["footer_links.$index.group" => 'Escolha uma coluna válida para este link.']);
            }
            if ($label === '' || $url === '') {
                throw ValidationException::withMessages(["footer_links.$index.url" => 'Preencha o nome e o endereço do link, ou deixe ambos vazios para ocultá-lo.']);
            }
            if (! SiteConfiguration::isSafeFooterUrl($url)) {
                throw ValidationException::withMessages(["footer_links.$index.url" => 'Use um caminho do próprio site, um endereço HTTPS ou um contato mailto:/tel: válido.']);
            }
            $order = isset($link['order']) ? (int) $link['order'] : $position + 1;
            $links[] = [
                'order' => $order,
                'position' => $position,
                'link' => ['group' => $group, 'label' => $label, 'url' => $url],
            ];
            $position++;
        }
        usort($links, fn ($a, $b) => [$a['order'], $a['position']] <=> [$b['order'], $b['position']]);
        $values['footer_links'] = array_values(array_map(fn ($item) => $item['link'], $links));
    }

    private function httpsSocialLinkRule(): \Closure
    {
        return static function ($attribute, $value, $fail): void {
            if (! SiteConfiguration::isSafeSocialLink($value)) {
                $fail('Use um link público HTTPS, sem usuário ou senha na URL.');
            }
        };
    }

    private function imageUpload(Request $request, string $field, array $allowedMimes, int $maxWidth, int $maxHeight, int $maxKilobytes, bool $square = false): ?array
    {
        $file = $request->file($field);
        if (! $file) {
            return null;
        }

        $contents = $file->get();
        $image = @getimagesizefromstring($contents);
        if (! $image
            || ! in_array($image['mime'] ?? '', $allowedMimes, true)
            || $image[0] > $maxWidth
            || $image[1] > $maxHeight
            || ($square && $image[0] !== $image[1])
            || strlen($contents) > $maxKilobytes * 1024) {
            $message = match ($field) {
                'logo' => 'Use PNG, JPEG ou WebP válido, até 1600 × 1600 pixels e 200 KiB.',
                'favicon' => 'Use favicon PNG/WebP quadrado, até 512 × 512 pixels e 100 KiB.',
                default => 'Use imagem PNG/JPEG/WebP válida, até 2400 × 1260 pixels e 600 KiB.',
            };
            throw ValidationException::withMessages([$field => $message]);
        }

        return ['content' => base64_encode($contents), 'mime' => $image['mime']];
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
        return $this->serveStoredImage('logo', ['image/png', 'image/jpeg', 'image/webp']);
    }

    public function favicon()
    {
        return $this->serveStoredImage('favicon', ['image/png', 'image/webp']);
    }

    public function socialCard()
    {
        return $this->serveStoredImage('og_image', ['image/png', 'image/jpeg', 'image/webp']);
    }

    private function serveStoredImage(string $key, array $allowedMimes)
    {
        $setting = SiteSetting::find(1);
        $encoded = $setting?->getAttribute($key.'_content');
        $mime = $setting?->getAttribute($key.'_mime');
        abort_unless(is_string($encoded) && in_array($mime, $allowedMimes, true), 404);
        $contents = base64_decode($encoded, true);
        abort_unless(is_string($contents), 404);

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
