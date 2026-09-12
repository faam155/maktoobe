<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialAudienceMetric extends Model
{
    protected $table = 'social_audience_metrics';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }
}
