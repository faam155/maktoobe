<?php

namespace App\Actions\Events;

use App\Models\EventFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteEventFile
{
    public function handle(User $actor, EventFile $file): void
    {
        Gate::forUser($actor)->authorize('delete', $file);
        DB::transaction(function () use ($actor, $file) {
            $file = EventFile::whereKey($file->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('delete', $file);
            $file->event->activities()->create(['actor_id' => $actor->id, 'action' => 'event.file_deleted', 'metadata' => ['file_id' => $file->id], 'created_at' => now()]);
            $file->delete();
        });
    }
}
