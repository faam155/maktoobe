<?php

namespace App\Services\Authorization;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Support\Authorization\Access;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class SuperAdministratorGuard
{
    public function lockGovernance(): ?Role
    {
        return Role::where('name', Access::SUPER_ADMINISTRATOR)->where('guard_name', 'web')->lockForUpdate()->first();
    }

    public function assertCanRemoveActiveSuper(User $user): void
    {
        if ($user->status !== AccountStatus::Active || ! $user->hasRole(Access::SUPER_ADMINISTRATOR)) {
            return;
        }

        $activeIds = User::role(Access::SUPER_ADMINISTRATOR)
            ->where('status', AccountStatus::Active)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');

        if ($activeIds->contains($user->id) && $activeIds->count() <= 1) {
            throw ValidationException::withMessages(['status' => __('admin.last_super')]);
        }
    }
}
