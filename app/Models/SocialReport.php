<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialReport extends Model
{
    use SoftDeletes;

    protected $table = 'social_reports';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'settings' => 'array', 'summary' => 'encrypted'];
    }
}
