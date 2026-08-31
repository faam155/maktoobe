<?php

namespace App\Actions\Identity;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SetAccountStatus
{
    // Operator-only command in this phase; future admin HTTP actions must authorize their actor.
    public function handle(User $user, AccountStatus $status, string $reason): void
    {
        DB::transaction(function () use ($user, $status, $reason) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $previous = $user->status->value;
            $user->forceFill(['status' => $status, 'disabled_at' => $status === AccountStatus::Disabled ? now() : null])->save();
            app(RevokeCredentials::class)->handle($user);
            app(RecordAccountAudit::class)->handle($user, 'account.status_changed', ['from' => $previous, 'to' => $status->value, 'reason' => $reason]);
        });
    }
}
