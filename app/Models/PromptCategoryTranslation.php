<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromptCategoryTranslation extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = ['locale', 'name', 'description'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(PromptCategory::class, 'category_id');
    }
}
