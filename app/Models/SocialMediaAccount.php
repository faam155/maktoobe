<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialMediaAccount extends Model
{
    use SoftDeletes;

    protected $table = 'social_media_accounts';

    protected $attributes = ['active' => true, 'revision' => 1];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'settings' => 'array', 'last_sync_at' => 'datetime'];
    }

    public function platform()
    {
        return $this->belongsTo(SocialPlatform::class, 'platform_id');
    }

    public function posts()
    {
        return $this->hasMany(SocialPost::class, 'social_account_id');
    }

    public function metrics()
    {
        return $this->hasMany(SocialAccountMetric::class, 'social_account_id');
    }
}
