<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\EventFile;
use App\Models\User;
use App\Services\Events\EventAccess;

class EventFilePolicy
{
    public function create(User $user, Event $event): bool
    {
        return $user->can('upload-event-files') && app(EventAccess::class)->canView($user, $event);
    }

    public function view(User $user, EventFile $file): bool
    {
        return ! $file->trashed() && $file->scan_status === 'clean' && $file->event && app(EventAccess::class)->canView($user, $file->event);
    }

    public function update(User $user, EventFile $file): bool
    {
        return $this->view($user, $file) && $user->can('upload-event-files') && ($file->uploaded_by === $user->id || $user->can('manage-events'));
    }

    public function delete(User $user, EventFile $file): bool
    {
        return $this->update($user, $file);
    }
}
