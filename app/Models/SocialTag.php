<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialTag extends Model
{
    use SoftDeletes;

    protected $table = 'social_tags';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function posts()
    {
        return $this->belongsToMany(SocialPost::class, 'social_post_tags');
    }
}
