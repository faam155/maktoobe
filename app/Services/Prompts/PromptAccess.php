<?php

namespace App\Services\Prompts;

use App\Enums\PromptSource;
use App\Enums\PromptStatus;
use App\Enums\PromptVisibility;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PromptAccess
{
    public function visibleTo(Builder $query, User $user): Builder
    {
        $roleIds = $user->roles()->pluck('roles.id');

        return $query->where('source', PromptSource::Library->value)
            ->where('status', PromptStatus::Published->value)
            ->whereNotNull('published_at')
            ->where(function ($query) {
                $query->whereNull('category_id')->orWhereHas('category', fn ($category) => $category->where('is_active', true));
            })
            ->where(function ($query) use ($user, $roleIds) {
                $query->where('visibility', PromptVisibility::AllUsers->value)
                    ->orWhere(fn ($private) => $private->where('visibility', PromptVisibility::Private->value)->where('owner_id', $user->id))
                    ->orWhere(fn ($selected) => $selected->where('visibility', PromptVisibility::SelectedUsers->value)->whereHas('allowedUsers', fn ($users) => $users->where('users.id', $user->id)))
                    ->orWhere(fn ($selected) => $selected->where('visibility', PromptVisibility::SelectedRoles->value)->whereHas('allowedRoles', fn ($roles) => $roles->whereIn('roles.id', $roleIds)));
            });
    }

    public function canView(User $user, Prompt $prompt): bool
    {
        return $this->visibleTo(Prompt::query()->whereKey($prompt->id), $user)->exists();
    }
}
