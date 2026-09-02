<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['slug', 'title', 'description', 'category_id', 'starts_at', 'ends_at', 'timezone', 'location', 'organizer_id', 'status', 'visibility', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'status' => EventStatus::class, 'visibility' => EventVisibility::class];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function allowedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_user_access')->withPivot(['granted_by', 'created_at']);
    }

    public function allowedRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'event_role_access')->withPivot(['granted_by', 'created_at']);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(EventActivity::class);
    }
}
