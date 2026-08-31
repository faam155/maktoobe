<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Administration\ChangeUserStatus;
use App\Actions\Administration\CreateUser;
use App\Actions\Administration\DeleteUser;
use App\Actions\Administration\SyncUserRoles;
use App\Actions\Administration\UpdateUser;
use App\Models\User;
use App\Queries\Administration\UserDirectory;
use App\Services\Authorization\RoleDelegation;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController
{
    public function index(Request $request, UserDirectory $directory): mixed
    {
        Gate::authorize('viewAny', User::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending', 'active', 'disabled'])],
            'role' => ['nullable', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ]);

        return view('admin.users.index', ['users' => $directory->get($filters), 'roles' => Role::orderBy('name')->get(), 'filters' => $filters]);
    }

    public function create(): mixed
    {
        Gate::authorize('create', User::class);

        return view('admin.users.create');
    }

    public function store(Request $request, CreateUser $create): mixed
    {
        $user = $create->handle($request->user(), $request->all());
        event(new Registered($user));

        return redirect()->route('admin.users.show', $user)->with('status', __('admin.user_created'));
    }

    public function show(Request $request, User $user, RoleDelegation $delegation): mixed
    {
        Gate::authorize('view', $user);
        $user->load('roles.permissions');

        return view('admin.users.show', [
            'managedUser' => $user,
            'assignableRoles' => Gate::allows('assignRoles', $user) ? $delegation->assignableRoles($request->user()) : collect(),
        ]);
    }

    public function edit(User $user): mixed
    {
        Gate::authorize('update', $user);

        return view('admin.users.edit', ['managedUser' => $user]);
    }

    public function update(Request $request, User $user, UpdateUser $update): mixed
    {
        $update->handle($request->user(), $user, $request->all());

        return redirect()->route('admin.users.show', $user)->with('status', __('admin.user_updated'));
    }

    public function status(Request $request, User $user, ChangeUserStatus $change): mixed
    {
        $change->handle($request->user(), $user, $request->all());

        return back()->with('status', __('admin.status_updated'));
    }

    public function roles(Request $request, User $user, SyncUserRoles $sync): mixed
    {
        $sync->handle($request->user(), $user, $request->input('roles', []));

        return back()->with('status', __('admin.roles_updated'));
    }

    public function destroy(Request $request, User $user, DeleteUser $delete): mixed
    {
        $delete->handle($request->user(), $user);

        return redirect()->route('admin.users.index')->with('status', __('admin.user_deleted'));
    }
}
