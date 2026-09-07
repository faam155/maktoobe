<?php

namespace App\Models;

use App\Services\Audit\SafeAuditMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function ($log) {
            $log->metadata = app(SafeAuditMetadata::class)->clean($log->metadata ?? []);
        });
        static::updating(fn () => throw new \LogicException('Audit entries are append-only.'));
        static::deleting(fn () => throw new \LogicException('Audit entries are append-only.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
