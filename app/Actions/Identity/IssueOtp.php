<?php

namespace App\Actions\Identity;

use App\Contracts\SmsGateway;
use App\Enums\AccountStatus;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Support\Identity\Identifiers;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class IssueOtp
{
    public function handle(string $phone, string $sessionId, string $purpose = 'login', ?User $actor = null): OtpChallenge
    {
        if (! in_array($purpose, ['login', 'enroll'], true)) {
            throw new \InvalidArgumentException('Invalid OTP purpose.');
        }
        $target = Identifiers::digest('phone:'.$phone);

        return Cache::lock('otp-issue:'.$target, 10)->block(3, function () use ($phone, $sessionId, $purpose, $actor, $target) {
            if (RateLimiter::tooManyAttempts('otp-cooldown:'.$target, 1) || RateLimiter::tooManyAttempts('otp-hour:'.$target, 5)) {
                throw ValidationException::withMessages(['phone' => __('auth.otp_throttled')]);
            }
            RateLimiter::hit('otp-cooldown:'.$target, config('identity.otp_cooldown'));
            RateLimiter::hit('otp-hour:'.$target, 3600);
            $user = User::where('phone_e164', $phone)->where('status', AccountStatus::Active)->whereNotNull('email_verified_at')->first();
            if ($purpose === 'login' && ! $user?->phone_verified_at) {
                $user = null;
            }
            if ($purpose === 'enroll' && (! $actor || ! $user?->is($actor))) {
                abort(403);
            }
            $id = (string) Str::uuid();
            $code = (string) random_int(100000, 999999);
            $challenge = DB::transaction(function () use ($user, $purpose, $target, $sessionId, $id, $code) {
                OtpChallenge::where('target_digest', $target)->where('purpose', $purpose)->whereNull('consumed_at')->update(['invalidated_at' => now()]);

                return OtpChallenge::create([
                    'id' => $id, 'user_id' => $user?->id, 'purpose' => $purpose, 'target_digest' => $target,
                    'session_digest' => Identifiers::digest('session:'.$sessionId),
                    'code_digest' => Identifiers::digest('otp:'.$id.':'.$code),
                    'security_version' => $user?->security_version,
                    'expires_at' => now()->addSeconds(config('identity.otp_lifetime')),
                ]);
            });
            if ($user) {
                try {
                    app(SmsGateway::class)->sendOtp($phone, $code);
                } catch (Throwable) {
                    // A failed delivery never leaves a usable challenge or logs the OTP/provider body.
                    $challenge->update(['invalidated_at' => now()]);
                    app(RecordAccountAudit::class)->handle($user, 'otp.delivery_failed');
                }
            }

            // Unknown/ineligible numbers also get a session-bound decoy challenge.
            return $challenge;
        });
    }
}
