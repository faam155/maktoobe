<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventReportVersion extends Model
{
    use SoftDeletes;

    public const UPDATED_AT = null;

    protected $fillable = ['event_id', 'event_file_id', 'version_number', 'title', 'notes'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(EventReport::class, 'event_report_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(EventFile::class, 'event_file_id');
    }
}
