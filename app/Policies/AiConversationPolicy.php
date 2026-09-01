<?php

namespace App\Policies;

use App\Models\AiConversation;
use App\Models\User;

class AiConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('use-ai');
    }

    public function create(User $user): bool
    {
        return $user->can('use-ai');
    }

    public function view(User $user, AiConversation $conversation): bool
    {
        return $user->can('use-ai') && $conversation->user_id === $user->id;
    }

    public function update(User $user, AiConversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function delete(User $user, AiConversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
