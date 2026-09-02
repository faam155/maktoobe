<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventCommunication extends Model
{
    public const TYPES = ['internal_email', 'linkedin_post', 'general_copy'];

    public const LANGUAGES = ['ar', 'en'];

    public const STATUSES = ['draft', 'ready', 'approved', 'used'];

    protected $guarded = ['id', 'event_id'];

    protected $attributes = ['revision_number' => 0, 'title' => '', 'status' => 'draft'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime', 'revision_number' => 'integer'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(EventCommunicationRevision::class);
    }

    public function generations(): HasMany
    {
        return $this->hasMany(EventCommunicationGeneration::class);
    }
}
