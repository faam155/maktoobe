<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $fillable = ['canonical_name', 'display_name'];

    public function prompts(): BelongsToMany
    {
        return $this->belongsToMany(Prompt::class);
    }
}
