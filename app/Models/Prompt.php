<?php

namespace App\Models;

use App\Enums\PromptSource;
use App\Enums\PromptStatus;
use App\Enums\PromptVisibility;
use Database\Factories\PromptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role;

class Prompt extends Model
{
    /** @use HasFactory<PromptFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'owner_id', 'category_id', 'source', 'title', 'slug', 'description', 'content',
        'content_locale', 'visibility', 'status', 'published_at', 'published_by', 'revision_number',
    ];

    protected function casts(): array
    {
        return [
            'source' => PromptSource::class, 'visibility' => PromptVisibility::class,
            'status' => PromptStatus::class, 'published_at' => 'datetime', 'revision_number' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PromptCategory::class, 'category_id')->withTrashed();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function allowedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'prompt_user_access')->withPivot(['granted_by', 'created_at']);
    }

    public function allowedRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'prompt_role_access')->withPivot(['granted_by', 'created_at']);
    }

    public function uses(): HasMany
    {
        return $this->hasMany(PromptUse::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(PromptFavorite::class);
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'prompt_favorites')->withPivot('created_at');
    }
}
