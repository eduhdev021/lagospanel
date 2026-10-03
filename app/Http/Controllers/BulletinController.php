<?php

namespace App\Http\Controllers;

use App\Models\Bulletin;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BulletinController extends Controller
{
    public function index(Request $r)
    {
        $admin = $r->routeIs('admin.*');
        $q = Bulletin::query();
        if (! $admin) {
            $q->visible();
        }$active = Bulletin::visible()->whereIn('kind', ['incident', 'maintenance'])->where('state', '!=', 'resolved')->whereIn('severity', ['degraded', 'outage'])->count();

        return view('bulletins.index', ['bulletins' => $q->orderByDesc('published_at')->orderByDesc('id')->paginate(20), 'admin' => $admin, 'active' => $active]);
    }

    public function show(Request $r, Bulletin $bulletin)
    {
        $admin = $r->routeIs('admin.*');
        abort_unless($admin || Bulletin::visible()->whereKey($bulletin->id)->exists(), 404);

        return view('bulletins.show', ['bulletin' => $bulletin, 'updates' => $bulletin->updates()->oldest('id')->paginate(30), 'admin' => $admin]);
    }

    public function create(Request $r)
    {
        $v = $r->validate(['title' => 'required|string|max:180', 'body' => 'required|string|max:20000', 'kind' => 'required|in:announcement,incident,maintenance', 'severity' => 'required|in:information,degraded,outage', 'published' => 'sometimes|boolean', 'published_at' => 'nullable|date_format:Y-m-d\TH:i']);
        $b = DB::transaction(function () use ($v, $r) {
            $b = Bulletin::create(['author_id' => $r->user()->id, 'title' => $v['title'], 'body' => $v['body'], 'kind' => $v['kind'], 'severity' => $v['kind'] === 'announcement' ? 'information' : $v['severity'], 'state' => 'open', 'published' => $r->boolean('published'), 'published_at' => $v['published_at'] ?? now()]);
            Audit::record('bulletin.created', 'bulletin:'.$b->id, [], $r->user()->id);

            return $b;
        }, 5);

        return redirect()->route('admin.bulletins.show', $b);
    }

    public function update(Request $r, Bulletin $bulletin)
    {
        $v = $r->validate(['version' => 'required|integer|min:1', 'body' => 'required|string|min:3|max:20000', 'state' => 'required|in:open,monitoring,resolved', 'published' => 'sometimes|boolean']);
        DB::transaction(function () use ($v, $r, $bulletin) {
            $b = Bulletin::whereKey($bulletin->id)->lockForUpdate()->firstOrFail();
            abort_unless($b->version === (int) $v['version'], 409);
            $b->updates()->create(['author_id' => $r->user()->id, 'body' => $v['body'], 'state' => $v['state']]);
            $b->update(['state' => $v['state'], 'published' => $r->boolean('published'), 'version' => $b->version + 1]);
            Audit::record('bulletin.updated', 'bulletin:'.$b->id, ['version' => $b->version, 'state' => $b->state, 'published' => $b->published], $r->user()->id);
        }, 5);

        return back()->with('status', 'Atualização registrada no histórico.');
    }
}
