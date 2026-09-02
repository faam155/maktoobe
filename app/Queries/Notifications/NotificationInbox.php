<?php

namespace App\Queries\Notifications;

use App\Models\User;
use App\Models\WorkspaceDatabaseNotification;
use App\Models\WorkspaceNotice;
use App\Services\Notifications\NoticeAccess;

class NotificationInbox
{
    public function query(User $user)
    {
        return WorkspaceDatabaseNotification::where('user_id', $user->id)->where('notifiable_type', $user->getMorphClass())->where('notifiable_id', $user->id)->whereNull('dismissed_at')
            ->whereIn('notice_id', app(NoticeAccess::class)->visibleTo(WorkspaceNotice::query(), $user)->select('workspace_notices.id'));
    }

    public function unread(User $user): int
    {
        return $this->query($user)->whereNull('read_at')->count();
    }

    public function describe(WorkspaceDatabaseNotification $notification): array
    {
        $notice = $notification->notice;
        $title = $notice->event?->title ?? $notice->prompt?->title ?? ($notice->system_content[app()->getLocale()]['title'] ?? '');
        $body = $notice->kind === 'system' ? ($notice->system_content[app()->getLocale()]['body'] ?? '') : __('notifications.'.$notice->kind);

        return compact('title', 'body');
    }
}
