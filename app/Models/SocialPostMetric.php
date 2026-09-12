<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialPostMetric extends Model
{
    protected $table = 'social_post_metrics';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }
}
