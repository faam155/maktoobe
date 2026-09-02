<?php

namespace App\Console\Commands;

use App\Actions\Notifications\RecordWorkspaceNotice;
use App\Jobs\DeliverWorkspaceNotice;
use App\Models\Event;
use App\Models\WorkspaceNotice;
use Illuminate\Console\Command;

class QueueWorkspaceNotifications extends Command
{
    protected $signature = 'notifications:dispatch';

    protected $description = 'Queue due event reminders and resume undelivered database notices';

    public function handle(): int
    {
        Event::whereIn('status', ['planned', 'confirmed'])->where('starts_at', '>', now())->where('starts_at', '<=', now()->addDay())->chunkById(100, function ($events) {
            foreach ($events as $event) {
                app(RecordWorkspaceNotice::class)->handle('event_reminder', 'reminder:'.$event->id.':'.$event->starts_at->timestamp, ['event_id' => $event->id, 'occurrence_at' => $event->starts_at]);
            }
        });
        WorkspaceNotice::whereNull('completed_at')->orderBy('id')->limit(100)->get()->each(fn ($notice) => DeliverWorkspaceNotice::dispatch($notice->id));
        $this->info('Due reminders and pending notices queued.');

        return self::SUCCESS;
    }
}
