<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialTarget extends Model
{
    protected $table = 'social_targets';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'value' => 'float'];
    }
}
