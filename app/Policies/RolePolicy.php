<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Authorization\Access;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('manage-roles') || $actor->can('manage-permissions');
    }

    public function view(User $actor, Role $role): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->can('manage-roles');
    }

    public function update(User $actor, Role $role): bool
    {
        return $actor->can('manage-roles') && ! Access::isProtectedRole($role->name);
    }

    public function updatePermissions(User $actor, Role $role): bool
    {
        return $actor->can('manage-roles') && $actor->can('manage-permissions') && ! Access::isProtectedRole($role->name);
    }
}
