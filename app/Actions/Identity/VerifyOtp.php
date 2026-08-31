<?php

namespace App\Actions\Identity;

use App\Enums\AccountStatus;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Support\Identity\Identifiers;
use Illuminate\Support\Facades\DB;

class VerifyOtp
{
    public function handle(string $id, string $code, string $sessionId, string $purpose, ?User $actor = null): ?User
    {
        return DB::transaction(function () use ($id, $code, $sessionId, $purpose, $actor) {
            // Match credential revocation's user -> challenge lock order.
            $ownerId = OtpChallenge::whereKey($id)->value('user_id');
            $user = $ownerId ? User::lockForUpdate()->find($ownerId) : null;
            $challenge = OtpChallenge::lockForUpdate()->find($id);
            if (! $challenge || $challenge->purpose !== $purpose || ! hash_equals($challenge->session_digest, Identifiers::digest('session:'.$sessionId))) {
                return null;
            }
            if ($challenge->consumed_at || $challenge->invalidated_at || $challenge->expires_at->isPast() || $challenge->attempts >= config('identity.otp_attempts')) {
                return null;
            }
            $challenge->increment('attempts');
            if (! hash_equals($challenge->code_digest, Identifiers::digest('otp:'.$id.':'.$code))) {
                if ($challenge->attempts >= config('identity.otp_attempts')) {
                    $challenge->update(['invalidated_at' => now()]);
                }

                return null;
            }
            if (! $user || $challenge->user_id !== $user->id || $user->status !== AccountStatus::Active || ! $user->hasVerifiedEmail()
                || $user->security_version !== $challenge->security_version
                || ! hash_equals($challenge->target_digest, Identifiers::digest('phone:'.$user->phone_e164))) {
                return null;
            }
            if ($purpose === 'enroll') {
                if (! $actor || ! $user->is($actor)) {
                    return null;
                }
                $user->forceFill(['phone_verified_at' => now()])->save();
                app(RecordAccountAudit::class)->handle($user, 'phone.verified', actor: $actor);
            } elseif (! $user->phone_verified_at) {
                return null;
            }
            $challenge->update(['consumed_at' => now()]);

            return $user;
        });
    }
}
