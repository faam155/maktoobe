<?php

namespace App\Policies;

use App\Models\BrandGuideline;
use App\Models\User;

class BrandGuidelinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-brand-guidelines');
    }

    public function view(User $user, BrandGuideline $guideline): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, BrandGuideline $guideline): bool
    {
        return $this->viewAny($user);
    }
}
