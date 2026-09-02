<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCommunicationRevision extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function communication(): BelongsTo
    {
        return $this->belongsTo(EventCommunication::class, 'event_communication_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
