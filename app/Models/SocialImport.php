<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialImport extends Model
{
    protected $table = 'social_imports';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['preview' => 'encrypted:array', 'errors' => 'array'];
    }
}
