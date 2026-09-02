<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceNotice extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['broadcast' => 'boolean', 'system_content' => 'array', 'occurrence_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    public function reportVersion(): BelongsTo
    {
        return $this->belongsTo(EventReportVersion::class);
    }
}
