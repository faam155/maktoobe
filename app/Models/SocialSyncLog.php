<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialSyncLog extends Model
{
    protected $table = 'social_sync_logs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
