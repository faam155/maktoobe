<?php

namespace App\Actions\Events;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChangeEventStatus
{
    public function handle(User $actor, Event $event, EventStatus $target): Event
    {
        Gate::forUser($actor)->authorize('update', $event);

        return DB::transaction(function () use ($actor, $event, $target) {
            $event = Event::lockForUpdate()->findOrFail($event->id);
            if (! $event->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => __('events.invalid_transition')]);
            }
            $from = $event->status->value;
            $event->update(['status' => $target, 'updated_by' => $actor->id]);
            EventActivity::create(['event_id' => $event->id, 'actor_id' => $actor->id, 'action' => $target === EventStatus::Cancelled ? 'event.cancelled' : 'event.status_changed', 'metadata' => ['from' => $from, 'to' => $target->value], 'created_at' => now()]);

            return $event->fresh();
        });
    }
}
