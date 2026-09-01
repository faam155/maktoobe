<?php

namespace App\Policies;

use App\Models\PromptCategory;
use App\Models\User;

class PromptCategoryPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('manage-categories');
    }

    public function view(User $actor, PromptCategory $category): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->can('manage-categories');
    }

    public function update(User $actor, PromptCategory $category): bool
    {
        return $actor->can('manage-categories');
    }

    public function delete(User $actor, PromptCategory $category): bool
    {
        return $actor->can('manage-categories');
    }
}
