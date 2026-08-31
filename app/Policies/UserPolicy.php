<?php

namespace App\Policies;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Support\Authorization\Access;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('manage-users');
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->can('manage-users');
    }

    public function create(User $actor): bool
    {
        return $actor->can('create-users');
    }

    public function update(User $actor, User $user): bool
    {
        return ! $actor->is($user) && $actor->can('edit-users') && $this->canManageProtectedUser($actor, $user);
    }

    public function changeStatus(User $actor, User $user): bool
    {
        return ! $actor->is($user) && $actor->can('disable-users') && $this->canManageProtectedUser($actor, $user);
    }

    public function delete(User $actor, User $user): bool
    {
        return ! $actor->is($user) && $actor->can('delete-users') && $this->canManageProtectedUser($actor, $user);
    }

    public function assignRoles(User $actor, User $user): bool
    {
        return ! $actor->is($user) && $actor->can('manage-roles') && $this->canManageProtectedUser($actor, $user);
    }

    public function manageSecurity(User $actor, User $user): bool
    {
        return $actor->is($user) && $actor->status === AccountStatus::Active && $actor->hasVerifiedEmail();
    }

    private function canManageProtectedUser(User $actor, User $user): bool
    {
        return ! $user->hasRole(Access::SUPER_ADMINISTRATOR) || $actor->hasRole(Access::SUPER_ADMINISTRATOR);
    }
}
