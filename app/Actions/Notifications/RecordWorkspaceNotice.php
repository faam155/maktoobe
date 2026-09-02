<?php

namespace App\Actions\Notifications;

use App\Jobs\DeliverWorkspaceNotice;
use App\Models\User;
use App\Models\WorkspaceNotice;

class RecordWorkspaceNotice
{
    /** Internal domain hook: inputs are server-derived, never request arrays. */
    public function handle(string $kind, string $key, array $references = []): WorkspaceNotice
    {
        $notice = WorkspaceNotice::firstOrCreate(['operation_key' => $key], $references + ['kind' => $kind, 'audience_ceiling' => (int) User::max('id')]);
        if (! $notice->completed_at) {
            DeliverWorkspaceNotice::dispatch($notice->id)->afterCommit();
        }

        return $notice;
    }
}
