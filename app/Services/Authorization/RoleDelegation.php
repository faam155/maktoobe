<?php

namespace App\Services\Authorization;

use App\Models\User;
use App\Support\Authorization\Access;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class RoleDelegation
{
    public function assignableRoles(User $actor): Collection
    {
        $permissionNames = $actor->getAllPermissions()->pluck('name');

        return Role::query()->with('permissions')->orderBy('name')->get()->filter(function (Role $role) use ($actor, $permissionNames) {
            if (Access::isProtectedRole($role->name) && ! $actor->hasRole(Access::SUPER_ADMINISTRATOR)) {
                return false;
            }

            return $role->permissions->pluck('name')->diff($permissionNames)->isEmpty();
        })->values();
    }

    public function assertPermissionsDelegable(User $actor, array $permissionNames): void
    {
        $unauthorized = collect($permissionNames)->diff($actor->getAllPermissions()->pluck('name'));
        if ($unauthorized->isNotEmpty()) {
            throw ValidationException::withMessages(['permissions' => __('admin.permission_escalation')]);
        }
    }
}
