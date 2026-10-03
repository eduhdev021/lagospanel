<?php

namespace App\Http\Controllers;

use App\Models\StaffRole;
use App\Models\User;
use App\Services\Audit;
use App\Support\AdminPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index(Request $r)
    {
        return view('admin.team', ['roles' => StaffRole::all(), 'users' => User::with('staffRole')->orderBy('id')->paginate(25), 'permissions' => array_values(array_filter(AdminPermissions::ALL, fn ($p) => $r->user()->hasPermission($p)))]);
    }

    public function create(Request $r)
    {
        $v = $r->validate(['name' => 'required|string|max:100|unique:staff_roles', 'permissions' => 'required|array|min:1', 'permissions.*' => ['string', Rule::in(AdminPermissions::ALL)]]);
        foreach ($v['permissions'] as $p) {
            abort_unless($r->user()->hasPermission($p), 403);
        }$role = StaffRole::create(['name' => $v['name'], 'permissions' => array_values(array_unique($v['permissions']))]);
        Audit::record('role.created', 'role:'.$role->id, [], $r->user()->id);

        return back()->with('status', 'Função criada.');
    }

    public function assign(Request $r, User $user)
    {
        $v = $r->validate(['staff_role_id' => 'nullable|integer|exists:staff_roles,id']);
        DB::transaction(function () use ($r, $user, $v) {
            $target = User::lockForUpdate()->findOrFail($user->id);
            abort_if($target->is_admin, 403, 'Administradores principais não podem ser alterados por esta tela.');
            $role = isset($v['staff_role_id']) ? StaffRole::findOrFail($v['staff_role_id']) : null;
            foreach (array_merge($target->staffRole?->permissions ?? [], $role?->permissions ?? []) as $p) {
                abort_unless($r->user()->hasPermission($p), 403);
            }$target->forceFill(['staff_role_id' => $role?->id])->save();
            Audit::record('role.assigned', 'user:'.$target->id, ['role' => $role?->id], $r->user()->id);
        });

        return back()->with('status', 'Permissões atualizadas.');
    }
}
