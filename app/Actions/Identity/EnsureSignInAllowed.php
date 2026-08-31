<?php

namespace App\Actions\Identity;

use App\Data\SignInAttempt;
use App\Enums\AccountStatus;
use Closure;
use Illuminate\Validation\ValidationException;

class EnsureSignInAllowed
{
    public function handle(SignInAttempt $attempt, Closure $next): mixed
    {
        if ($attempt->user->trashed() || $attempt->user->status === AccountStatus::Disabled) {
            throw ValidationException::withMessages(['login' => __('auth.failed')]);
        }

        return $next($attempt);
    }
}
