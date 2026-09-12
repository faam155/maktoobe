<?php

namespace App\Policies;

use App\Models\SocialPost;
use App\Models\User;
use App\Services\Social\SocialAccess;

class SocialPostPolicy
{
    public function view(User $actor, SocialPost $post): bool
    {
        app(SocialAccess::class)->post($actor, $post, 'posts.view');

        return true;
    }

    public function update(User $actor, SocialPost $post): bool
    {
        app(SocialAccess::class)->post($actor, $post, 'posts.edit');

        return true;
    }

    public function delete(User $actor, SocialPost $post): bool
    {
        app(SocialAccess::class)->post($actor, $post, 'posts.delete');

        return true;
    }
}
