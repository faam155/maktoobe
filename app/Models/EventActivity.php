<?php

namespace App\Models;

use App\Actions\Audit\RecordAudit;
use App\Services\Audit\SafeAuditMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventActivity extends Model
{
    public $timestamps = false;

    protected $fillable = ['event_id', 'actor_id', 'action', 'metadata', 'created_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function ($activity) {
            $activity->metadata = app(SafeAuditMetadata::class)->clean($activity->metadata ?? []);
        });
        static::created(fn ($activity) => app(RecordAudit::class)->event($activity));
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
