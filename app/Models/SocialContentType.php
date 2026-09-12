<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialContentType extends Model
{
    protected $table = 'social_content_types';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function label(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name;
    }
}
