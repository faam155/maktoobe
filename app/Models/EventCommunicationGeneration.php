<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCommunicationGeneration extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['status' => 'queued'];

    protected $hidden = ['input_snapshot', 'result', 'settings_snapshot', 'provider_request_id'];

    protected function casts(): array
    {
        return ['base_revision' => 'integer', 'input_snapshot' => 'encrypted:array', 'result' => 'encrypted:array', 'settings_snapshot' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'applied_at' => 'datetime'];
    }

    public function communication(): BelongsTo
    {
        return $this->belongsTo(EventCommunication::class, 'event_communication_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brandVersion(): BelongsTo
    {
        return $this->belongsTo(BrandGuidelineVersion::class, 'brand_guideline_version_id');
    }
}
