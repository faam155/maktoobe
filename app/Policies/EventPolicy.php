<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use App\Services\Events\EventAccess;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Event $event): bool
    {
        return app(EventAccess::class)->canView($user, $event);
    }

    public function create(User $user): bool
    {
        return $user->can('manage-events');
    }

    public function update(User $user, Event $event): bool
    {
        return $user->can('manage-events');
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->can('manage-events');
    }

    public function duplicate(User $user, Event $event): bool
    {
        return $user->can('manage-events');
    }
}
