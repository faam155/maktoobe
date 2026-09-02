<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\WorkspaceNotice;
use App\Notifications\WorkspaceNotification;
use App\Services\Notifications\NoticeAccess;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class DeliverWorkspaceNotice implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $noticeId)
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $more = DB::transaction(function () {
            $notice = WorkspaceNotice::lockForUpdate()->find($this->noticeId);
            if (! $notice || $notice->completed_at) {
                return false;
            }
            $users = User::where('id', '>', $notice->last_user_id)->where('id', '<=', $notice->audience_ceiling)->orderBy('id')->limit(50)->get();
            foreach ($users as $user) {
                if (app(NoticeAccess::class)->canView($user, $notice) && ! $user->notifications()->where('notice_id', $notice->id)->exists()) {
                    $user->notifyNow(new WorkspaceNotification($notice->id));
                }
                $notice->last_user_id = $user->id;
            }
            if ($users->count() < 50) {
                $notice->completed_at = now();
            }
            $notice->save();

            return ! $notice->completed_at;
        });
        if ($more) {
            self::dispatch($this->noticeId)->afterCommit();
        }
    }
}
