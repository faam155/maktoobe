<?php

namespace App\Actions\Events;

use App\Actions\Notifications\RecordWorkspaceNotice;
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
            $activity = EventActivity::create(['event_id' => $event->id, 'actor_id' => $actor->id, 'action' => $target === EventStatus::Cancelled ? 'event.cancelled' : 'event.status_changed', 'metadata' => ['from' => $from, 'to' => $target->value], 'created_at' => now()]);
            if ($from !== $target->value) {
                $kind = $target === EventStatus::Cancelled ? 'event_cancelled' : ($from === 'draft' ? 'event_published' : 'event_updated');
                app(RecordWorkspaceNotice::class)->handle($kind, 'event:'.$activity->id, ['event_id' => $event->id]);
                if ($from === 'draft' && $target !== EventStatus::Cancelled) {
                    foreach ($event->allowedUsers()->pluck('users.id')->push($event->organizer_id)->unique() as $id) {
                        app(RecordWorkspaceNotice::class)->handle('event_assigned', 'assignment:'.$activity->id.':'.$id, ['event_id' => $event->id, 'target_user_id' => $id, 'broadcast' => false]);
                    }
                }
            }

            return $event->fresh();
        });
    }
}
