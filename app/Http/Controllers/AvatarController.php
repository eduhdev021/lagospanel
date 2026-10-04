<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Audit;
use App\Services\Avatar;
use Illuminate\Http\Request;

class AvatarController extends Controller
{
    public function show(Request $r)
    {
        $u = User::findOrFail($r->user()->id);
        abort_unless($u->avatar_content && in_array($u->avatar_mime, ['image/png', 'image/jpeg', 'image/webp'], true), 404);

        return response(base64_decode($u->avatar_content, true), 200, ['Content-Type' => $u->avatar_mime, 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function save(Request $r, Avatar $avatar)
    {
        $v = $r->validate(['source' => 'required|in:upload,gravatar,remove', 'avatar' => 'nullable|required_if:source,upload|file|max:256', 'consent' => 'accepted_if:source,gravatar']);
        if ($v['source'] === 'remove') {
            $data = ['avatar_content' => null, 'avatar_mime' => null, 'avatar_source' => null, 'avatar_updated_at' => now()];
        } elseif ($v['source'] === 'gravatar') {
            $data = $avatar->gravatar($r->user()->email);
        } else {
            $data = $avatar->validateImage($r->file('avatar')->get()) + ['avatar_source' => 'upload'];
        }
        $r->user()->forceFill($data)->save();
        Audit::record('profile.avatar_updated', 'user:'.$r->user()->id, ['source' => $v['source']], $r->user()->id);

        return back()->with('status', $v['source'] === 'remove' ? 'Foto removida.' : 'Foto atualizada.');
    }
}
