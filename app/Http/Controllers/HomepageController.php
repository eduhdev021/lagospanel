<?php

namespace App\Http\Controllers;

use App\Models\HomepageSetting;
use App\Models\Product;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomepageController extends Controller
{
    public function index()
    {
        return view('home', ['home' => HomepageSetting::find(1)?->toArray() ?? HomepageSetting::DEFAULTS, 'products' => Product::where('active', true)->orderBy('price_minor')->limit(3)->get()]);
    }

    public function settings()
    {
        return view('admin.configuration.homepage', ['home' => HomepageSetting::find(1)?->toArray() ?? HomepageSetting::DEFAULTS]);
    }

    public function save(Request $r)
    {
        $v = $r->validate(['version' => 'required|integer|min:0', 'eyebrow' => 'required|string|max:60', 'title' => 'required|string|max:150', 'description' => 'required|string|max:600', 'cta' => 'required|string|max:40']);
        DB::transaction(function () use ($v, $r) {
            HomepageSetting::insertOrIgnore(['id' => 1, 'version' => 0, 'created_at' => now(), 'updated_at' => now()] + HomepageSetting::DEFAULTS);
            $h = HomepageSetting::lockForUpdate()->findOrFail(1);
            abort_unless($h->version === (int) $v['version'], 409, 'A página inicial foi alterada. Recarregue.');
            $h->update(array_replace($v, ['version' => $h->version + 1]));
            Audit::record('homepage.updated', 'homepage:1', [], $r->user()->id);
        }, 5);

        return back()->with('status', 'Página inicial salva.');
    }
}
