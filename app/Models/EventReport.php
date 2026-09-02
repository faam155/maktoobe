<?php

namespace App\Models;

use App\Enums\EventReportType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventReport extends Model
{
    use SoftDeletes;

    protected $fillable = ['type', 'created_by'];

    protected function casts(): array
    {
        return ['type' => EventReportType::class];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(EventReportVersion::class);
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(EventReportVersion::class)->ofMany('version_number', 'max');
    }
}
