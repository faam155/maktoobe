<?php

namespace App\Services\Social;

use App\Models\SocialMediaAccount;
use App\Models\User;

class SocialProvenance
{
    public function fingerprint(User $actor, SocialMediaAccount $account): string
    {
        $hash = hash_init('sha256');
        foreach (app(SocialAccess::class)->posts($actor, $account)->orderBy('id')->select(['id', 'revision'])->cursor() as $post) {
            hash_update($hash, $post->id.':'.$post->revision.';');
        }
        hash_update($hash, json_encode([$actor->getAllPermissions()->pluck('name')->sort()->values()->all(), $account->revision], JSON_THROW_ON_ERROR));

        return hash_final($hash);
    }
}
