<?php

namespace App\Services\Social;

use App\Contracts\SocialMediaProvider;
use App\Models\SocialMediaAccount;

class LinkedInProvider implements SocialMediaProvider
{
    public function fetch(SocialMediaAccount $account): iterable
    {
        throw new \RuntimeException('provider_not_configured');
    }
}
