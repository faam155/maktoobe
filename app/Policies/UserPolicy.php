<?php

namespace App\Policies;

use App\Enums\AccountStatus;
use App\Models\User;

class UserPolicy
{
    public function manageSecurity(User $actor, User $user): bool
    {
        return $actor->is($user) && $actor->status === AccountStatus::Active && $actor->hasVerifiedEmail();
    }
}
