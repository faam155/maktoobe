<?php

namespace App\Actions\Administration;

use App\Actions\Identity\RecordAccountAudit;
use App\Actions\Identity\RevokeCredentials;
use App\Enums\AccountStatus;
use App\Models\User;
use App\Services\Authorization\SuperAdministratorGuard;
use App\Support\Authorization\Access;
use App\Support\Identity\Identifiers;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateFirstSuperAdministrator
{
    public function handle(string $email): User
    {
        return DB::transaction(function () use ($email) {
            $role = app(SuperAdministratorGuard::class)->lockGovernance();
            if (! $role) {
                throw ValidationException::withMessages(['email' => __('admin.bootstrap_seed_first')]);
            }
            if (User::role(Access::SUPER_ADMINISTRATOR)->where('status', AccountStatus::Active)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['email' => __('admin.bootstrap_exists')]);
            }
            $user = User::where('email', Identifiers::canonical($email))->lockForUpdate()->first();
            if (! $user) {
                throw ValidationException::withMessages(['email' => __('admin.bootstrap_register_first')]);
            }
            $user->forceFill(['status' => AccountStatus::Active, 'email_verified_at' => $user->email_verified_at ?? now(), 'disabled_at' => null])->save();
            $user->syncRoles([$role]);
            app(RevokeCredentials::class)->handle($user);
            app(RecordAccountAudit::class)->handle($user, 'account.first_super_administrator');

            return $user;
        });
    }
}
