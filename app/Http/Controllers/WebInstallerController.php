<?php

namespace App\Http\Controllers;

use App\Services\SiteConfiguration;
use App\Services\WebInstaller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class WebInstallerController extends Controller
{
    private function authorizePage(Request $r, WebInstaller $setup): array
    {
        abort_unless($state = $setup->state(), 404);
        abort_if(app()->isProduction() && ! $r->isSecure(), 403, 'Instalação exige HTTPS.');

        return $state;
    }

    public function index(Request $r, WebInstaller $setup)
    {
        $s = $this->authorizePage($r, $setup);

        return response()->view('setup.index', ['authorized' => hash_equals($s['hash'], (string) $r->session()->get('setup_grant', '')), 'checks' => $setup->requirements()])->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function unlock(Request $r, WebInstaller $setup)
    {
        $s = $this->authorizePage($r, $setup);
        $v = $r->validate(['setup_key' => 'required|string|size:64']);
        abort_unless(hash_equals($s['hash'], hash('sha256', $v['setup_key'])), 403, 'Chave de instalação inválida.');
        $r->session()->regenerate();
        $r->session()->put('setup_grant', $s['hash']);

        return redirect()->route('setup.index');
    }

    public function finish(Request $r, WebInstaller $setup)
    {
        $s = $this->authorizePage($r, $setup);
        abort_unless(hash_equals($s['hash'], (string) $r->session()->get('setup_grant', '')), 403);
        $v = $r->validate(['site_name' => 'required|string|max:80', 'url' => 'required|string|max:255', 'db_driver' => 'required|in:sqlite,mysql', 'db_host' => 'nullable|required_if:db_driver,mysql|regex:/^[A-Za-z0-9.:-]+$/D|max:253', 'db_port' => 'required|integer|min:1|max:65535', 'db_database' => 'nullable|required_if:db_driver,mysql|regex:/^[A-Za-z0-9_-]{1,64}$/D', 'db_username' => 'nullable|required_if:db_driver,mysql|string|max:100', 'db_password' => 'nullable|string|max:2000', 'admin_name' => 'required|string|max:100', 'admin_email' => 'required|email|max:254', 'password' => ['required', 'confirmed', 'max:128', Password::min(12)->letters()->numbers()], 'ack' => 'accepted']);
        $v['url'] = SiteConfiguration::origin($v['url']);
        try {
            $setup->finish($v, $s['hash']);
        } catch (\Throwable $e) {
            return back()->withErrors(['setup' => $e->getMessage()]);
        }
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return response()->view('setup.done', ['url' => $v['url']])->header('Cache-Control','no-store, private');
    }
}
