<?php

namespace App\Actions\Identity;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RevokeCredentials
{
    // Call while holding the user row lock, within the caller's transaction.
    public function handle(User $user, ?string $exceptSession = null): void
    {
        $user->forceFill(['remember_token' => Str::random(60), 'security_version' => $user->security_version + 1])->save();
        DB::table('sessions')->where('user_id', $user->id)->when($exceptSession, fn ($query) => $query->where('id', '!=', $exceptSession))->delete();
        $user->otpChallenges()->whereNull('consumed_at')->update(['invalidated_at' => now()]);
    }
}
