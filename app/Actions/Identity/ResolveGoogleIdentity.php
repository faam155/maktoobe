<?php

namespace App\Actions\Identity;

use App\Models\SocialAccount;
use App\Models\User;
use App\Support\Identity\Identifiers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as ProviderUser;

class ResolveGoogleIdentity
{
    public function handle(ProviderUser $identity, ?User $actor = null): User
    {
        $subject = (string) $identity->getId();
        $email = Identifiers::canonical($identity->getEmail());
        $raw = $identity->getRaw();
        if (($raw['email_verified'] ?? false) !== true || ! preg_match('/^[a-zA-Z0-9_-]{1,255}$/D', $subject)) {
            throw ValidationException::withMessages(['google' => __('auth.google_unverified')]);
        }
        Validator::make(['email' => $email], ['email' => 'required|email:rfc|max:254'])->validate();

        return DB::transaction(function () use ($identity, $subject, $email, $actor) {
            $linked = SocialAccount::where('provider', 'google')->where('provider_subject', $subject)->first();
            if ($actor) {
                Gate::authorize('manageSecurity', $actor);
                if (($linked && $linked->user_id !== $actor->id) || $actor->socialAccounts()->where('provider', 'google')->where('provider_subject', '!=', $subject)->exists()) {
                    throw ValidationException::withMessages(['google' => __('auth.google_link_conflict')]);
                }
                SocialAccount::firstOrCreate(['provider' => 'google', 'provider_subject' => $subject], ['user_id' => $actor->id]);
                app(RecordAccountAudit::class)->handle($actor, 'google.linked', actor: $actor);

                return $actor;
            }
            if ($linked) {
                if (! $linked->user) {
                    throw ValidationException::withMessages(['google' => __('auth.failed')]);
                }

                return $linked->user;
            }
            if (User::withTrashed()->where('email', $email)->exists()) {
                throw ValidationException::withMessages(['google' => __('auth.google_collision')]);
            }
            $user = new User;
            $user->forceFill([
                'name' => Str::limit(trim((string) $identity->getName()) ?: 'Google user', 150, ''),
                'email' => $email, 'username' => 'user_'.Str::lower(Str::random(16)),
                'password' => null, 'email_verified_at' => now(), 'status' => 'pending',
                'locale' => app()->getLocale(), 'timezone' => 'UTC',
            ])->save();
            $user->socialAccounts()->create(['provider' => 'google', 'provider_subject' => $subject]);
            app(RecordAccountAudit::class)->handle($user, 'account.google_registered');

            return $user;
        });
    }
}
