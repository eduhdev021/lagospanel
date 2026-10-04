<?php

namespace App\Http\Controllers;

use App\Models\DownloadAsset;
use App\Services\Audit;
use App\Services\Downloads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class DownloadController extends Controller
{
    public function index(Request $r)
    {
        $admin = $r->routeIs('admin.*');
        $q = DownloadAsset::with('product');
        if (! $admin) {
            $q->availableTo($r->user());
        }

        return view('downloads.index', ['assets' => $q->latest()->paginate(20), 'admin' => $admin]);
    }

    public function create(Request $r, Downloads $downloads)
    {
        $r->validate(['file' => 'required|file|max:10240']);
        $downloads->create($r->user(), $r->only(['title', 'description', 'product_id', 'active']), $r->file('file'));

        return back()->with('status', 'Arquivo armazenado com criptografia e controle de acesso.');
    }

    public function state(Request $r, DownloadAsset $asset)
    {
        $v = $r->validate(['active' => 'required|boolean']);
        DB::transaction(function () use ($r, $asset, $v) {
            $a = DownloadAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $a->update(['active' => (bool) $v['active']]);
            Audit::record('download.availability', 'download:'.$a->id, ['active' => $a->active], $r->user()->id);
        }, 5);

        return back()->with('status', 'Disponibilidade atualizada.');
    }

    public function fetch(Request $r, DownloadAsset $asset, Downloads $downloads)
    {
        $admin = $r->routeIs('admin.*');
        abort_unless($admin || DownloadAsset::availableTo($r->user())->whereKey($asset->id)->exists(), 404);
        try {
            $bytes = $downloads->bytes($asset);
        } catch (HttpExceptionInterface $e) {
            return response($e->getStatusCode() === 409 ? 'Arquivo indisponível ou com integridade inválida.' : 'Arquivo não encontrado.', $e->getStatusCode());
        }
        // Recheck entitlement after storage I/O; never expose a file withdrawn in that interval.
        abort_unless($admin || DownloadAsset::availableTo($r->user())->whereKey($asset->id)->exists(), 404);
        Audit::record('download.retrieved', 'download:'.$asset->id, [], $r->user()->id);

        return response($bytes, 200, ['Content-Type' => 'application/octet-stream', 'Content-Disposition' => 'attachment; filename="'.$asset->filename.'"', 'Cache-Control' => 'no-store, private', 'Content-Security-Policy' => "default-src 'none'; sandbox", 'X-Content-Type-Options' => 'nosniff']);
    }
}
