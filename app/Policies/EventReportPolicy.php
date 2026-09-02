<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\EventReport;
use App\Models\User;
use App\Services\Events\EventAccess;

class EventReportPolicy
{
    public function create(User $user, Event $event): bool
    {
        return $user->can('upload-event-files') && app(EventAccess::class)->canView($user, $event);
    }

    public function view(User $user, EventReport $report): bool
    {
        return ! $report->trashed() && $report->event && app(EventAccess::class)->canView($user, $report->event);
    }

    public function update(User $user, EventReport $report): bool
    {
        return $this->view($user, $report) && $user->can('upload-event-files') && ($report->created_by === $user->id || $user->can('manage-events'));
    }

    public function delete(User $user, EventReport $report): bool
    {
        return $this->update($user, $report);
    }
}
