<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialPost extends Model
{
    use SoftDeletes;

    protected $table = 'social_posts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['published_at' => 'immutable_datetime'];
    }

    public function account()
    {
        return $this->belongsTo(SocialMediaAccount::class, 'social_account_id');
    }

    public function contentType()
    {
        return $this->belongsTo(SocialContentType::class, 'content_type_id');
    }

    public function campaign()
    {
        return $this->belongsTo(SocialCampaign::class, 'campaign_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function tags()
    {
        return $this->belongsToMany(SocialTag::class, 'social_post_tags');
    }

    public function metrics()
    {
        return $this->hasMany(SocialPostMetric::class);
    }
}
