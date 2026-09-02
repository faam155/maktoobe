<?php

namespace App\Actions\Events;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\EventActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class DuplicateEvent
{
    public function handle(User $actor, Event $event): Event
    {
        Gate::forUser($actor)->authorize('duplicate', $event);

        return DB::transaction(function () use ($actor, $event) {
            $copy = $event->replicate(['slug', 'status', 'visibility', 'created_by', 'updated_by']);
            $copy->fill(['title' => Str::substr(__('events.copy_title', ['title' => $event->title]), 0, 180), 'slug' => Str::substr(Str::slug($event->title), 0, 170).'-'.Str::lower(Str::random(16)), 'status' => EventStatus::Draft, 'visibility' => EventVisibility::Private, 'organizer_id' => $actor->id, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
            $copy->save();
            EventActivity::create(['event_id' => $copy->id, 'actor_id' => $actor->id, 'action' => 'event.duplicated', 'metadata' => ['source_event_id' => $event->id], 'created_at' => now()]);

            return $copy;
        });
    }
}
