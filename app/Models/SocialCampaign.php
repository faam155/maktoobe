<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialCampaign extends Model
{
    use SoftDeletes;

    protected $table = 'social_campaigns';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function account()
    {
        return $this->belongsTo(SocialMediaAccount::class, 'social_account_id');
    }

    public function posts()
    {
        return $this->hasMany(SocialPost::class, 'campaign_id');
    }
}
