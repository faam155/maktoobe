<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialAccountMetric extends Model
{
    protected $table = 'social_account_metrics';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }
}
