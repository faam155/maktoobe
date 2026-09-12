<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialAiInsight extends Model
{
    protected $table = 'social_ai_insights';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'settings' => 'array', 'snapshot' => 'encrypted:array', 'content' => 'encrypted', 'recommendations' => 'encrypted'];
    }
}
