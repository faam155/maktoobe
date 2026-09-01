<?php

namespace App\Models;

use Database\Factories\PromptCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PromptCategory extends Model
{
    /** @use HasFactory<PromptCategoryFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slug', 'icon', 'display_order', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return ['display_order' => 'integer', 'is_active' => 'boolean'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(PromptCategoryTranslation::class, 'category_id');
    }

    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class, 'category_id');
    }

    public function translation(string $locale): ?PromptCategoryTranslation
    {
        return $this->translations->firstWhere('locale', $locale);
    }

    public function getNameEnAttribute(): ?string
    {
        return $this->translation('en')?->name;
    }

    public function getNameArAttribute(): ?string
    {
        return $this->translation('ar')?->name;
    }

    public function getDescriptionEnAttribute(): ?string
    {
        return $this->translation('en')?->description;
    }

    public function getDescriptionArAttribute(): ?string
    {
        return $this->translation('ar')?->description;
    }

    public function localizedName(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return (string) ($locale === 'ar' ? ($this->name_ar ?: $this->name_en) : ($this->name_en ?: $this->name_ar));
    }

    public function localizedDescription(?string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        $value = $locale === 'ar' ? ($this->description_ar ?: $this->description_en) : ($this->description_en ?: $this->description_ar);

        return filled($value) ? $value : null;
    }
}
