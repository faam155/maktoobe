<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventCategory extends Model
{
    protected $fillable = ['slug', 'display_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(EventCategoryTranslation::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'category_id');
    }

    public function name(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->translations->firstWhere('locale', $locale)?->name
            ?? $this->translations->firstWhere('locale', 'en')?->name ?? $this->slug;
    }
}
