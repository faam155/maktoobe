<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\EventActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteEvent
{
    public function handle(User $actor, Event $event): void
    {
        Gate::forUser($actor)->authorize('delete', $event);
        DB::transaction(function () use ($actor, $event) {
            EventActivity::create(['event_id' => $event->id, 'actor_id' => $actor->id, 'action' => 'event.deleted', 'metadata' => null, 'created_at' => now()]);
            $event->update(['updated_by' => $actor->id]);
            $event->delete();
        });
    }
}
