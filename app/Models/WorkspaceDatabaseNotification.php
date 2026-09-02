<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\DatabaseNotification;

class WorkspaceDatabaseNotification extends DatabaseNotification
{
    protected $table = 'notifications';

    protected static function booted(): void
    {
        static::creating(function ($notification) {
            if ($notification->notifiable_type === 'user') {
                $notification->user_id = $notification->notifiable_id;
            }
            $notification->notice_id = $notification->data['notice_id'] ?? null;
        });
    }

    public function notice(): BelongsTo
    {
        return $this->belongsTo(WorkspaceNotice::class);
    }
}
