<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class WorkspaceNotification extends Notification
{
    public function __construct(public int $noticeId) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['notice_id' => $this->noticeId];
    }
}
