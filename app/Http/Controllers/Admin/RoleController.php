<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Administration\CreateRole;
use App\Actions\Administration\UpdateRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController
{
    public function index(): mixed
    {
        Gate::authorize('viewAny', Role::class);

        return view('admin.roles.index', ['roles' => Role::withCount(['users', 'permissions'])->orderBy('name')->paginate(15)]);
    }

    public function create(Request $request): mixed
    {
        Gate::authorize('create', Role::class);

        return view('admin.roles.create', ['permissions' => $this->permissions($request)]);
    }

    public function store(Request $request, CreateRole $create): mixed
    {
        $role = $create->handle($request->user(), $request->all());

        return redirect()->route('admin.roles.show', $role)->with('status', __('admin.role_created'));
    }

    public function show(Role $role): mixed
    {
        Gate::authorize('view', $role);

        return view('admin.roles.show', [
            'role' => $role->load('permissions'),
            'users' => $role->users()->orderBy('name')->paginate(15),
        ]);
    }

    public function edit(Request $request, Role $role): mixed
    {
        Gate::authorize('update', $role);

        return view('admin.roles.edit', [
            'role' => $role->load('permissions'),
            'permissions' => $this->permissions($request),
            'canManagePermissions' => Gate::allows('updatePermissions', $role),
        ]);
    }

    public function update(Request $request, Role $role, UpdateRole $update): mixed
    {
        $role = $update->handle($request->user(), $role, $request->all());

        return redirect()->route('admin.roles.show', $role)->with('status', __('admin.role_updated'));
    }

    private function permissions(Request $request): mixed
    {
        if (! $request->user()->can('manage-permissions')) {
            return collect();
        }
        $names = $request->user()->getAllPermissions()->pluck('name');

        return Permission::whereIn('name', $names)->orderBy('name')->get();
    }
}
