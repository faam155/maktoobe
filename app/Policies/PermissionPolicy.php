<?php

namespace App\Policies;

use App\Models\User;

class PermissionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('manage-permissions');
    }
}
