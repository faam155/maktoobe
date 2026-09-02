<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\EventFile;
use App\Models\EventReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteEventReport
{
    public function handle(User $actor, EventReport $report): void
    {
        Gate::forUser($actor)->authorize('delete', $report);
        DB::transaction(function () use ($actor, $report) {
            $event = Event::whereKey($report->event_id)->lockForUpdate()->firstOrFail();
            $report = $event->reports()->whereKey($report->id)->firstOrFail();
            Gate::forUser($actor)->authorize('delete', $report);
            $fileIds = $report->versions()->pluck('event_file_id');
            $report->versions()->delete();
            EventFile::where('event_id', $event->id)->whereIn('id', $fileIds)->delete();
            $report->delete();
            $event->activities()->create(['actor_id' => $actor->id, 'action' => 'event.report_deleted', 'metadata' => ['report_id' => $report->id], 'created_at' => now()]);
        });
    }
}
