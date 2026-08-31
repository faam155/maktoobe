<?php

namespace App\Actions\Identity;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Services\Authorization\SuperAdministratorGuard;
use Illuminate\Support\Facades\DB;

class SetAccountStatus
{
    // HTTP callers authorize before entering this shared transactional lifecycle action.
    public function handle(User $user, AccountStatus $status, string $reason, ?User $actor = null): void
    {
        DB::transaction(function () use ($user, $status, $reason, $actor) {
            $guard = app(SuperAdministratorGuard::class);
            $guard->lockGovernance();
            $user = User::lockForUpdate()->findOrFail($user->id);
            if ($status !== AccountStatus::Active) {
                $guard->assertCanRemoveActiveSuper($user);
            }
            $previous = $user->status->value;
            $user->forceFill(['status' => $status, 'disabled_at' => $status === AccountStatus::Disabled ? now() : null])->save();
            app(RevokeCredentials::class)->handle($user);
            app(RecordAccountAudit::class)->handle($user, 'account.status_changed', ['from' => $previous, 'to' => $status->value, 'reason' => $reason], $actor);
        });
    }
}
