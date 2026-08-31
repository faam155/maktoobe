<?php

namespace App\Actions\Administration;

use App\Actions\Identity\RecordAccountAudit;
use App\Actions\Identity\RevokeCredentials;
use App\Models\User;
use App\Support\Identity\Identifiers;
use App\Support\Identity\IdentityRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateUser
{
    public function handle(User $actor, User $user, array $input): User
    {
        Gate::forUser($actor)->authorize('update', $user);
        $input['username'] = Identifiers::canonical($input['username'] ?? '');
        $input['email'] = Identifiers::canonical($input['email'] ?? '');
        $input['phone'] = Identifiers::phone($input['phone'] ?? null);
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[a-z0-9][a-z0-9_.-]*$/', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'string', 'email:rfc', 'max:254', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => [...IdentityRules::phone(), Rule::unique('users', 'phone_e164')->ignore($user->id)],
            'locale' => ['required', Rule::in(['en', 'ar'])],
        ])->validate();

        return DB::transaction(function () use ($actor, $user, $data) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $emailChanged = $user->email !== $data['email'];
            $phoneChanged = $user->phone_e164 !== $data['phone'];
            $user->forceFill([
                'name' => trim($data['name']), 'username' => $data['username'], 'email' => $data['email'],
                'phone_e164' => $data['phone'], 'locale' => $data['locale'],
                'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
                'phone_verified_at' => $phoneChanged ? null : $user->phone_verified_at,
            ])->save();
            if ($emailChanged || $phoneChanged) {
                app(RevokeCredentials::class)->handle($user);
            }
            app(RecordAccountAudit::class)->handle($user, 'account.updated_by_admin', [
                'email_changed' => $emailChanged, 'phone_changed' => $phoneChanged,
            ], $actor);

            return $user;
        });
    }
}
