<?php

namespace App\Policies;

use App\Enums\AccountStatus;
use App\Models\Event;
use App\Models\User;
use App\Services\Events\EventAccess;

class EventCommunicationPolicy
{
    public function viewAny(User $user, Event $event): bool
    {
        return $user->status === AccountStatus::Active && $user->hasVerifiedEmail() && app(EventAccess::class)->canView($user, $event);
    }

    public function manage(User $user, Event $event): bool
    {
        return $this->viewAny($user, $event) && $user->can('manage-events');
    }

    public function generate(User $user, Event $event): bool
    {
        return $this->manage($user, $event) && $user->can('use-ai');
    }
}
