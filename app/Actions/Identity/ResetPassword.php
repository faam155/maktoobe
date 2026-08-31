<?php

namespace App\Actions\Identity;

use App\Enums\AccountStatus;
use App\Support\Identity\IdentityRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetPassword implements ResetsUserPasswords
{
    public function reset($user, array $input): void
    {
        $data = Validator::make($input, ['password' => IdentityRules::password()])->validate();
        DB::transaction(function () use ($user, $data) {
            $user = $user->newQuery()->lockForUpdate()->findOrFail($user->id);
            if ($user->status === AccountStatus::Disabled) {
                throw ValidationException::withMessages(['email' => __('auth.reset_invalid')]);
            }
            $user->forceFill(['password' => $data['password']])->save();
            app(RevokeCredentials::class)->handle($user);
            app(RecordAccountAudit::class)->handle($user, 'password.reset');
        });
    }
}
