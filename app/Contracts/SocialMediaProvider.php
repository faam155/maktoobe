<?php

namespace App\Contracts;

use App\Models\SocialMediaAccount;

interface SocialMediaProvider
{
    /** Return validated interval inputs consumed by SaveSocialMetric; never cumulative totals. */
    public function fetch(SocialMediaAccount $account): iterable;
}
