<?php

namespace App\Policies;

use App\Models\BrandGuidelineVersion;
use App\Models\User;

class BrandGuidelineVersionPolicy
{
    public function view(User $user, BrandGuidelineVersion $version): bool
    {
        return $user->can('manage-brand-guidelines');
    }

    public function update(User $user, BrandGuidelineVersion $version): bool
    {
        return $this->view($user, $version);
    }
}
