<?php

namespace App\Policies;

use App\Enums\PromptSource;
use App\Models\Prompt;
use App\Models\User;
use App\Services\Prompts\PromptAccess;

class PromptPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, Prompt $prompt): bool
    {
        if ($prompt->source === PromptSource::Library && $actor->can('manage-prompts')) {
            return true;
        }

        return app(PromptAccess::class)->canView($actor, $prompt);
    }

    public function create(User $actor): bool
    {
        return $actor->can('manage-prompts');
    }

    public function update(User $actor, Prompt $prompt): bool
    {
        return $prompt->source === PromptSource::Library && $actor->can('manage-prompts');
    }

    public function delete(User $actor, Prompt $prompt): bool
    {
        return $this->update($actor, $prompt);
    }

    public function publish(User $actor, Prompt $prompt): bool
    {
        return $this->update($actor, $prompt) && $actor->can('publish-prompts');
    }

    public function duplicate(User $actor, Prompt $prompt): bool
    {
        return $this->update($actor, $prompt);
    }
}
